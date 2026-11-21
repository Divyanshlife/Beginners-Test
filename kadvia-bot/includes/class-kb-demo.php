<?php
/**
 * Demo mode: free, no AI. Fixed templates write the messages and simple
 * keyword rules sort replies, so the whole flow can be tested without a
 * Claude API key. Messages are less personal than Claude's.
 *
 * @package kadvia-bot
 */

defined( 'ABSPATH' ) || exit;

class KB_Demo {

	private static function first( $lead ) {
		return trim( (string) $lead->first_name ) !== '' ? trim( $lead->first_name ) : 'there';
	}

	private static function company( $lead ) {
		return trim( (string) $lead->company ) !== '' ? trim( $lead->company ) : 'your team';
	}

	/** One short line about what the client does, taken from the campaign offer. */
	private static function offer_line( $campaign ) {
		$offer = trim( preg_replace( '/\s+/', ' ', (string) $campaign->offer ) );
		$first = preg_split( '/(?<=[.!?])\s/', $offer );
		$line  = $first ? $first[0] : $offer;
		return rtrim( mb_substr( $line, 0, 220 ), '.' ) . '.';
	}

	public static function write_email( $campaign, $lead, $step, $total ) {
		$hi      = 'Hi ' . self::first( $lead ) . ',';
		$company = self::company( $lead );
		$client  = $campaign->client_name ? $campaign->client_name : 'we';
		$link    = $campaign->calendly_url ? "\n\nIf it is easier, pick a time here: " . $campaign->calendly_url : '';
		$role    = trim( (string) $lead->title ) !== '' ? ' as ' . trim( $lead->title ) : '';

		if ( 1 === $step ) {
			return array(
				'subject' => 'quick question, ' . self::first( $lead ),
				'body'    => $hi . "\n\n"
					. 'I am reaching out because you lead things' . $role . ' at ' . $company . ".\n\n"
					. 'At ' . $client . ': ' . self::offer_line( $campaign ) . "\n\n"
					. 'Would a 15-minute call next week be worth it to see if this fits ' . $company . '?',
			);
		}
		if ( $step < $total ) {
			$proof = trim( (string) $campaign->proof ) !== '' ? "\n\nFor context: " . trim( preg_split( '/\R/', $campaign->proof )[0] ) : '';
			return array(
				'subject' => 'following up',
				'body'    => $hi . "\n\n"
					. 'Sharing one more thought in case my last note got buried. Teams like ' . $company . ' usually come to us when they want results without adding more work to their plate.' . $proof . "\n\n"
					. 'Is this a priority for you this quarter?' . $link,
			);
		}
		return array(
			'subject' => 'closing the loop',
			'body'    => $hi . "\n\n"
				. 'I will not keep filling your inbox. If this becomes relevant for ' . $company . ' later, just reply to this email.' . "\n\n"
				. 'And if someone else handles this, I would be grateful for a pointer.',
		);
	}

	public static function write_linkedin( $campaign, $lead ) {
		$first = self::first( $lead );
		return "CONNECTION NOTE:\nHi " . $first . ', I work with teams like ' . self::company( $lead ) . ' and would be glad to connect.'
			. "\n\nAFTER THEY ACCEPT:\nThanks for connecting, " . $first . '. At ' . $campaign->client_name . ': ' . self::offer_line( $campaign )
			. ' Is this something you are looking at this year?';
	}

	public static function write_call_script( $campaign, $lead ) {
		$first = self::first( $lead );
		return "OPENER:\nHi " . $first . ', this is [your name] calling about ' . $campaign->client_name . '. Do you have 30 seconds?'
			. "\n\nWHY THEM:\nI saw you work at " . self::company( $lead ) . ', and we help teams like yours. ' . self::offer_line( $campaign )
			. "\n\nQUESTIONS:\n- How do you handle this today?\n- What is the hardest part of it?\n- Who else is involved when you decide on this?"
			. "\n\nIF THEY SAY:\n- \"Send me an email\" → Happy to. What should it cover so it is useful for you?"
			. "\n- \"We already have something\" → Makes sense. What would you change about it if you could?"
			. "\n- \"No budget\" → Understood. Is it worth a short call to see if it pays for itself?"
			. "\n\nCLOSE:\nCould we do 15 minutes on Tuesday at 11 or Thursday at 4?";
	}

	/** Keyword rules instead of AI. */
	public static function read_reply( $campaign, $lead, $subject, $text ) {
		$t    = strtolower( $subject . ' ' . $text );
		$name = self::first( $lead );
		$link = $campaign->calendly_url ? ' You can pick a time here: ' . $campaign->calendly_url . '.' : ' Would Tuesday at 11 or Thursday at 4 work for you?';
		$rule = static fn( $re ) => (bool) preg_match( $re, $t );

		if ( $rule( '/\b(unsubscribe|stop emailing|remove me|do not contact|don\'t contact)\b/' ) ) {
			return self::out( 'unsubscribe', 'Asked to stop.', '' );
		}
		if ( $rule( '/\b(out of office|out of the office|on leave|on vacation|on holiday|auto.?reply|automatic reply)\b/' ) ) {
			return self::out( 'out_of_office', 'Automatic out-of-office reply.', '' );
		}
		if ( $rule( '/\b(not interested|no thanks|no thank you|not a fit|not for us|we are good|we\'re good)\b/' ) ) {
			return self::out( 'not_interested', 'Not interested.', 'Thanks for letting me know, ' . $name . '. All the best.' );
		}
		if ( $rule( '/\b(not the right person|wrong person|not my area|contact .* instead|reach out to)\b/' ) ) {
			return self::out( 'wrong_person', 'Says they are not the right person.', 'Thanks, ' . $name . '. Who would be the best person to speak to about this?' );
		}
		if ( $rule( '/\b(later|next month|next quarter|not now|not right now|after|busy)\b/' ) && ! $rule( '/\b(yes|sure|interested)\b/' ) ) {
			return self::out( 'not_now', 'Not now, maybe later.', 'Understood, ' . $name . '. When would be a good time for me to check back?' );
		}
		if ( $rule( '/\b(call|meeting|meet|talk|chat|demo|schedule|calendar|monday|tuesday|wednesday|thursday|friday|tomorrow|available|slot)\b/' ) ) {
			return self::out( 'meeting_request', 'Wants to talk.', 'Great, ' . $name . '. Glad to set up 15 minutes.' . $link );
		}
		if ( $rule( '/\b(interested|sounds good|tell me more|send more|share more|curious|yes)\b/' ) ) {
			return self::out( 'interested', 'Interested.', 'Thanks, ' . $name . '. The quickest way to see if this fits is a 15-minute call.' . $link );
		}
		if ( str_contains( $t, '?' ) ) {
			return self::out( 'question', 'Asked a question.', 'Good question, ' . $name . '. Let me answer it properly on a short call so I get the details right.' . $link );
		}
		return self::out( 'other', 'Replied (please read).', 'Thanks for your reply, ' . $name . '.' . $link );
	}

	private static function out( $category, $summary, $reply ) {
		return array(
			'category'     => $category,
			'summary'      => '[Demo] ' . $summary,
			'reply_needed' => '' !== $reply,
			'reply_body'   => $reply,
		);
	}
}
