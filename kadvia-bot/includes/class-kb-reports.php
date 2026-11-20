<?php
/**
 * Numbers per campaign, the client report text, and CSV export.
 *
 * @package kadvia-bot
 */

defined( 'ABSPATH' ) || exit;

class KB_Reports {

	/**
	 * @param object $c    Campaign.
	 * @param int    $days 0 = all time.
	 */
	public static function stats( $c, $days = 0 ) {
		global $wpdb;
		$L     = KB_DB::t( 'leads' );
		$M     = KB_DB::t( 'messages' );
		$since = $days ? wp_date( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ) : '1970-01-01 00:00:00';

		$by_status = array();
		foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT status, COUNT(*) n FROM $L WHERE campaign_id = %d GROUP BY status", $c->id ) ) as $r ) {
			$by_status[ $r->status ] = (int) $r->n;
		}
		$count = static function ( $sql ) use ( $wpdb, $c, $since ) {
			return (int) $wpdb->get_var( $wpdb->prepare( $sql, $c->id, $since ) );
		};
		return array(
			'leads'          => array_sum( $by_status ),
			'by_status'      => $by_status,
			'contacted'      => $count( "SELECT COUNT(DISTINCT lead_id) FROM $M WHERE campaign_id = %d AND direction = 'out' AND status IN ('sent','simulated') AND sent_at >= %s" ),
			'emails'         => $count( "SELECT COUNT(*) FROM $M WHERE campaign_id = %d AND channel = 'email' AND direction = 'out' AND status IN ('sent','simulated') AND sent_at >= %s" ),
			'whatsapp'       => $count( "SELECT COUNT(*) FROM $M WHERE campaign_id = %d AND channel = 'whatsapp' AND status IN ('sent','simulated') AND sent_at >= %s" ),
			'linkedin'       => $count( "SELECT COUNT(*) FROM $M WHERE campaign_id = %d AND channel = 'linkedin' AND status = 'done' AND sent_at >= %s" ),
			'calls'          => $count( "SELECT COUNT(*) FROM $M WHERE campaign_id = %d AND channel = 'call' AND status = 'done' AND sent_at >= %s" ),
			'replies'        => $count( "SELECT COUNT(DISTINCT lead_id) FROM $M WHERE campaign_id = %d AND direction = 'in' AND sent_at >= %s" ),
			'positive'       => $count( "SELECT COUNT(DISTINCT lead_id) FROM $M WHERE campaign_id = %d AND direction = 'in' AND category IN ('interested','meeting_request') AND sent_at >= %s" ),
			'meetings'       => (int) ( $by_status['meeting'] ?? 0 ),
		);
	}

	public static function text( $c, $days = 7 ) {
		$st   = self::stats( $c, $days );
		$all  = self::stats( $c, 0 );
		$rate = $all['contacted'] ? min( 100, round( 100 * $all['replies'] / $all['contacted'], 1 ) ) : 0;
		$out  = 'Outreach report for ' . $c->client_name . ' (' . $c->name . ")\n";
		$out .= 'Period: last ' . $days . ' days, up to ' . wp_date( 'j M Y' ) . "\n\n";
		$out .= "THIS WEEK\n";
		$out .= '- People contacted: ' . $st['contacted'] . "\n";
		$out .= '- Emails sent: ' . $st['emails'] . "\n";
		if ( $st['linkedin'] ) {
			$out .= '- LinkedIn messages: ' . $st['linkedin'] . "\n";
		}
		if ( $st['calls'] ) {
			$out .= '- Calls made: ' . $st['calls'] . "\n";
		}
		if ( $st['whatsapp'] ) {
			$out .= '- WhatsApp messages (opted-in contacts): ' . $st['whatsapp'] . "\n";
		}
		$out .= '- Replies: ' . $st['replies'] . "\n";
		$out .= '- Interested replies: ' . $st['positive'] . "\n\n";
		$out .= "TOTAL SO FAR\n";
		$out .= '- Leads in campaign: ' . $all['leads'] . "\n";
		$out .= '- People contacted: ' . $all['contacted'] . ' (reply rate ' . $rate . "%)\n";
		$out .= '- Meetings booked: ' . $all['meetings'] . "\n";
		$out .= '- Interested, being followed up: ' . (int) ( $all['by_status']['interested'] ?? 0 ) . "\n\n";

		global $wpdb;
		$hot = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . KB_DB::t( 'leads' ) . " WHERE campaign_id = %d AND status IN ('interested','meeting') ORDER BY last_contact_at DESC LIMIT 15",
				$c->id
			)
		);
		if ( $hot ) {
			$out .= "HOT LEADS\n";
			foreach ( $hot as $l ) {
				$out .= '- ' . KB_DB::full_name( $l ) . ', ' . $l->title . ', ' . $l->company . ' (' . KB_DB::LEAD_STATUSES[ $l->status ] . ")\n";
			}
			$out .= "\n";
		}
		return $out . "Questions? Just reply to this email.\n";
	}

	/** Stream a CSV of a campaign's leads. */
	public static function export_csv( $campaign_id ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT first_name,last_name,email,company,title,phone,linkedin_url,website,industry,city,status,step,email_status,last_contact_at FROM ' . KB_DB::t( 'leads' ) . ' WHERE campaign_id = %d ORDER BY id', $campaign_id ), ARRAY_A );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=kadvia-leads-' . (int) $campaign_id . '-' . wp_date( 'Y-m-d' ) . '.csv' );
		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'First name', 'Last name', 'Email', 'Company', 'Title', 'Phone', 'LinkedIn', 'Website', 'Industry', 'City', 'Status', 'Step', 'Email check', 'Last contact' ) );
		foreach ( $rows as $r ) {
			// Stop spreadsheet formula injection.
			$r = array_map( static fn( $v ) => preg_match( '/^[=+\-@]/', (string) $v ) ? "'" . $v : $v, $r );
			fputcsv( $out, $r );
		}
		fclose( $out );
		exit;
	}
}
