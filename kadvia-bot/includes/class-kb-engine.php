<?php
/**
 * The bot's work loop. Runs every 10 minutes (WP-Cron or a real server cron):
 *  1. check new lead emails, 2. read replies, 3. write the next drafts with Claude,
 *  4. send approved messages inside the sending window and daily limit,
 *  5. close finished sequences, 6. send weekly client reports.
 *
 * @package kadvia-bot
 */

defined( 'ABSPATH' ) || exit;

class KB_Engine {

	const HOOK = 'kb_tick';

	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'schedules' ) );
		add_action( self::HOOK, array( __CLASS__, 'run' ) );
		add_action( 'plugins_loaded', array( __CLASS__, 'maybe_upgrade' ) );
	}

	public static function schedules( $s ) {
		$s['kb_ten_minutes'] = array(
			'interval' => 600,
			'display'  => 'Every 10 minutes (Kadvia Bot)',
		);
		return $s;
	}

	public static function activate() {
		KB_DB::install();
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + 60, 'kb_ten_minutes', self::HOOK );
		}
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( self::HOOK );
	}

	public static function maybe_upgrade() {
		if ( (int) get_option( 'kb_schema' ) < KB_DB::SCHEMA_VERSION ) {
			KB_DB::install();
		}
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + 60, 'kb_ten_minutes', self::HOOK );
		}
	}

	/**
	 * One full run. Returns a short summary for the dashboard.
	 */
	public static function run() {
		if ( get_transient( 'kb_running' ) ) {
			return 'Already running, try again in a minute.';
		}
		set_transient( 'kb_running', 1, 9 * MINUTE_IN_SECONDS );
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		$summary = array();
		try {
			$checked = KB_Leads::verify_pending( 200 );
			if ( $checked ) {
				$summary[] = $checked . ' emails checked';
			}
			$summary[] = self::check_replies() . ' replies read';
			$summary[] = self::prepare() . ' drafts written';
			$summary[] = self::send() . ' messages sent';
			self::finish_sequences();
			self::weekly_reports();
		} catch ( Throwable $e ) {
			KB_Log::error( 'Run stopped: ' . $e->getMessage() );
			$summary[] = 'error: ' . $e->getMessage();
		}
		delete_transient( 'kb_running' );
		$text = implode( ', ', $summary );
		update_option(
			'kb_last_run',
			array(
				't' => KB_DB::now(),
				's' => $text,
			),
			false
		);
		return $text;
	}

	/* ---------- helpers ---------- */

	public static function in_window() {
		$s    = KB_Settings::all();
		$hour = (int) wp_date( 'G' );
		$dow  = (int) wp_date( 'N' );
		if ( $s['skip_weekends'] && $dow >= 6 ) {
			return false;
		}
		return $hour >= (int) $s['window_start'] && $hour < (int) $s['window_end'];
	}

	private static function later( $days ) {
		return wp_date( 'Y-m-d H:i:s', time() + (int) round( $days * DAY_IN_SECONDS ) );
	}

	public static function sent_today( $channel = 'email' ) {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . KB_DB::t( 'messages' ) . " WHERE channel = %s AND direction = 'out' AND status IN ('sent','simulated') AND sent_at >= %s",
				$channel,
				wp_date( 'Y-m-d 00:00:00' )
			)
		);
	}

	public static function unsubscribe( $lead, $source ) {
		KB_DB::update_lead( $lead->id, array( 'status' => 'unsubscribed' ) );
		KB_DB::suppress( $lead->email, 'unsubscribe' );
		KB_DB::cancel_pending( $lead->id, false );
		KB_Log::add( 'Unsubscribed ' . $lead->email . ' (' . $source . ').' );
	}

	private static function has_open( $lead_id, $channel ) {
		global $wpdb;
		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT 1 FROM ' . KB_DB::t( 'messages' ) . " WHERE lead_id = %d AND channel = %s AND direction = 'out' AND kind = 'sequence' AND status IN ('draft','approved') LIMIT 1",
				$lead_id,
				$channel
			)
		);
	}

	private static function first_subject( $lead_id ) {
		global $wpdb;
		return (string) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT subject FROM ' . KB_DB::t( 'messages' ) . " WHERE lead_id = %d AND channel = 'email' AND kind = 'sequence' AND step = 1 AND status IN ('sent','simulated') ORDER BY id ASC LIMIT 1",
				$lead_id
			)
		);
	}

	/* ---------- 1. write drafts ---------- */

	/** Write the next messages for leads that are due. Returns how many drafts were made. */
	public static function prepare() {
		global $wpdb;
		$s      = KB_Settings::all();
		$budget = (int) $s['ai_per_run'];
		$made   = 0;
		if ( ! $s['api_key'] ) {
			return 0;
		}
		$initial = $s['require_approval'] ? 'draft' : 'approved';

		foreach ( KB_DB::campaigns( 'active' ) as $c ) {
			if ( $budget <= 0 ) {
				break;
			}
			$channels = KB_DB::channels( $c );
			$leads    = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM ' . KB_DB::t( 'leads' ) . " WHERE campaign_id = %d AND status IN ('new','active') AND step < %d
					AND (next_action_at IS NULL OR next_action_at <= %s) AND email_status <> 'unknown' ORDER BY step DESC, id ASC LIMIT %d",
					$c->id,
					(int) $c->steps,
					KB_DB::now(),
					$budget * 2
				)
			);
			foreach ( $leads as $lead ) {
				if ( $budget <= 0 ) {
					break;
				}
				$can_email = in_array( 'email', $channels, true ) && $lead->email && in_array( $lead->email_status, array( 'valid', 'risky' ), true ) && ! KB_DB::is_suppressed( $lead->email );
				$next      = (int) $lead->step + 1;

				try {
					// Email step.
					if ( $can_email && ! self::has_open( $lead->id, 'email' ) ) {
						$mail    = KB_AI::write_email( $c, $lead, $next, (int) $c->steps );
						--$budget;
						$subject = $mail['subject'];
						if ( $next > 1 ) {
							$first   = self::first_subject( $lead->id );
							$subject = 'Re: ' . ( $first ? $first : $subject );
						}
						KB_DB::add_message(
							array(
								'lead_id'     => $lead->id,
								'campaign_id' => $c->id,
								'channel'     => 'email',
								'kind'        => 'sequence',
								'step'        => $next,
								'subject'     => $subject,
								'body'        => $mail['body'],
								'status'      => $initial,
							)
						);
						++$made;
					}

					// Other channels, once per lead at the start.
					if ( 1 === $next ) {
						if ( in_array( 'linkedin', $channels, true ) && $lead->linkedin_url && $budget > 0 && ! self::has_open( $lead->id, 'linkedin' ) ) {
							$text = KB_AI::write_linkedin( $c, $lead );
							--$budget;
							self::add_task( $lead, $c, 'linkedin', 'LinkedIn: connect and message', $text );
							++$made;
						}
						if ( in_array( 'call', $channels, true ) && $lead->phone && $budget > 0 && ! self::has_open( $lead->id, 'call' ) ) {
							$text = KB_AI::write_call_script( $c, $lead );
							--$budget;
							self::add_task( $lead, $c, 'call', 'Call ' . KB_DB::full_name( $lead ), $text );
							++$made;
						}
						if ( in_array( 'whatsapp', $channels, true ) && $lead->wa_opt_in && $lead->phone && KB_WhatsApp::ready() && ! self::has_open( $lead->id, 'whatsapp' ) ) {
							KB_DB::add_message(
								array(
									'lead_id'     => $lead->id,
									'campaign_id' => $c->id,
									'channel'     => 'whatsapp',
									'kind'        => 'sequence',
									'step'        => 1,
									'subject'     => 'WhatsApp template',
									'body'        => KB_WhatsApp::preview( $lead ),
									'status'      => $initial,
								)
							);
							++$made;
						}
					}

					if ( $can_email ) {
						// Wait for this email to be sent before planning the next one.
						$update = array(
							'status'         => 'active',
							'next_action_at' => '2099-01-01 00:00:00',
						);
					} else {
						// No email for this lead: tasks only, then the sequence is over.
						$update = array(
							'status'         => 'active',
							'step'           => (int) $c->steps,
							'next_action_at' => self::later( (int) $c->gap_days * (int) $c->steps ),
						);
					}
					KB_DB::update_lead( $lead->id, $update );
				} catch ( Throwable $e ) {
					KB_Log::error( 'Could not write for ' . ( $lead->email ? $lead->email : KB_DB::full_name( $lead ) ) . ': ' . $e->getMessage() );
					if ( str_contains( $e->getMessage(), 'API key' ) || str_contains( $e->getMessage(), 'rate limit' ) || str_contains( $e->getMessage(), 'reach the Claude API' ) ) {
						return $made; // Stop this run; nothing else will work either.
					}
				}
			}
		}
		if ( $made ) {
			KB_Log::add( $made . ' new drafts/tasks written.' );
		}
		return $made;
	}

	private static function add_task( $lead, $campaign, $channel, $title, $text ) {
		KB_DB::add_message(
			array(
				'lead_id'     => $lead->id,
				'campaign_id' => $campaign->id,
				'channel'     => $channel,
				'kind'        => 'sequence',
				'step'        => 1,
				'subject'     => $title,
				'body'        => $text,
				'status'      => 'draft',
			)
		);
	}

	/* ---------- 2. send ---------- */

	/** Send approved emails and WhatsApp messages. Returns how many went out. */
	public static function send() {
		global $wpdb;
		$s = KB_Settings::all();
		if ( ! self::in_window() ) {
			return 0;
		}
		$sent  = 0;
		$room  = min( (int) $s['per_run_limit'], (int) $s['daily_limit'] - self::sent_today( 'email' ) );
		$queue = $room > 0 ? $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . KB_DB::t( 'messages' ) . " WHERE channel = 'email' AND direction = 'out' AND status = 'approved' ORDER BY (kind = 'reply') DESC, id ASC LIMIT %d",
				$room
			)
		) : array();

		foreach ( $queue as $m ) {
			$lead = KB_DB::lead( $m->lead_id );
			$c    = KB_DB::campaign( $m->campaign_id );
			if ( ! $lead || ! $c ) {
				KB_DB::update_message( $m->id, array( 'status' => 'cancelled' ) );
				continue;
			}
			$blocked = KB_DB::is_suppressed( $lead->email ) || in_array( $lead->status, array( 'unsubscribed', 'bounced' ), true );
			if ( 'sequence' === $m->kind ) {
				$blocked = $blocked || ! in_array( $lead->status, array( 'new', 'active' ), true ) || 'active' !== $c->status;
			}
			if ( $blocked ) {
				KB_DB::update_message( $m->id, array( 'status' => 'cancelled' ) );
				continue;
			}

			$in_reply_to = 'reply' === $m->kind ? $m->in_reply_to : ( (int) $m->step > 1 ? $lead->thread_id : '' );
			if ( $s['dry_run'] ) {
				$result = '<dry-run.' . $m->id . '@kadvia.local>';
			} else {
				$result = KB_Mailer::send( $lead, $c, $m->subject, $m->body, $in_reply_to );
			}
			if ( is_wp_error( $result ) ) {
				KB_DB::update_message(
					$m->id,
					array(
						'status' => 'failed',
						'error'  => $result->get_error_message(),
					)
				);
				KB_Log::error( 'Send failed to ' . $lead->email . ': ' . $result->get_error_message() );
				continue;
			}
			KB_DB::update_message(
				$m->id,
				array(
					'status'  => $s['dry_run'] ? 'simulated' : 'sent',
					'ext_id'  => $result,
					'sent_at' => KB_DB::now(),
				)
			);
			++$sent;

			$update = array( 'last_contact_at' => KB_DB::now() );
			if ( 'sequence' === $m->kind ) {
				$update['step']           = (int) $m->step;
				$update['next_action_at'] = self::later( (int) $c->gap_days );
				if ( 1 === (int) $m->step ) {
					$update['thread_id'] = $result;
				}
			}
			KB_DB::update_lead( $lead->id, $update );
		}

		// WhatsApp templates (max 10 a run).
		$wa = $wpdb->get_results( 'SELECT * FROM ' . KB_DB::t( 'messages' ) . " WHERE channel = 'whatsapp' AND direction = 'out' AND status = 'approved' ORDER BY id ASC LIMIT 10" );
		foreach ( $wa as $m ) {
			$lead = KB_DB::lead( $m->lead_id );
			if ( ! $lead || ! $lead->wa_opt_in || in_array( $lead->status, array( 'unsubscribed', 'not_interested' ), true ) ) {
				KB_DB::update_message( $m->id, array( 'status' => 'cancelled' ) );
				continue;
			}
			$result = $s['dry_run'] ? 'dry-run' : KB_WhatsApp::send( $lead );
			if ( is_wp_error( $result ) ) {
				KB_DB::update_message(
					$m->id,
					array(
						'status' => 'failed',
						'error'  => $result->get_error_message(),
					)
				);
				KB_Log::error( 'WhatsApp failed for ' . $lead->phone . ': ' . $result->get_error_message() );
				continue;
			}
			KB_DB::update_message(
				$m->id,
				array(
					'status'  => $s['dry_run'] ? 'simulated' : 'sent',
					'ext_id'  => $result,
					'sent_at' => KB_DB::now(),
				)
			);
			++$sent;
		}

		if ( $sent ) {
			KB_Log::add( $sent . ' messages ' . ( $s['dry_run'] ? 'simulated (dry run, nothing really sent)' : 'sent' ) . '.' );
		}
		return $sent;
	}

	/* ---------- 3. replies ---------- */

	/** Read new mail over IMAP. Returns how many lead replies were found. */
	public static function check_replies() {
		$s = KB_Settings::all();
		if ( ! $s['imap_host'] || ! $s['imap_user'] || ! $s['imap_pass'] ) {
			return 0;
		}
		$imap  = new KB_Imap();
		$found = 0;
		try {
			$imap->connect( $s['imap_host'], $s['imap_port'], $s['imap_user'], $s['imap_pass'] );
			$box   = $imap->select_inbox();
			$state = get_option( 'kb_imap_state', array() );
			if ( empty( $state['uidvalidity'] ) || (int) $state['uidvalidity'] !== $box['uidvalidity'] ) {
				// First run: start from now, do not read old mail.
				update_option(
					'kb_imap_state',
					array(
						'uidvalidity' => $box['uidvalidity'],
						'last_uid'    => max( 0, $box['uidnext'] - 1 ),
					),
					false
				);
				KB_Log::add( 'Inbox connected. The bot will read replies that arrive from now on.' );
				$imap->close();
				return 0;
			}
			foreach ( array_slice( $imap->uids_after( $state['last_uid'] ), 0, 30 ) as $uid ) {
				$msg = $imap->fetch( $uid );
				if ( $msg && self::handle_incoming( $msg ) ) {
					++$found;
				}
				$state['last_uid'] = $uid;
				update_option( 'kb_imap_state', $state, false );
			}
			$imap->close();
		} catch ( Throwable $e ) {
			KB_Log::error( 'Inbox check failed: ' . $e->getMessage() );
			$imap->close();
		}
		return $found;
	}

	/**
	 * Handle one incoming email. Public so it can be tested without a mailbox.
	 *
	 * @return bool True when it was from a lead.
	 */
	public static function handle_incoming( array $msg ) {
		global $wpdb;
		$s = KB_Settings::all();
		if ( ! $msg['from'] || strtolower( $s['sender_email'] ) === $msg['from'] ) {
			return false;
		}

		// Bounces.
		if ( preg_match( '/mailer-daemon|postmaster/i', $msg['from'] ) || preg_match( '/undeliver|delivery status|returned mail|failure notice|delivery has failed/i', $msg['subject'] ) ) {
			preg_match_all( '/[\w.+\-]+@[\w\-]+(\.[\w\-]+)+/', $msg['full_text'], $m );
			foreach ( array_unique( array_map( 'strtolower', $m[0] ) ) as $addr ) {
				$leads = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . KB_DB::t( 'leads' ) . " WHERE email = %s AND step > 0 AND status IN ('new','active')", $addr ) );
				foreach ( $leads as $lead ) {
					KB_DB::update_lead( $lead->id, array( 'status' => 'bounced' ) );
					KB_DB::cancel_pending( $lead->id, false );
					KB_DB::suppress( $addr, 'bounce' );
					KB_Log::add( 'Bounced: ' . $addr );
				}
			}
			return false;
		}

		// Match the sender to a lead we have written to.
		$lead = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . KB_DB::t( 'leads' ) . ' WHERE email = %s AND (step > 0 OR last_contact_at IS NOT NULL) ORDER BY last_contact_at DESC LIMIT 1', $msg['from'] ) );
		if ( ! $lead && ( $msg['in_reply_to'] || $msg['references'] ) ) {
			preg_match_all( '/<[^>]+>/', $msg['in_reply_to'] . ' ' . $msg['references'], $ids );
			foreach ( $ids[0] as $id ) {
				$lead_id = $wpdb->get_var( $wpdb->prepare( 'SELECT lead_id FROM ' . KB_DB::t( 'messages' ) . " WHERE ext_id = %s AND direction = 'out' LIMIT 1", $id ) );
				if ( $lead_id ) {
					$lead = KB_DB::lead( $lead_id );
					break;
				}
			}
		}
		if ( ! $lead ) {
			return false;
		}
		$c = KB_DB::campaign( $lead->campaign_id );

		$in_id = KB_DB::add_message(
			array(
				'lead_id'     => $lead->id,
				'campaign_id' => $lead->campaign_id,
				'channel'     => 'email',
				'direction'   => 'in',
				'kind'        => 'reply',
				'subject'     => mb_substr( $msg['subject'], 0, 250 ),
				'body'        => $msg['text'],
				'status'      => 'received',
				'ext_id'      => mb_substr( $msg['message_id'], 0, 190 ),
				'sent_at'     => KB_DB::now(),
			)
		);
		KB_DB::cancel_pending( $lead->id );
		KB_DB::update_lead(
			$lead->id,
			array(
				'status'          => 'replied',
				'last_contact_at' => KB_DB::now(),
			)
		);

		// Clear "stop" replies need no AI.
		if ( preg_match( '/^\s*(stop|unsubscribe|remove me|remove|opt out)\b/i', $msg['text'] ) ) {
			KB_DB::update_message(
				$in_id,
				array(
					'category' => 'unsubscribe',
					'summary'  => 'Asked to stop.',
				)
			);
			self::unsubscribe( $lead, 'reply' );
			return true;
		}

		if ( ! $c ) {
			return true;
		}
		try {
			$r = KB_AI::read_reply( $c, $lead, $msg['subject'], $msg['text'] );
		} catch ( Throwable $e ) {
			KB_Log::error( 'Could not read reply from ' . $lead->email . ': ' . $e->getMessage() );
			return true;
		}
		KB_DB::update_message(
			$in_id,
			array(
				'category' => $r['category'],
				'summary'  => $r['summary'],
			)
		);

		switch ( $r['category'] ) {
			case 'interested':
			case 'meeting_request':
				KB_DB::update_lead( $lead->id, array( 'status' => 'interested' ) );
				break;
			case 'not_interested':
				KB_DB::update_lead( $lead->id, array( 'status' => 'not_interested' ) );
				break;
			case 'unsubscribe':
				self::unsubscribe( $lead, 'reply' );
				return true;
			case 'out_of_office':
				KB_DB::update_lead(
					$lead->id,
					array(
						'status'         => 'active',
						'next_action_at' => self::later( 7 ),
					)
				);
				break;
		}

		if ( $r['reply_needed'] && '' !== $r['reply_body'] ) {
			$subject = preg_match( '/^re:/i', $msg['subject'] ) ? $msg['subject'] : 'Re: ' . $msg['subject'];
			KB_DB::add_message(
				array(
					'lead_id'     => $lead->id,
					'campaign_id' => $lead->campaign_id,
					'channel'     => 'email',
					'kind'        => 'reply',
					'subject'     => mb_substr( $subject, 0, 250 ),
					'body'        => $r['reply_body'],
					'status'      => 'draft', // Answers to real people always wait for a human.
					'in_reply_to' => mb_substr( $msg['message_id'], 0, 190 ),
				)
			);
		}
		KB_Log::add( 'Reply from ' . $lead->email . ': ' . ( KB_AI::REPLY_CATEGORIES[ $r['category'] ] ?? $r['category'] ) . '. ' . $r['summary'] );
		return true;
	}

	/* ---------- 4. housekeeping ---------- */

	private static function finish_sequences() {
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . KB_DB::t( 'leads' ) . ' l JOIN ' . KB_DB::t( 'campaigns' ) . " c ON c.id = l.campaign_id
				SET l.status = 'done' WHERE l.status = 'active' AND l.step >= c.steps AND l.next_action_at <= %s
				AND NOT EXISTS (SELECT 1 FROM " . KB_DB::t( 'messages' ) . " m WHERE m.lead_id = l.id AND m.status IN ('draft','approved'))",
				KB_DB::now()
			)
		);
	}

	private static function weekly_reports() {
		$s = KB_Settings::all();
		if ( ! $s['weekly_report'] || 1 !== (int) wp_date( 'N' ) || (int) wp_date( 'G' ) < 9 ) {
			return;
		}
		$week = wp_date( 'o-W' );
		if ( get_option( 'kb_report_week' ) === $week ) {
			return;
		}
		update_option( 'kb_report_week', $week, false );
		foreach ( KB_DB::campaigns() as $c ) {
			if ( 'active' !== $c->status || ! is_email( $c->client_email ) ) {
				continue;
			}
			if ( $s['dry_run'] ) {
				KB_Log::add( 'Weekly report for ' . $c->client_name . ' not emailed (dry run is on).' );
				continue;
			}
			$ok = KB_Mailer::send_plain( $c->client_email, 'Weekly outreach report: ' . $c->name, KB_Reports::text( $c, 7 ) );
			KB_Log::add( 'Weekly report to ' . $c->client_email . ( $ok ? ' sent.' : ' FAILED.' ) );
		}
	}
}
