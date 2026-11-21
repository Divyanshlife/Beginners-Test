<?php
/**
 * wp-admin screens for Kadvia Bot.
 *
 * @package kadvia-bot
 */

defined( 'ABSPATH' ) || exit;

class KB_Admin {

	const CAP = 'manage_options';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_kb_action', array( __CLASS__, 'handle' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	public static function assets( $hook ) {
		if ( str_contains( (string) $hook, 'kadvia-bot' ) || str_contains( (string) $hook, 'kb-' ) ) {
			wp_enqueue_style( 'kb-admin', KB_URL . 'admin/admin.css', array(), KB_VERSION );
			wp_enqueue_script( 'kb-admin', KB_URL . 'admin/admin.js', array(), KB_VERSION, true );
		}
	}

	public static function menu() {
		global $wpdb;
		$waiting = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . KB_DB::t( 'messages' ) . " WHERE status = 'draft' AND channel IN ('email','whatsapp') AND direction = 'out'" );
		$bubble  = $waiting ? ' <span class="awaiting-mod">' . $waiting . '</span>' : '';

		add_menu_page( 'Kadvia Bot', 'Kadvia Bot' . $bubble, self::CAP, 'kadvia-bot', array( __CLASS__, 'page_dashboard' ), 'dashicons-email-alt2', 3 );
		add_submenu_page( 'kadvia-bot', 'Dashboard', 'Dashboard', self::CAP, 'kadvia-bot', array( __CLASS__, 'page_dashboard' ) );
		add_submenu_page( 'kadvia-bot', 'Approve messages', 'Approve' . $bubble, self::CAP, 'kb-approvals', array( __CLASS__, 'page_approvals' ) );
		add_submenu_page( 'kadvia-bot', 'Replies', 'Replies', self::CAP, 'kb-replies', array( __CLASS__, 'page_replies' ) );
		add_submenu_page( 'kadvia-bot', 'LinkedIn and calls', 'LinkedIn & Calls', self::CAP, 'kb-tasks', array( __CLASS__, 'page_tasks' ) );
		add_submenu_page( 'kadvia-bot', 'Leads', 'Leads', self::CAP, 'kb-leads', array( __CLASS__, 'page_leads' ) );
		add_submenu_page( 'kadvia-bot', 'Campaigns', 'Campaigns', self::CAP, 'kb-campaigns', array( __CLASS__, 'page_campaigns' ) );
		add_submenu_page( 'kadvia-bot', 'Settings', 'Settings', self::CAP, 'kb-settings', array( __CLASS__, 'page_settings' ) );
		add_submenu_page( 'kadvia-bot', 'Activity log', 'Activity log', self::CAP, 'kb-log', array( __CLASS__, 'page_log' ) );
	}

	/* =========================================================
	 * Helpers
	 * ======================================================= */

	private static function url( $page, $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => $page ), $args ), admin_url( 'admin.php' ) );
	}

	private static function form_open( $do, $extra = '' ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" ' . $extra . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<input type="hidden" name="action" value="kb_action"><input type="hidden" name="do" value="' . esc_attr( $do ) . '">';
		wp_nonce_field( 'kb_action' );
	}

	private static function action_link( $do, $args, $label, $class = 'button', $confirm = '' ) {
		$url = wp_nonce_url( add_query_arg( array_merge( array( 'action' => 'kb_action', 'do' => $do ), $args ), admin_url( 'admin-post.php' ) ), 'kb_action' );
		$js  = $confirm ? ' onclick="return confirm(\'' . esc_js( $confirm ) . '\')"' : '';
		return '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '"' . $js . '>' . esc_html( $label ) . '</a>';
	}

	private static function notice( $type, $text ) {
		set_transient( 'kb_notice_' . get_current_user_id(), array( $type, $text ), 120 );
	}

	private static function show_notice() {
		$n = get_transient( 'kb_notice_' . get_current_user_id() );
		if ( $n ) {
			delete_transient( 'kb_notice_' . get_current_user_id() );
			echo '<div class="notice notice-' . esc_attr( $n[0] ) . ' is-dismissible"><p>' . nl2br( esc_html( $n[1] ) ) . '</p></div>';
		}
	}

	private static function header( $title ) {
		echo '<div class="wrap kb-wrap"><h1 class="kb-title"><span class="kb-logo">K</span> ' . esc_html( $title ) . '</h1>';
		self::show_notice();
		if ( KB_AI::is_demo() ) {
			echo '<div class="kb-banner kb-banner--warn"><strong>Demo mode is ON (free, no AI).</strong> Messages come from fixed templates and replies are sorted by simple keyword rules. Good for testing the flow; for real clients choose a Claude model in <a href="' . esc_url( self::url( 'kb-settings' ) ) . '">Settings</a>.</div>';
		}
		if ( KB_Settings::get( 'dry_run' ) ) {
			echo '<div class="kb-banner kb-banner--test"><strong>Test mode (dry run) is ON.</strong> The bot writes and "sends" messages only inside this dashboard. Nothing reaches real people. Turn it off in <a href="' . esc_url( self::url( 'kb-settings' ) ) . '">Settings</a> when you are ready.</div>';
		}
	}

	private static function footer() {
		echo '</div>';
	}

	private static function badge( $status, $label = '' ) {
		$label = $label ? $label : ( KB_DB::LEAD_STATUSES[ $status ] ?? ucfirst( str_replace( '_', ' ', $status ) ) );
		return '<span class="kb-badge kb-badge--' . esc_attr( $status ) . '">' . esc_html( $label ) . '</span>';
	}

	private static function lead_line( $lead ) {
		$name = KB_DB::full_name( $lead );
		$out  = '<a href="' . esc_url( self::url( 'kb-leads', array( 'lead' => $lead->id ) ) ) . '"><strong>' . esc_html( $name ? $name : $lead->email ) . '</strong></a>';
		$sub  = array_filter( array( $lead->title, $lead->company ) );
		if ( $sub ) {
			$out .= ' <span class="kb-muted">' . esc_html( implode( ', ', $sub ) ) . '</span>';
		}
		return $out;
	}

	private static function campaign_select( $name, $selected = 0, $any = false ) {
		$out = '<select name="' . esc_attr( $name ) . '">';
		if ( $any ) {
			$out .= '<option value="0">All campaigns</option>';
		}
		foreach ( KB_DB::campaigns() as $c ) {
			$out .= '<option value="' . (int) $c->id . '"' . selected( (int) $selected, (int) $c->id, false ) . '>' . esc_html( $c->name . ' (' . $c->client_name . ')' ) . '</option>';
		}
		return $out . '</select>';
	}

	/* =========================================================
	 * Dashboard
	 * ======================================================= */

	public static function page_dashboard() {
		global $wpdb;
		$s = KB_Settings::all();
		$M = KB_DB::t( 'messages' );
		self::header( 'Kadvia Bot' );

		$problems = KB_Settings::problems();
		if ( $problems ) {
			echo '<div class="kb-banner kb-banner--warn"><strong>Finish setup:</strong><ul>';
			foreach ( $problems as $p ) {
				echo '<li>' . esc_html( $p ) . '</li>';
			}
			echo '</ul></div>';
		}

		$drafts  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $M WHERE status = 'draft' AND channel IN ('email','whatsapp') AND direction = 'out'" );
		$queued  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $M WHERE status = 'approved'" );
		$tasks   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $M WHERE status = 'draft' AND channel IN ('linkedin','call')" );
		$replies = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $M WHERE direction = 'in' AND sent_at >= %s", wp_date( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS ) ) );
		$meet    = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . KB_DB::t( 'leads' ) . " WHERE status = 'meeting'" );
		$failed  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $M WHERE status = 'failed'" );

		echo '<div class="kb-cards">';
		self::card( 'Emails sent today', self::sent_label( $s ) );
		self::card( 'Waiting for your OK', $drafts, self::url( 'kb-approvals' ) );
		self::card( 'Approved, in queue', $queued );
		self::card( 'LinkedIn & call tasks', $tasks, self::url( 'kb-tasks' ) );
		self::card( 'Replies (7 days)', $replies, self::url( 'kb-replies' ) );
		self::card( 'Meetings booked', $meet, self::url( 'kb-leads', array( 'status' => 'meeting' ) ), true );
		echo '</div>';

		if ( $failed ) {
			echo '<div class="kb-banner kb-banner--warn">' . (int) $failed . ' message(s) failed to send. See <a href="' . esc_url( self::url( 'kb-approvals' ) ) . '">Approve</a> to retry, and the <a href="' . esc_url( self::url( 'kb-log' ) ) . '">Activity log</a> for the reason.</div>';
		}

		$last = get_option( 'kb_last_run' );
		$next = wp_next_scheduled( KB_Engine::HOOK );
		echo '<div class="kb-panel kb-run"><div><strong>Last run:</strong> ' . esc_html( $last ? $last['t'] . ' — ' . $last['s'] : 'not yet' ) . '<br><span class="kb-muted">Runs every 10 minutes by itself' . ( $next ? ' (next ' . esc_html( wp_date( 'H:i', $next ) ) . ')' : '' ) . '. Sending window: ' . (int) $s['window_start'] . ':00 to ' . (int) $s['window_end'] . ':00' . ( $s['skip_weekends'] ? ', weekdays' : '' ) . ( KB_Engine::in_window() ? ' (open now)' : ' (closed now)' ) . '.</span></div>';
		self::form_open( 'run_now' );
		submit_button( 'Run the bot now', 'primary', 'submit', false );
		echo '</form></div>';

		$campaigns = KB_DB::campaigns();
		echo '<h2>Campaigns</h2>';
		if ( ! $campaigns ) {
			echo '<div class="kb-panel kb-steps"><h3>Get started in 5 steps</h3><ol>'
				. '<li>Open <a href="' . esc_url( self::url( 'kb-settings' ) ) . '">Settings</a>: add the Claude API key, sender email and address. Keep test mode on.</li>'
				. '<li>Create a <a href="' . esc_url( self::url( 'kb-campaigns', array( 'edit' => 'new' ) ) ) . '">campaign</a> for a client: what they sell and who to target.</li>'
				. '<li>Import leads on the <a href="' . esc_url( self::url( 'kb-leads' ) ) . '">Leads</a> page (CSV from Apollo or Excel).</li>'
				. '<li>Set the campaign to Active and press "Run the bot now". Read and approve the drafts.</li>'
				. '<li>When the drafts look right, connect SMTP + IMAP and turn test mode off.</li>'
				. '</ol></div>';
		} else {
			echo '<table class="widefat striped kb-table"><thead><tr><th>Campaign</th><th>Status</th><th>Leads</th><th>Contacted</th><th>Replies</th><th>Interested</th><th>Meetings</th><th></th></tr></thead><tbody>';
			foreach ( $campaigns as $c ) {
				$st = KB_Reports::stats( $c );
				echo '<tr><td><strong>' . esc_html( $c->name ) . '</strong><br><span class="kb-muted">' . esc_html( $c->client_name ) . '</span></td><td>' . self::badge( $c->status, ucfirst( $c->status ) ) . '</td><td>' . (int) $st['leads'] . '</td><td>' . (int) $st['contacted'] . '</td><td>' . (int) $st['replies'] . '</td><td>' . (int) $st['positive'] . '</td><td><strong>' . (int) $st['meetings'] . '</strong></td><td><a class="button" href="' . esc_url( self::url( 'kb-campaigns', array( 'report' => $c->id ) ) ) . '">Report</a></td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
			}
			echo '</tbody></table>';
		}
		self::footer();
	}

	private static function sent_label( $s ) {
		return KB_Engine::sent_today( 'email' ) . ' / ' . (int) $s['daily_limit'];
	}

	private static function card( $label, $value, $link = '', $hot = false ) {
		$tag = $link ? 'a href="' . esc_url( $link ) . '"' : 'div';
		echo '<' . $tag . ' class="kb-card' . ( $hot ? ' kb-card--hot' : '' ) . '"><span class="kb-card__n">' . esc_html( (string) $value ) . '</span><span class="kb-card__l">' . esc_html( $label ) . '</span></' . ( $link ? 'a' : 'div' ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/* =========================================================
	 * Approvals
	 * ======================================================= */

	public static function page_approvals() {
		global $wpdb;
		$M = KB_DB::t( 'messages' );
		self::header( 'Approve messages' );
		echo '<p class="kb-lead">Read each message. Edit if needed, then approve. Approved messages go out on the next run inside the sending window. Replies to real people always wait here for you.</p>';

		$failed = $wpdb->get_results( "SELECT * FROM $M WHERE status = 'failed' ORDER BY id DESC LIMIT 20" );
		if ( $failed ) {
			echo '<h2>Failed to send</h2><table class="widefat striped kb-table"><tbody>';
			foreach ( $failed as $m ) {
				$lead = KB_DB::lead( $m->lead_id );
				echo '<tr><td>' . ( $lead ? self::lead_line( $lead ) : '' ) . '<br><span class="kb-muted">' . esc_html( $m->subject ) . '</span></td><td class="kb-err">' . esc_html( $m->error ) . '</td><td>' . self::action_link( 'msg_retry', array( 'id' => $m->id ), 'Retry' ) . ' ' . self::action_link( 'msg_skip', array( 'id' => $m->id ), 'Drop', 'button-link' ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
			}
			echo '</tbody></table>';
		}

		$drafts = $wpdb->get_results( "SELECT * FROM $M WHERE status = 'draft' AND direction = 'out' AND channel IN ('email','whatsapp') ORDER BY (kind = 'reply') DESC, id ASC LIMIT 40" );
		if ( ! $drafts ) {
			echo '<div class="kb-panel kb-empty">Nothing waiting. The bot writes new drafts on each run for leads that are due.</div>';
			self::footer();
			return;
		}

		$ids = wp_list_pluck( $drafts, 'id' );
		self::form_open( 'approve_many', 'class="kb-bulk"' );
		echo '<input type="hidden" name="ids" value="' . esc_attr( implode( ',', $ids ) ) . '">';
		submit_button( 'Approve all ' . count( $ids ) . ' shown below', 'primary', 'submit', false, array( 'onclick' => "return confirm('Approve all messages on this page as they are?')" ) );
		echo '</form>';

		foreach ( $drafts as $m ) {
			$lead = KB_DB::lead( $m->lead_id );
			$c    = KB_DB::campaign( $m->campaign_id );
			if ( ! $lead ) {
				continue;
			}
			$label = 'reply' === $m->kind ? 'Answer to a reply' : ( 'whatsapp' === $m->channel ? 'WhatsApp template' : 'Email ' . (int) $m->step . ' of ' . (int) ( $c ? $c->steps : 0 ) );
			echo '<div class="kb-msg kb-msg--' . esc_attr( 'reply' === $m->kind ? 'reply' : $m->channel ) . '">';
			echo '<div class="kb-msg__head"><span class="kb-pill">' . esc_html( $label ) . '</span> ' . self::lead_line( $lead ) . ' <span class="kb-muted">&lt;' . esc_html( 'whatsapp' === $m->channel ? $lead->phone : $lead->email ) . '&gt;' . ( $c ? ' · ' . esc_html( $c->name ) : '' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
			if ( 'risky' === $lead->email_status && 'email' === $m->channel ) {
				echo ' <span class="kb-badge kb-badge--risky">shared inbox</span>';
			}
			echo '</div>';

			if ( 'reply' === $m->kind ) {
				$in = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $M WHERE lead_id = %d AND direction = 'in' ORDER BY id DESC LIMIT 1", $lead->id ) );
				if ( $in ) {
					echo '<div class="kb-quote"><div class="kb-quote__h">They wrote ' . ( $in->category ? self::badge( $in->category, KB_AI::REPLY_CATEGORIES[ $in->category ] ?? $in->category ) : '' ) . ' <span class="kb-muted">' . esc_html( $in->summary ) . '</span></div>' . nl2br( esc_html( mb_substr( $in->body, 0, 1500 ) ) ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
				}
			}

			self::form_open( 'msg_save' );
			echo '<input type="hidden" name="id" value="' . (int) $m->id . '">';
			if ( 'email' === $m->channel ) {
				echo '<input type="text" class="kb-subject" name="subject" value="' . esc_attr( $m->subject ) . '">';
				echo '<textarea name="body" rows="' . (int) min( 16, max( 6, substr_count( $m->body, "\n" ) + 3 ) ) . '">' . esc_textarea( $m->body ) . '</textarea>';
				echo '<p class="kb-muted kb-small">Your signature, address and unsubscribe link are added below this text when it is sent.</p>';
			} else {
				echo '<pre class="kb-pre">' . esc_html( $m->body ) . '</pre>';
			}
			echo '<div class="kb-actions">';
			echo '<button class="button button-primary" name="approve" value="1">Approve</button> ';
			if ( 'email' === $m->channel ) {
				echo '<button class="button" name="approve" value="0">Save edits</button> ';
			}
			echo '</div></form><div class="kb-actions kb-actions--sub">';
			if ( 'sequence' === $m->kind && 'email' === $m->channel ) {
				echo self::action_link( 'msg_rewrite', array( 'id' => $m->id ), 'Rewrite with AI', 'button' ) . ' '; // phpcs:ignore WordPress.Security.EscapeOutput
			}
			echo self::action_link( 'msg_skip', array( 'id' => $m->id ), 'reply' === $m->kind ? 'Do not answer' : 'Skip this lead', 'button-link kb-danger' ); // phpcs:ignore WordPress.Security.EscapeOutput
			echo '</div></div>';
		}
		self::footer();
	}

	/* =========================================================
	 * Replies
	 * ======================================================= */

	public static function page_replies() {
		global $wpdb;
		self::header( 'Replies' );
		$cat  = isset( $_GET['cat'] ) ? sanitize_key( $_GET['cat'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$sql  = 'SELECT * FROM ' . KB_DB::t( 'messages' ) . " WHERE direction = 'in'";
		$sql .= $cat ? $wpdb->prepare( ' AND category = %s', $cat ) : '';
		$rows = $wpdb->get_results( $sql . ' ORDER BY id DESC LIMIT 100' );

		echo '<p class="kb-filters"><a href="' . esc_url( self::url( 'kb-replies' ) ) . '"' . ( $cat ? '' : ' class="current"' ) . '>All</a>';
		foreach ( KB_AI::REPLY_CATEGORIES as $k => $v ) {
			echo ' | <a href="' . esc_url( self::url( 'kb-replies', array( 'cat' => $k ) ) ) . '"' . ( $cat === $k ? ' class="current"' : '' ) . '>' . esc_html( $v ) . '</a>';
		}
		echo '</p>';

		if ( ! $rows ) {
			echo '<div class="kb-panel kb-empty">No replies yet. Replies appear here after the bot reads your inbox (Settings → Reading replies).</div>';
			self::footer();
			return;
		}
		foreach ( $rows as $m ) {
			$lead = KB_DB::lead( $m->lead_id );
			if ( ! $lead ) {
				continue;
			}
			echo '<div class="kb-msg kb-msg--in"><div class="kb-msg__head">' . ( $m->category ? self::badge( $m->category, KB_AI::REPLY_CATEGORIES[ $m->category ] ?? $m->category ) : '' ) . ' ' . self::lead_line( $lead ) . ' <span class="kb-muted">' . esc_html( $m->sent_at ) . '</span> ' . self::badge( $lead->status ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
			if ( $m->summary ) {
				echo '<p><strong>' . esc_html( $m->summary ) . '</strong></p>';
			}
			echo '<div class="kb-quote">' . nl2br( esc_html( mb_substr( $m->body, 0, 2000 ) ) ) . '</div>';
			echo '<div class="kb-actions">';
			if ( 'meeting' !== $lead->status ) {
				echo self::action_link( 'lead_status', array( 'id' => $lead->id, 'status' => 'meeting' ), 'Meeting booked', 'button button-primary' ) . ' '; // phpcs:ignore WordPress.Security.EscapeOutput
			}
			echo self::action_link( 'lead_status', array( 'id' => $lead->id, 'status' => 'not_interested' ), 'Not interested', 'button' ) . ' '; // phpcs:ignore WordPress.Security.EscapeOutput
			echo '<a class="button-link" href="' . esc_url( self::url( 'kb-leads', array( 'lead' => $lead->id ) ) ) . '">Full conversation</a></div></div>';
		}
		self::footer();
	}

	/* =========================================================
	 * LinkedIn & call tasks
	 * ======================================================= */

	public static function page_tasks() {
		global $wpdb;
		self::header( 'LinkedIn & Calls' );
		echo '<p class="kb-lead">LinkedIn does not allow bots to send messages, and calls need a person, so the bot prepares everything and you do the last click. Copy, send or call, then mark the result.</p>';
		$rows = $wpdb->get_results( 'SELECT * FROM ' . KB_DB::t( 'messages' ) . " WHERE status = 'draft' AND channel IN ('linkedin','call') ORDER BY channel, id ASC LIMIT 60" );
		if ( ! $rows ) {
			echo '<div class="kb-panel kb-empty">No open tasks. Turn on LinkedIn or Call in a campaign, and import leads with a LinkedIn URL or phone number.</div>';
			self::footer();
			return;
		}
		foreach ( $rows as $m ) {
			$lead = KB_DB::lead( $m->lead_id );
			if ( ! $lead ) {
				continue;
			}
			echo '<div class="kb-msg kb-msg--' . esc_attr( $m->channel ) . '"><div class="kb-msg__head"><span class="kb-pill">' . ( 'call' === $m->channel ? 'Call' : 'LinkedIn' ) . '</span> ' . self::lead_line( $lead ) . ' ' . self::badge( $lead->status ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
			echo '<p>';
			if ( 'linkedin' === $m->channel && $lead->linkedin_url ) {
				echo '<a class="button" target="_blank" rel="noopener" href="' . esc_url( $lead->linkedin_url ) . '">Open LinkedIn profile ↗</a> ';
			}
			if ( 'call' === $m->channel && $lead->phone ) {
				echo '<a class="button" href="' . esc_url( 'tel:' . $lead->phone ) . '">Call ' . esc_html( $lead->phone ) . '</a> ';
			}
			echo '<button type="button" class="button kb-copy" data-target="kb-t-' . (int) $m->id . '">Copy text</button></p>';
			echo '<pre class="kb-pre" id="kb-t-' . (int) $m->id . '">' . esc_html( $m->body ) . '</pre>';
			self::form_open( 'task_done' );
			echo '<input type="hidden" name="id" value="' . (int) $m->id . '"><div class="kb-actions"><label>Result: <select name="outcome">';
			$opts = 'call' === $m->channel
				? array( 'done' => 'Spoke, follow up later', 'no_answer' => 'No answer', 'meeting' => 'Meeting booked', 'not_interested' => 'Not interested' )
				: array( 'done' => 'Sent connection request', 'meeting' => 'Meeting booked', 'not_interested' => 'Not interested' );
			foreach ( $opts as $k => $v ) {
				echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $v ) . '</option>';
			}
			echo '</select></label> <button class="button button-primary">Mark done</button></div></form></div>';
		}
		self::footer();
	}

	/* =========================================================
	 * Leads
	 * ======================================================= */

	public static function page_leads() {
		global $wpdb;
		// phpcs:disable WordPress.Security.NonceVerification
		if ( ! empty( $_GET['lead'] ) ) {
			self::lead_detail( absint( $_GET['lead'] ) );
			return;
		}
		self::header( 'Leads' );
		$campaigns = KB_DB::campaigns();
		if ( ! $campaigns ) {
			echo '<div class="kb-panel kb-empty">Create a <a href="' . esc_url( self::url( 'kb-campaigns', array( 'edit' => 'new' ) ) ) . '">campaign</a> first. Leads always belong to a campaign.</div>';
			self::footer();
			return;
		}

		echo '<div class="kb-two">';
		echo '<details class="kb-panel"><summary><strong>Import CSV</strong> (Apollo, Excel, Google Sheets)</summary>';
		self::form_open( 'import_csv', 'enctype="multipart/form-data"' );
		echo '<p>Campaign: ' . self::campaign_select( 'campaign_id' ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<p><input type="file" name="csv" accept=".csv,text/csv" required></p>';
		echo '<p class="kb-muted kb-small">Columns the bot understands: First Name, Last Name, Email, Company, Title, Phone, LinkedIn URL, Website, Industry, City, Notes, WhatsApp opt-in (yes/no). Apollo exports work as they are. In Excel use File → Save As → CSV.</p>';
		submit_button( 'Import', 'primary', 'submit', false );
		echo '</form></details>';

		echo '<details class="kb-panel"><summary><strong>Add one lead</strong></summary>';
		self::form_open( 'add_lead' );
		echo '<p>Campaign: ' . self::campaign_select( 'campaign_id' ) . '</p><div class="kb-grid">'; // phpcs:ignore WordPress.Security.EscapeOutput
		foreach ( array( 'first_name' => 'First name', 'last_name' => 'Last name', 'email' => 'Email', 'company' => 'Company', 'title' => 'Job title', 'phone' => 'Phone (with country code)', 'linkedin_url' => 'LinkedIn URL', 'website' => 'Website', 'industry' => 'Industry', 'city' => 'City' ) as $k => $v ) {
			echo '<label>' . esc_html( $v ) . '<input type="text" name="' . esc_attr( $k ) . '"></label>';
		}
		echo '</div><label>Notes (anything personal and true: a recent post, an event, a hiring signal)<textarea name="notes" rows="2"></textarea></label>';
		echo '<label class="kb-check"><input type="checkbox" name="wa_opt_in" value="yes"> This person agreed to get WhatsApp messages from us</label>';
		submit_button( 'Add lead', 'primary', 'submit', false );
		echo '</form></details></div>';

		$cid    = absint( $_GET['campaign'] ?? 0 );
		$status = sanitize_key( $_GET['status'] ?? '' );
		$q      = sanitize_text_field( wp_unslash( $_GET['q'] ?? '' ) );
		$paged  = max( 1, absint( $_GET['paged'] ?? 1 ) );
		// phpcs:enable

		$where = array( '1=1' );
		if ( $cid ) {
			$where[] = $wpdb->prepare( 'campaign_id = %d', $cid );
		}
		if ( $status ) {
			$where[] = $wpdb->prepare( 'status = %s', $status );
		}
		if ( $q ) {
			$like    = '%' . $wpdb->esc_like( $q ) . '%';
			$where[] = $wpdb->prepare( '(email LIKE %s OR company LIKE %s OR first_name LIKE %s OR last_name LIKE %s)', $like, $like, $like, $like );
		}
		$w     = implode( ' AND ', $where );
		$L     = KB_DB::t( 'leads' );
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $L WHERE $w" );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $L WHERE $w ORDER BY id DESC LIMIT 50 OFFSET %d", ( $paged - 1 ) * 50 ) );

		echo '<form method="get" class="kb-filterbar"><input type="hidden" name="page" value="kb-leads">' . self::campaign_select( 'campaign', $cid, true ) . ' <select name="status"><option value="">All statuses</option>'; // phpcs:ignore WordPress.Security.EscapeOutput
		foreach ( KB_DB::LEAD_STATUSES as $k => $v ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $status, $k, false ) . '>' . esc_html( $v ) . '</option>';
		}
		echo '</select> <input type="search" name="q" value="' . esc_attr( $q ) . '" placeholder="Name, email or company"> <button class="button">Filter</button> <span class="kb-muted">' . (int) $total . ' leads</span>';
		if ( $cid ) {
			echo ' ' . self::action_link( 'export_csv', array( 'campaign' => $cid ), 'Export CSV', 'button' ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</form>';

		echo '<table class="widefat striped kb-table"><thead><tr><th>Lead</th><th>Contact</th><th>Status</th><th>Step</th><th>Last contact</th></tr></thead><tbody>';
		if ( ! $rows ) {
			echo '<tr><td colspan="5">No leads found.</td></tr>';
		}
		foreach ( $rows as $l ) {
			$check = array(
				'valid'   => '✓',
				'risky'   => '!',
				'invalid' => '✗',
				'unknown' => '…',
				'none'    => '',
			);
			echo '<tr><td>' . self::lead_line( $l ) . '</td><td>' . esc_html( $l->email ) . ' <span class="kb-check-' . esc_attr( $l->email_status ) . '" title="' . esc_attr( 'Email check: ' . $l->email_status ) . '">' . esc_html( $check[ $l->email_status ] ?? '' ) . '</span>' . ( $l->phone ? '<br><span class="kb-muted">' . esc_html( $l->phone ) . ( $l->wa_opt_in ? ' · WhatsApp OK' : '' ) . '</span>' : '' ) . '</td><td>' . self::badge( $l->status ) . '</td><td>' . (int) $l->step . '</td><td>' . esc_html( $l->last_contact_at ? $l->last_contact_at : '—' ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</tbody></table>';
		$pages = (int) ceil( $total / 50 );
		if ( $pages > 1 ) {
			echo '<p class="kb-pages">';
			for ( $i = 1; $i <= $pages; $i++ ) {
				echo $i === $paged ? '<strong>' . (int) $i . '</strong> ' : '<a href="' . esc_url( add_query_arg( 'paged', $i ) ) . '">' . (int) $i . '</a> ';
			}
			echo '</p>';
		}
		self::footer();
	}

	private static function lead_detail( $id ) {
		$lead = KB_DB::lead( $id );
		self::header( 'Lead' );
		if ( ! $lead ) {
			echo '<p>Lead not found.</p>';
			self::footer();
			return;
		}
		$c = KB_DB::campaign( $lead->campaign_id );
		echo '<p><a href="' . esc_url( self::url( 'kb-leads' ) ) . '">← All leads</a></p>';
		echo '<div class="kb-panel"><h2>' . esc_html( KB_DB::full_name( $lead ) ) . ' ' . self::badge( $lead->status ) . '</h2><table class="kb-kv">'; // phpcs:ignore WordPress.Security.EscapeOutput
		foreach ( array( 'Campaign' => $c ? $c->name : '', 'Title' => $lead->title, 'Company' => $lead->company, 'Email' => $lead->email . ' (' . $lead->email_status . ')', 'Phone' => $lead->phone . ( $lead->wa_opt_in ? ' · WhatsApp opt-in' : '' ), 'LinkedIn' => $lead->linkedin_url, 'Website' => $lead->website, 'Industry' => $lead->industry, 'City' => $lead->city, 'Notes' => $lead->notes, 'Sequence step' => $lead->step . ( $c ? ' of ' . $c->steps : '' ), 'Next action' => $lead->next_action_at && $lead->next_action_at < '2090' ? $lead->next_action_at : '—' ) as $k => $v ) {
			if ( '' !== trim( (string) $v ) ) {
				echo '<tr><th>' . esc_html( $k ) . '</th><td>' . esc_html( $v ) . '</td></tr>';
			}
		}
		echo '</table><div class="kb-actions">';
		foreach ( array( 'meeting' => 'Meeting booked', 'interested' => 'Interested', 'not_interested' => 'Not interested', 'unsubscribed' => 'Unsubscribe', 'active' => 'Restart sequence' ) as $st => $label ) {
			if ( $st !== $lead->status ) {
				echo self::action_link( 'lead_status', array( 'id' => $lead->id, 'status' => $st ), $label, 'meeting' === $st ? 'button button-primary' : 'button' ) . ' '; // phpcs:ignore WordPress.Security.EscapeOutput
			}
		}
		echo self::action_link( 'lead_wa', array( 'id' => $lead->id ), $lead->wa_opt_in ? 'Remove WhatsApp opt-in' : 'Mark WhatsApp opt-in', 'button' ) . ' '; // phpcs:ignore WordPress.Security.EscapeOutput
		echo self::action_link( 'lead_delete', array( 'id' => $lead->id ), 'Delete lead', 'button-link kb-danger', 'Delete this lead and its messages?' ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '</div></div><h2>Conversation</h2>';

		$thread = KB_DB::thread( $lead->id );
		if ( ! $thread ) {
			echo '<div class="kb-panel kb-empty">Nothing yet.</div>';
		}
		foreach ( $thread as $m ) {
			$cls = 'in' === $m->direction ? 'in' : $m->channel;
			echo '<div class="kb-msg kb-msg--' . esc_attr( $cls ) . '"><div class="kb-msg__head"><span class="kb-pill">' . esc_html( ucfirst( $m->channel ) . ( 'in' === $m->direction ? ' from lead' : '' ) ) . '</span> ' . self::badge( $m->status, $m->status ) . ' <strong>' . esc_html( $m->subject ) . '</strong> <span class="kb-muted">' . esc_html( $m->sent_at ? $m->sent_at : $m->created_at ) . '</span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
			if ( $m->summary ) {
				echo '<p><em>' . esc_html( $m->summary ) . '</em></p>';
			}
			echo '<div class="kb-quote">' . nl2br( esc_html( $m->body ) ) . '</div>';
			if ( $m->error ) {
				echo '<p class="kb-err">' . esc_html( $m->error ) . '</p>';
			}
			echo '</div>';
		}
		self::footer();
	}

	/* =========================================================
	 * Campaigns
	 * ======================================================= */

	public static function page_campaigns() {
		// phpcs:disable WordPress.Security.NonceVerification
		if ( ! empty( $_GET['report'] ) ) {
			self::campaign_report( absint( $_GET['report'] ) );
			return;
		}
		if ( ! empty( $_GET['edit'] ) ) {
			self::campaign_form( 'new' === $_GET['edit'] ? null : KB_DB::campaign( absint( $_GET['edit'] ) ) );
			return;
		}
		// phpcs:enable
		self::header( 'Campaigns' );
		echo '<p><a class="button button-primary" href="' . esc_url( self::url( 'kb-campaigns', array( 'edit' => 'new' ) ) ) . '">+ New campaign</a></p>';
		$list = KB_DB::campaigns();
		if ( ! $list ) {
			echo '<div class="kb-panel kb-empty">One campaign = one client + one type of buyer. Example: "Acme HR software → HR heads at 200-1000 people IT companies in Pune".</div>';
		} else {
			echo '<table class="widefat striped kb-table"><thead><tr><th>Campaign</th><th>Client</th><th>Channels</th><th>Steps</th><th>Status</th><th></th></tr></thead><tbody>';
			foreach ( $list as $c ) {
				echo '<tr><td><strong>' . esc_html( $c->name ) . '</strong></td><td>' . esc_html( $c->client_name ) . '</td><td>' . esc_html( str_replace( ',', ', ', $c->channels ) ) . '</td><td>' . (int) $c->steps . ' emails, every ' . (int) $c->gap_days . ' days</td><td>' . self::badge( $c->status, ucfirst( $c->status ) ) . '</td><td>'; // phpcs:ignore WordPress.Security.EscapeOutput
				echo '<a class="button" href="' . esc_url( self::url( 'kb-campaigns', array( 'edit' => $c->id ) ) ) . '">Edit</a> ';
				echo 'active' === $c->status ? self::action_link( 'campaign_status', array( 'id' => $c->id, 'status' => 'paused' ), 'Pause' ) : self::action_link( 'campaign_status', array( 'id' => $c->id, 'status' => 'active' ), 'Start', 'button button-primary' ); // phpcs:ignore WordPress.Security.EscapeOutput
				echo ' <a class="button-link" href="' . esc_url( self::url( 'kb-campaigns', array( 'report' => $c->id ) ) ) . '">Report</a></td></tr>';
			}
			echo '</tbody></table>';
		}
		self::footer();
	}

	private static function campaign_form( $c ) {
		self::header( $c ? 'Edit campaign' : 'New campaign' );
		$v = static function ( $k, $d = '' ) use ( $c ) {
			return $c ? $c->$k : $d;
		};
		$channels = $c ? KB_DB::channels( $c ) : array( 'email' );
		self::form_open( 'save_campaign', 'class="kb-form"' );
		echo '<input type="hidden" name="id" value="' . (int) ( $c ? $c->id : 0 ) . '">';
		echo '<div class="kb-panel"><h2>Client</h2><div class="kb-grid">';
		echo '<label>Campaign name<input type="text" name="name" required value="' . esc_attr( $v( 'name' ) ) . '" placeholder="Acme → HR heads, Pune IT"></label>';
		echo '<label>Client company<input type="text" name="client_name" required value="' . esc_attr( $v( 'client_name' ) ) . '" placeholder="Acme HR Software"></label>';
		echo '<label>Client email (gets the weekly report)<input type="email" name="client_email" value="' . esc_attr( $v( 'client_email' ) ) . '"></label>';
		echo '<label>Booking link (Calendly, Google Calendar)<input type="url" name="calendly_url" value="' . esc_attr( $v( 'calendly_url' ) ) . '" placeholder="https://calendly.com/client/15min"></label>';
		echo '</div></div>';

		echo '<div class="kb-panel"><h2>What the AI needs to know</h2>';
		echo '<label>What does the client sell? What problem does it solve?<textarea name="offer" rows="4" required placeholder="HR software that runs payroll, attendance and leave in one app for Indian companies. Saves HR teams 2 days a month on payroll.">' . esc_textarea( $v( 'offer' ) ) . '</textarea></label>';
		echo '<label>Who are we targeting?<textarea name="icp" rows="3" required placeholder="HR heads and founders at IT services companies with 200-1000 employees in Pune and Bangalore.">' . esc_textarea( $v( 'icp' ) ) . '</textarea></label>';
		echo '<label>Proof points the AI may use (real results, clients, numbers). Leave empty if you have none: the AI will not invent any.<textarea name="proof" rows="3" placeholder="Used by 120 companies. Example: XYZ Tech cut payroll time from 3 days to 4 hours.">' . esc_textarea( $v( 'proof' ) ) . '</textarea></label>';
		echo '<div class="kb-grid"><label>Tone and language<input type="text" name="tone" value="' . esc_attr( $v( 'tone', 'friendly, direct, simple English' ) ) . '"></label>';
		echo '<label>Signature (added under every email)<textarea name="signature" rows="3" required placeholder="Divyansh&#10;Growth Partner, Acme HR Software">' . esc_textarea( $v( 'signature' ) ) . '</textarea></label></div>';
		echo '</div>';

		echo '<div class="kb-panel"><h2>Sequence</h2><div class="kb-grid">';
		echo '<label>Number of emails<select name="steps">';
		for ( $i = 1; $i <= 6; $i++ ) {
			echo '<option' . selected( (int) $v( 'steps', 3 ), $i, false ) . '>' . (int) $i . '</option>';
		}
		echo '</select></label><label>Days between emails<select name="gap_days">';
		foreach ( array( 1, 2, 3, 4, 5, 7, 10, 14 ) as $d ) {
			echo '<option' . selected( (int) $v( 'gap_days', 3 ), $d, false ) . '>' . (int) $d . '</option>';
		}
		echo '</select></label></div><p><strong>Channels</strong></p>';
		foreach ( array( 'email' => 'Email (automatic)', 'linkedin' => 'LinkedIn (bot writes, you send)', 'call' => 'Calls (bot writes the script, you call)', 'whatsapp' => 'WhatsApp template (only leads who opted in)' ) as $k => $label ) {
			echo '<label class="kb-check"><input type="checkbox" name="channels[]" value="' . esc_attr( $k ) . '"' . checked( in_array( $k, $channels, true ), true, false ) . '> ' . esc_html( $label ) . '</label>';
		}
		echo '<p><label class="kb-check"><input type="checkbox" name="status" value="active"' . checked( $v( 'status' ), 'active', false ) . '> <strong>Active</strong> (the bot works on this campaign)</label></p>';
		echo '</div>';
		submit_button( 'Save campaign' );
		echo '</form>';
		self::footer();
	}

	private static function campaign_report( $id ) {
		$c = KB_DB::campaign( $id );
		self::header( 'Report' );
		if ( ! $c ) {
			echo '<p>Not found.</p>';
			self::footer();
			return;
		}
		echo '<p><a href="' . esc_url( self::url( 'kb-campaigns' ) ) . '">← Campaigns</a></p><div class="kb-panel"><pre class="kb-pre kb-report">' . esc_html( KB_Reports::text( $c, 7 ) ) . '</pre>';
		echo '<div class="kb-actions">';
		if ( is_email( $c->client_email ) ) {
			echo self::action_link( 'send_report', array( 'id' => $c->id ), 'Email this to ' . $c->client_email, 'button button-primary', 'Send this report to the client now?' ) . ' '; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo self::action_link( 'export_csv', array( 'campaign' => $c->id ), 'Export leads CSV', 'button' ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '</div></div>';
		self::footer();
	}

	/* =========================================================
	 * Settings
	 * ======================================================= */

	public static function page_settings() {
		$s = KB_Settings::all();
		self::header( 'Settings' );
		self::form_open( 'save_settings', 'class="kb-form" autocomplete="off"' );

		$secret = static function ( $key, $label, $help = '' ) use ( $s ) {
			if ( KB_Settings::is_constant( $key ) ) {
				echo '<label>' . esc_html( $label ) . '<input type="text" disabled value="Set in wp-config.php"></label>';
				return;
			}
			echo '<label>' . esc_html( $label ) . '<input type="password" name="' . esc_attr( $key ) . '" value="" placeholder="' . esc_attr( $s[ $key ] ? '•••••••• saved (leave empty to keep)' : '' ) . '" autocomplete="new-password">' . ( $help ? '<small>' . wp_kses_post( $help ) . '</small>' : '' ) . '</label>';
		};
		$text = static function ( $key, $label, $help = '', $type = 'text' ) use ( $s ) {
			echo '<label>' . esc_html( $label ) . '<input type="' . esc_attr( $type ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $s[ $key ] ) . '">' . ( $help ? '<small>' . wp_kses_post( $help ) . '</small>' : '' ) . '</label>';
		};
		$check = static function ( $key, $label ) use ( $s ) {
			echo '<label class="kb-check"><input type="checkbox" name="' . esc_attr( $key ) . '" value="1"' . checked( $s[ $key ], 1, false ) . '> ' . esc_html( $label ) . '</label>';
		};

		echo '<div class="kb-panel"><h2>1. Claude (the AI writer)</h2><div class="kb-grid">';
		$secret( 'api_key', 'Claude API key', 'Create one at <a href="https://platform.claude.com" target="_blank" rel="noopener">platform.claude.com</a> → API keys. Add a little credit (Billing) first.' );
		echo '<label>Model<select name="model">';
		foreach ( KB_Settings::MODELS as $k => $v ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $s['model'], $k, false ) . '>' . esc_html( $v ) . '</option>';
		}
		echo '</select><small>No API key yet? Choose <strong>Demo mode</strong> to test everything for free with ready-made templates.</small></label></div></div>';

		echo '<div class="kb-panel"><h2>2. Email sending</h2><div class="kb-grid">';
		$text( 'sender_name', 'Sender name', 'The person the email comes from, e.g. "Divyansh from Kadvia".' );
		$text( 'sender_email', 'Sender email', 'Use a separate outreach domain (e.g. you@trykadvia.com), not your main domain.', 'email' );
		echo '<label>Business postal address<textarea name="business_address" rows="2">' . esc_textarea( $s['business_address'] ) . '</textarea><small>Shown in every email footer. Required by law in many countries.</small></label>';
		echo '</div><h3>SMTP (outgoing server)</h3><div class="kb-grid">';
		$text( 'smtp_host', 'SMTP host', 'Google Workspace: smtp.gmail.com · Hostinger email: smtp.hostinger.com' );
		$text( 'smtp_port', 'Port', '587 with TLS, or 465 with SSL', 'number' );
		echo '<label>Security<select name="smtp_secure">';
		foreach ( array( 'tls' => 'TLS (587)', 'ssl' => 'SSL (465)', '' => 'None' ) as $k => $v ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $s['smtp_secure'], $k, false ) . '>' . esc_html( $v ) . '</option>';
		}
		echo '</select></label>';
		$text( 'smtp_user', 'SMTP username', 'Usually the full email address.' );
		$secret( 'smtp_pass', 'SMTP password', 'For Gmail/Workspace use an <em>App password</em>, not your normal password.' );
		echo '</div></div>';

		echo '<div class="kb-panel"><h2>3. Reading replies (IMAP)</h2><div class="kb-grid">';
		$text( 'imap_host', 'IMAP host', 'Gmail/Workspace: imap.gmail.com · Hostinger: imap.hostinger.com' );
		$text( 'imap_port', 'Port', 'Usually 993', 'number' );
		$text( 'imap_user', 'IMAP username' );
		$secret( 'imap_pass', 'IMAP password' );
		echo '</div><p class="kb-muted kb-small">The bot only reads. It never deletes or changes your mail. It starts with mail that arrives after you connect.</p></div>';

		echo '<div class="kb-panel kb-panel--safety"><h2>4. Safety</h2>';
		$check( 'dry_run', 'Test mode (dry run): do not really send anything' );
		$check( 'require_approval', 'I approve every email before it is sent (recommended for the first weeks)' );
		echo '<div class="kb-grid">';
		$text( 'daily_limit', 'Max emails per day', 'New inbox: start at 20-30. Never above 50 per inbox.', 'number' );
		$text( 'per_run_limit', 'Max emails per 10-minute run', 'Keeps sending slow and human-like.', 'number' );
		$text( 'ai_per_run', 'Max AI drafts per run', 'Controls Claude cost.', 'number' );
		$text( 'window_start', 'Send from (hour, 0-23)', 'Uses the site time zone (Settings → General).', 'number' );
		$text( 'window_end', 'Send until (hour)', '', 'number' );
		echo '</div>';
		$check( 'skip_weekends', 'Do not send on Saturday and Sunday' );
		echo '</div>';

		echo '<div class="kb-panel"><h2>5. WhatsApp (optional)</h2><p class="kb-muted">Needs a WhatsApp Business API account from Meta (or a partner such as AiSensy or Interakt) and a message template approved by Meta. Only leads marked "opted in" get messages.</p><div class="kb-grid">';
		$secret( 'wa_token', 'Access token' );
		$text( 'wa_phone_id', 'Phone number ID' );
		$text( 'wa_template', 'Template name', 'Exactly as approved in WhatsApp Manager.' );
		$text( 'wa_lang', 'Template language code', 'e.g. en, en_US, hi' );
		$text( 'wa_params', 'Template variables, in order', 'Lead fields for {{1}}, {{2}}…, e.g. <code>first_name,company</code>' );
		$text( 'wa_api_version', 'Graph API version' );
		echo '</div></div>';

		echo '<div class="kb-panel"><h2>6. Reports</h2>';
		$check( 'weekly_report', 'Email each client a weekly report on Monday morning' );
		echo '</div>';
		submit_button( 'Save settings' );
		echo '</form>';

		echo '<div class="kb-panel"><h2>Test the connections</h2><div class="kb-actions">';
		echo self::action_link( 'test_ai', array(), 'Test Claude', 'button' ) . ' '; // phpcs:ignore WordPress.Security.EscapeOutput
		echo self::action_link( 'test_email', array(), 'Send a test email to myself', 'button' ) . ' '; // phpcs:ignore WordPress.Security.EscapeOutput
		echo self::action_link( 'test_imap', array(), 'Test inbox (IMAP)', 'button' ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '</div><p class="kb-muted kb-small">Tip: secrets can also live in wp-config.php: <code>define( \'KADVIA_BOT_API_KEY\', \'...\' );</code> (also KADVIA_BOT_SMTP_PASS, KADVIA_BOT_IMAP_PASS, KADVIA_BOT_WA_TOKEN).</p></div>';
		self::footer();
	}

	/* =========================================================
	 * Log
	 * ======================================================= */

	public static function page_log() {
		self::header( 'Activity log' );
		echo '<table class="widefat striped kb-table"><thead><tr><th style="width:160px">Time</th><th>What happened</th></tr></thead><tbody>';
		$lines = KB_Log::lines();
		if ( ! $lines ) {
			echo '<tr><td colspan="2">Nothing yet.</td></tr>';
		}
		foreach ( $lines as $l ) {
			echo '<tr class="' . ( 'error' === $l['l'] ? 'kb-err' : '' ) . '"><td>' . esc_html( $l['t'] ) . '</td><td>' . esc_html( $l['m'] ) . '</td></tr>';
		}
		echo '</tbody></table>';
		self::footer();
	}

	/* =========================================================
	 * Form handler
	 * ======================================================= */

	public static function handle() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'Not allowed.' );
		}
		check_admin_referer( 'kb_action' );
		global $wpdb;
		// phpcs:disable WordPress.Security.NonceVerification -- checked above.
		$req  = wp_unslash( $_REQUEST );
		$do   = sanitize_key( $req['do'] ?? '' );
		$id   = absint( $req['id'] ?? 0 );
		$back = wp_get_referer() ? wp_get_referer() : self::url( 'kadvia-bot' );

		switch ( $do ) {
			case 'run_now':
				$out = KB_Engine::run();
				self::notice( str_contains( $out, 'error' ) ? 'error' : 'success', 'Run finished: ' . $out );
				break;

			case 'save_settings':
				KB_Settings::save( $_POST ); // Values are sanitized inside save().
				self::notice( 'success', 'Settings saved.' );
				break;

			case 'save_campaign':
				$channels = array_values( array_intersect( (array) ( $req['channels'] ?? array() ), array( 'email', 'linkedin', 'call', 'whatsapp' ) ) );
				$data     = array(
					'name'         => sanitize_text_field( $req['name'] ?? '' ),
					'client_name'  => sanitize_text_field( $req['client_name'] ?? '' ),
					'client_email' => sanitize_email( $req['client_email'] ?? '' ),
					'calendly_url' => esc_url_raw( $req['calendly_url'] ?? '' ),
					'offer'        => sanitize_textarea_field( $req['offer'] ?? '' ),
					'icp'          => sanitize_textarea_field( $req['icp'] ?? '' ),
					'proof'        => sanitize_textarea_field( $req['proof'] ?? '' ),
					'tone'         => sanitize_text_field( $req['tone'] ?? '' ),
					'signature'    => sanitize_textarea_field( $req['signature'] ?? '' ),
					'steps'        => max( 1, min( 6, absint( $req['steps'] ?? 3 ) ) ),
					'gap_days'     => max( 1, min( 14, absint( $req['gap_days'] ?? 3 ) ) ),
					'channels'     => implode( ',', $channels ? $channels : array( 'email' ) ),
					'status'       => 'active' === ( $req['status'] ?? '' ) ? 'active' : 'paused',
				);
				if ( $id ) {
					$wpdb->update( KB_DB::t( 'campaigns' ), $data, array( 'id' => $id ) );
				} else {
					$data['created_at'] = KB_DB::now();
					$wpdb->insert( KB_DB::t( 'campaigns' ), $data );
				}
				self::notice( 'success', 'Campaign saved.' . ( 'active' === $data['status'] ? '' : ' It is paused: tick Active when you want the bot to start.' ) );
				$back = self::url( 'kb-campaigns' );
				break;

			case 'campaign_status':
				$wpdb->update( KB_DB::t( 'campaigns' ), array( 'status' => 'active' === ( $req['status'] ?? '' ) ? 'active' : 'paused' ), array( 'id' => $id ) );
				self::notice( 'success', 'Campaign updated.' );
				break;

			case 'import_csv':
				$cid = absint( $req['campaign_id'] ?? 0 );
				if ( ! $cid || empty( $_FILES['csv']['tmp_name'] ) || ! is_uploaded_file( $_FILES['csv']['tmp_name'] ) ) {
					self::notice( 'error', 'Choose a campaign and a CSV file.' );
					break;
				}
				$r   = KB_Leads::import_csv( $_FILES['csv']['tmp_name'], $cid ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				$msg = $r['added'] . ' leads added, ' . $r['skipped'] . ' skipped.';
				if ( $r['errors'] ) {
					$msg .= "\n" . implode( "\n", $r['errors'] );
				}
				$checked = KB_Leads::verify_pending( 300 );
				$msg    .= "\n" . $checked . ' email domains checked.';
				KB_Log::add( 'CSV import: ' . $r['added'] . ' added, ' . $r['skipped'] . ' skipped.' );
				self::notice( $r['added'] ? 'success' : 'error', $msg );
				break;

			case 'add_lead':
				$res = KB_Leads::add( $req, absint( $req['campaign_id'] ?? 0 ) );
				if ( is_wp_error( $res ) ) {
					self::notice( 'error', $res->get_error_message() );
				} else {
					KB_Leads::verify_pending( 5 );
					self::notice( 'success', 'Lead added.' );
				}
				break;

			case 'lead_status':
				$lead   = KB_DB::lead( $id );
				$status = sanitize_key( $req['status'] ?? '' );
				if ( $lead && isset( KB_DB::LEAD_STATUSES[ $status ] ) ) {
					if ( 'unsubscribed' === $status ) {
						KB_Engine::unsubscribe( $lead, 'manual' );
					} elseif ( 'active' === $status ) {
						KB_DB::update_lead(
							$id,
							array(
								'status'         => 'active',
								'next_action_at' => null,
							)
						);
					} else {
						KB_DB::update_lead( $id, array( 'status' => $status ) );
						if ( in_array( $status, array( 'meeting', 'not_interested' ), true ) ) {
							KB_DB::cancel_pending( $id );
						}
					}
					KB_Log::add( 'Lead ' . ( $lead->email ? $lead->email : KB_DB::full_name( $lead ) ) . ' marked ' . KB_DB::LEAD_STATUSES[ $status ] . '.' );
					self::notice( 'success', 'Lead marked: ' . KB_DB::LEAD_STATUSES[ $status ] . '.' );
				}
				break;

			case 'lead_wa':
				$lead = KB_DB::lead( $id );
				if ( $lead ) {
					KB_DB::update_lead( $id, array( 'wa_opt_in' => $lead->wa_opt_in ? 0 : 1 ) );
				}
				break;

			case 'lead_delete':
				$wpdb->delete( KB_DB::t( 'messages' ), array( 'lead_id' => $id ) );
				$wpdb->delete( KB_DB::t( 'leads' ), array( 'id' => $id ) );
				self::notice( 'success', 'Lead deleted.' );
				$back = self::url( 'kb-leads' );
				break;

			case 'msg_save':
				$m = KB_DB::message( $id );
				if ( $m && 'draft' === $m->status ) {
					$data = array();
					if ( 'email' === $m->channel ) {
						$data['subject'] = sanitize_text_field( $req['subject'] ?? $m->subject );
						$data['body']    = sanitize_textarea_field( $req['body'] ?? $m->body );
					}
					if ( ! empty( $req['approve'] ) ) {
						$data['status'] = 'approved';
					}
					KB_DB::update_message( $id, $data );
					self::notice( 'success', ! empty( $req['approve'] ) ? 'Approved. It goes out on the next run in the sending window.' : 'Saved.' );
				}
				break;

			case 'approve_many':
				$ids = array_filter( array_map( 'absint', explode( ',', (string) ( $req['ids'] ?? '' ) ) ) );
				$n   = 0;
				foreach ( $ids as $mid ) {
					$m = KB_DB::message( $mid );
					if ( $m && 'draft' === $m->status ) {
						KB_DB::update_message( $mid, array( 'status' => 'approved' ) );
						++$n;
					}
				}
				self::notice( 'success', $n . ' messages approved.' );
				break;

			case 'msg_skip':
				$m = KB_DB::message( $id );
				if ( $m ) {
					KB_DB::update_message( $id, array( 'status' => 'cancelled' ) );
					if ( 'sequence' === $m->kind && 'email' === $m->channel ) {
						KB_DB::update_lead( $m->lead_id, array( 'status' => 'done' ) );
						self::notice( 'success', 'Skipped. The bot will not write to this lead again.' );
					} else {
						self::notice( 'success', 'Dropped.' );
					}
				}
				break;

			case 'msg_rewrite':
				$m = KB_DB::message( $id );
				if ( $m && 'sequence' === $m->kind ) {
					KB_DB::update_message( $id, array( 'status' => 'cancelled' ) );
					KB_DB::update_lead( $m->lead_id, array( 'next_action_at' => null ) );
					$out = KB_Engine::prepare();
					self::notice( 'success', 'Rewritten (' . $out . ' new drafts).' );
				}
				break;

			case 'msg_retry':
				KB_DB::update_message(
					$id,
					array(
						'status' => 'approved',
						'error'  => '',
					)
				);
				self::notice( 'success', 'Will retry on the next run.' );
				break;

			case 'task_done':
				$m       = KB_DB::message( $id );
				$outcome = sanitize_key( $req['outcome'] ?? 'done' );
				if ( $m ) {
					KB_DB::update_message(
						$id,
						array(
							'status'  => 'no_answer' === $outcome ? 'draft' : 'done',
							'summary' => $outcome,
							'sent_at' => KB_DB::now(),
						)
					);
					$lead_update = array( 'last_contact_at' => KB_DB::now() );
					if ( in_array( $outcome, array( 'meeting', 'not_interested' ), true ) ) {
						$lead_update['status'] = $outcome;
						KB_DB::cancel_pending( $m->lead_id );
					}
					KB_DB::update_lead( $m->lead_id, $lead_update );
					self::notice( 'success', 'no_answer' === $outcome ? 'Kept on the list: try again later.' : 'Marked done.' );
				}
				break;

			case 'send_report':
				$c = KB_DB::campaign( $id );
				if ( $c && is_email( $c->client_email ) ) {
					if ( KB_Settings::get( 'dry_run' ) ) {
						self::notice( 'warning', 'Test mode is on, so the report was not emailed.' );
					} else {
						$ok = KB_Mailer::send_plain( $c->client_email, 'Outreach report: ' . $c->name, KB_Reports::text( $c, 7 ) );
						self::notice( $ok ? 'success' : 'error', $ok ? 'Report sent.' : 'Could not send. Check SMTP settings.' );
					}
				}
				break;

			case 'export_csv':
				KB_Reports::export_csv( absint( $req['campaign'] ?? 0 ) );
				exit;

			case 'test_ai':
				try {
					$c    = (object) array(
						'client_name'  => 'Kadvia',
						'offer'        => 'We book qualified sales meetings for B2B companies using email, LinkedIn, WhatsApp and phone.',
						'icp'          => 'Founders of B2B service companies in India',
						'proof'        => '',
						'tone'         => 'friendly, simple English',
						'calendly_url' => '',
					);
					$lead = (object) array(
						'id'         => 0,
						'first_name' => 'Asha',
						'last_name'  => 'Rao',
						'title'      => 'Founder',
						'company'    => 'Example Labs',
						'industry'   => 'IT services',
						'city'       => 'Pune',
						'website'    => '',
						'notes'      => '',
					);
					$mail = KB_AI::write_email( $c, $lead, 1, 3 );
					self::notice( 'success', ( KB_AI::is_demo() ? "Demo mode works (template, not AI). Sample email:\n\nSubject: " : "Claude works. Sample email:\n\nSubject: " ) . $mail['subject'] . "\n\n" . $mail['body'] );
				} catch ( Throwable $e ) {
					self::notice( 'error', $e->getMessage() );
				}
				break;

			case 'test_email':
				$s = KB_Settings::all();
				if ( ! is_email( $s['sender_email'] ) ) {
					self::notice( 'error', 'Set the sender email first.' );
					break;
				}
				$err   = '';
				$catch = static function ( $e ) use ( &$err ) {
					$err = $e->get_error_message();
				};
				add_action( 'wp_mail_failed', $catch );
				$ok = KB_Mailer::send_plain( $s['sender_email'], 'Kadvia Bot test email', "If you can read this, the bot can send email.\n\nSent " . wp_date( 'j M Y H:i' ) );
				remove_action( 'wp_mail_failed', $catch );
				self::notice( $ok ? 'success' : 'error', $ok ? 'Test email sent to ' . $s['sender_email'] . '. Check the inbox (and spam).' : 'Sending failed: ' . $err );
				break;

			case 'test_imap':
				$s    = KB_Settings::all();
				$imap = new KB_Imap();
				try {
					$imap->connect( $s['imap_host'], $s['imap_port'], $s['imap_user'], $s['imap_pass'] );
					$box = $imap->select_inbox();
					$imap->close();
					self::notice( 'success', 'Inbox connected (' . max( 0, $box['uidnext'] - 1 ) . ' messages so far).' );
				} catch ( Throwable $e ) {
					$imap->close();
					self::notice( 'error', 'Inbox test failed: ' . $e->getMessage() );
				}
				break;
		}
		// phpcs:enable
		wp_safe_redirect( $back );
		exit;
	}
}
