<?php
/**
 * Database tables and small query helpers.
 *
 * @package kadvia-bot
 */

defined( 'ABSPATH' ) || exit;

class KB_DB {

	const SCHEMA_VERSION = 1;

	const LEAD_STATUSES = array(
		'new'            => 'New',
		'active'         => 'In sequence',
		'replied'        => 'Replied',
		'interested'     => 'Interested',
		'meeting'        => 'Meeting booked',
		'not_interested' => 'Not interested',
		'unsubscribed'   => 'Unsubscribed',
		'bounced'        => 'Bounced',
		'done'           => 'Sequence finished',
	);

	public static function t( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'kb_' . $name;
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c = $wpdb->get_charset_collate();

		dbDelta(
			'CREATE TABLE ' . self::t( 'campaigns' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(190) NOT NULL DEFAULT '',
			client_name varchar(190) NOT NULL DEFAULT '',
			client_email varchar(190) NOT NULL DEFAULT '',
			offer text NOT NULL,
			icp text NOT NULL,
			proof text NOT NULL,
			tone varchar(190) NOT NULL DEFAULT '',
			signature text NOT NULL,
			calendly_url varchar(255) NOT NULL DEFAULT '',
			steps tinyint(3) unsigned NOT NULL DEFAULT 3,
			gap_days tinyint(3) unsigned NOT NULL DEFAULT 3,
			channels varchar(100) NOT NULL DEFAULT 'email',
			status varchar(20) NOT NULL DEFAULT 'paused',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id)
		) $c;"
		);

		dbDelta(
			'CREATE TABLE ' . self::t( 'leads' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			campaign_id bigint(20) unsigned NOT NULL DEFAULT 0,
			first_name varchar(100) NOT NULL DEFAULT '',
			last_name varchar(100) NOT NULL DEFAULT '',
			email varchar(190) NOT NULL DEFAULT '',
			company varchar(190) NOT NULL DEFAULT '',
			title varchar(190) NOT NULL DEFAULT '',
			phone varchar(50) NOT NULL DEFAULT '',
			linkedin_url varchar(255) NOT NULL DEFAULT '',
			website varchar(255) NOT NULL DEFAULT '',
			industry varchar(190) NOT NULL DEFAULT '',
			city varchar(100) NOT NULL DEFAULT '',
			notes text NOT NULL,
			email_status varchar(20) NOT NULL DEFAULT 'unknown',
			wa_opt_in tinyint(1) NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'new',
			step tinyint(3) unsigned NOT NULL DEFAULT 0,
			next_action_at datetime NULL DEFAULT NULL,
			last_contact_at datetime NULL DEFAULT NULL,
			thread_id varchar(190) NOT NULL DEFAULT '',
			token varchar(40) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY campaign_email (campaign_id,email),
			KEY email (email),
			KEY status (status),
			KEY token (token)
		) $c;"
		);

		dbDelta(
			'CREATE TABLE ' . self::t( 'messages' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			lead_id bigint(20) unsigned NOT NULL DEFAULT 0,
			campaign_id bigint(20) unsigned NOT NULL DEFAULT 0,
			channel varchar(20) NOT NULL DEFAULT 'email',
			direction varchar(5) NOT NULL DEFAULT 'out',
			kind varchar(20) NOT NULL DEFAULT 'sequence',
			step tinyint(3) unsigned NOT NULL DEFAULT 0,
			subject varchar(255) NOT NULL DEFAULT '',
			body longtext NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'draft',
			category varchar(30) NOT NULL DEFAULT '',
			summary text NOT NULL,
			error text NOT NULL,
			ext_id varchar(190) NOT NULL DEFAULT '',
			in_reply_to varchar(190) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			sent_at datetime NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY lead_id (lead_id),
			KEY status (status),
			KEY channel_status (channel,status)
		) $c;"
		);

		dbDelta(
			'CREATE TABLE ' . self::t( 'suppress' ) . " (
			email varchar(190) NOT NULL,
			reason varchar(30) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			PRIMARY KEY  (email)
		) $c;"
		);

		update_option( 'kb_schema', self::SCHEMA_VERSION, false );
	}

	public static function now() {
		return current_time( 'mysql' );
	}

	/* ---------- Campaigns ---------- */

	public static function campaign( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t( 'campaigns' ) . ' WHERE id = %d', $id ) );
	}

	public static function campaigns( $status = '' ) {
		global $wpdb;
		$sql = 'SELECT * FROM ' . self::t( 'campaigns' );
		if ( $status ) {
			$sql .= $wpdb->prepare( ' WHERE status = %s', $status );
		}
		return $wpdb->get_results( $sql . ' ORDER BY id DESC' );
	}

	public static function channels( $campaign ) {
		return array_filter( array_map( 'trim', explode( ',', (string) $campaign->channels ) ) );
	}

	/* ---------- Leads ---------- */

	public static function lead( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t( 'leads' ) . ' WHERE id = %d', $id ) );
	}

	public static function update_lead( $id, array $data ) {
		global $wpdb;
		return $wpdb->update( self::t( 'leads' ), $data, array( 'id' => $id ) );
	}

	public static function full_name( $lead ) {
		return trim( $lead->first_name . ' ' . $lead->last_name );
	}

	/* ---------- Messages ---------- */

	public static function message( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t( 'messages' ) . ' WHERE id = %d', $id ) );
	}

	public static function add_message( array $data ) {
		global $wpdb;
		$data = wp_parse_args(
			$data,
			array(
				'body'       => '',
				'summary'    => '',
				'error'      => '',
				'created_at' => self::now(),
			)
		);
		$wpdb->insert( self::t( 'messages' ), $data );
		return (int) $wpdb->insert_id;
	}

	public static function update_message( $id, array $data ) {
		global $wpdb;
		return $wpdb->update( self::t( 'messages' ), $data, array( 'id' => $id ) );
	}

	/** Messages for one lead, oldest first. */
	public static function thread( $lead_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::t( 'messages' ) . ' WHERE lead_id = %d ORDER BY id ASC', $lead_id ) );
	}

	/** Drop drafts and open tasks for a lead (used when the lead replies or opts out). */
	public static function cancel_pending( $lead_id, $keep_replies = true ) {
		global $wpdb;
		$sql = 'UPDATE ' . self::t( 'messages' ) . " SET status = 'cancelled' WHERE lead_id = %d AND direction = 'out' AND status IN ('draft','approved')";
		if ( $keep_replies ) {
			$sql .= " AND kind <> 'reply'";
		}
		$wpdb->query( $wpdb->prepare( $sql, $lead_id ) );
	}

	/* ---------- Suppression list ---------- */

	public static function suppress( $email, $reason ) {
		global $wpdb;
		$email = strtolower( trim( $email ) );
		if ( ! $email ) {
			return;
		}
		$wpdb->replace(
			self::t( 'suppress' ),
			array(
				'email'      => $email,
				'reason'     => $reason,
				'created_at' => self::now(),
			)
		);
	}

	public static function is_suppressed( $email ) {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM ' . self::t( 'suppress' ) . ' WHERE email = %s', strtolower( trim( $email ) ) ) );
	}
}
