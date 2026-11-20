<?php
/**
 * Lead import (CSV from Apollo, Sales Navigator tools, Excel), single add, and email checks.
 *
 * @package kadvia-bot
 */

defined( 'ABSPATH' ) || exit;

class KB_Leads {

	/** CSV header (lowercased, letters only) => lead field. */
	const MAP = array(
		'firstname'           => 'first_name',
		'first'               => 'first_name',
		'name'                => 'first_name',
		'lastname'            => 'last_name',
		'last'                => 'last_name',
		'surname'             => 'last_name',
		'email'               => 'email',
		'emailaddress'        => 'email',
		'workemail'           => 'email',
		'company'             => 'company',
		'companyname'         => 'company',
		'organization'        => 'company',
		'organisation'        => 'company',
		'account'             => 'company',
		'title'               => 'title',
		'jobtitle'            => 'title',
		'designation'         => 'title',
		'position'            => 'title',
		'phone'               => 'phone',
		'mobile'              => 'phone',
		'mobilephone'         => 'phone',
		'phonenumber'         => 'phone',
		'workdirectphone'     => 'phone',
		'whatsapp'            => 'phone',
		'linkedin'            => 'linkedin_url',
		'linkedinurl'         => 'linkedin_url',
		'personlinkedinurl'   => 'linkedin_url',
		'linkedinprofile'     => 'linkedin_url',
		'website'             => 'website',
		'companywebsite'      => 'website',
		'domain'              => 'website',
		'industry'            => 'industry',
		'city'                => 'city',
		'location'            => 'city',
		'notes'               => 'notes',
		'note'                => 'notes',
		'whatsappoptin'       => 'wa_opt_in',
		'optin'               => 'wa_opt_in',
	);

	/**
	 * Import a CSV file into a campaign.
	 *
	 * @return array{added:int, skipped:int, errors:array}
	 */
	public static function import_csv( $path, $campaign_id ) {
		$result = array(
			'added'   => 0,
			'skipped' => 0,
			'errors'  => array(),
		);
		$fh     = fopen( $path, 'r' );
		if ( ! $fh ) {
			$result['errors'][] = 'Could not open the file.';
			return $result;
		}
		$first = fgets( $fh );
		if ( false === $first ) {
			fclose( $fh );
			$result['errors'][] = 'The file is empty.';
			return $result;
		}
		$first = preg_replace( '/^\xEF\xBB\xBF/', '', $first );
		$delim = substr_count( $first, ';' ) > substr_count( $first, ',' ) ? ';' : ',';
		$head  = str_getcsv( $first, $delim );
		$cols  = array();
		foreach ( $head as $i => $h ) {
			$k = preg_replace( '/[^a-z]/', '', strtolower( $h ) );
			if ( isset( self::MAP[ $k ] ) && ! in_array( self::MAP[ $k ], $cols, true ) ) {
				$cols[ $i ] = self::MAP[ $k ];
			}
		}
		if ( ! in_array( 'email', $cols, true ) && ! in_array( 'phone', $cols, true ) && ! in_array( 'linkedin_url', $cols, true ) ) {
			fclose( $fh );
			$result['errors'][] = 'No Email, Phone or LinkedIn column found. Check the first row of the CSV.';
			return $result;
		}
		$row_no = 1;
		while ( ( $row = fgetcsv( $fh, 0, $delim ) ) !== false ) {
			++$row_no;
			if ( array( null ) === $row ) {
				continue;
			}
			$data = array();
			foreach ( $cols as $i => $field ) {
				$data[ $field ] = isset( $row[ $i ] ) ? trim( (string) $row[ $i ] ) : '';
			}
			// "Name" column with a full name: split it.
			if ( ! empty( $data['first_name'] ) && empty( $data['last_name'] ) && str_contains( $data['first_name'], ' ' ) ) {
				list( $data['first_name'], $data['last_name'] ) = array_map( 'trim', explode( ' ', $data['first_name'], 2 ) );
			}
			$id = self::add( $data, $campaign_id );
			if ( is_wp_error( $id ) ) {
				++$result['skipped'];
				if ( count( $result['errors'] ) < 15 ) {
					$result['errors'][] = 'Row ' . $row_no . ': ' . $id->get_error_message();
				}
			} else {
				++$result['added'];
			}
		}
		fclose( $fh );
		return $result;
	}

	/**
	 * Add one lead. Returns the new id or WP_Error.
	 */
	public static function add( array $data, $campaign_id ) {
		global $wpdb;
		$email = strtolower( sanitize_email( $data['email'] ?? '' ) );
		if ( ! empty( $data['email'] ) && ! $email ) {
			return new WP_Error( 'email', 'Email address is not valid.' );
		}
		$phone = preg_replace( '/[^0-9+]/', '', (string) ( $data['phone'] ?? '' ) );
		if ( ! $email && ! $phone && empty( $data['linkedin_url'] ) ) {
			return new WP_Error( 'empty', 'No email, phone or LinkedIn.' );
		}
		if ( $email && $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . KB_DB::t( 'leads' ) . ' WHERE campaign_id = %d AND email = %s', $campaign_id, $email ) ) ) {
			return new WP_Error( 'dup', 'Already in this campaign (' . $email . ').' );
		}
		$opt_raw = strtolower( trim( (string) ( $data['wa_opt_in'] ?? '' ) ) );
		$row     = array(
			'campaign_id'  => (int) $campaign_id,
			'first_name'   => sanitize_text_field( $data['first_name'] ?? '' ),
			'last_name'    => sanitize_text_field( $data['last_name'] ?? '' ),
			'email'        => $email,
			'company'      => sanitize_text_field( $data['company'] ?? '' ),
			'title'        => sanitize_text_field( $data['title'] ?? '' ),
			'phone'        => $phone,
			'linkedin_url' => esc_url_raw( $data['linkedin_url'] ?? '' ),
			'website'      => esc_url_raw( $data['website'] ?? '' ),
			'industry'     => sanitize_text_field( $data['industry'] ?? '' ),
			'city'         => sanitize_text_field( $data['city'] ?? '' ),
			'notes'        => sanitize_textarea_field( $data['notes'] ?? '' ),
			'wa_opt_in'    => in_array( $opt_raw, array( '1', 'yes', 'y', 'true', 'haan' ), true ) ? 1 : 0,
			'status'       => 'new',
			'email_status' => $email ? 'unknown' : 'none',
			'token'        => wp_generate_password( 32, false ),
			'created_at'   => KB_DB::now(),
		);
		if ( $email && KB_DB::is_suppressed( $email ) ) {
			$row['status'] = 'unsubscribed';
		}
		$ok = $wpdb->insert( KB_DB::t( 'leads' ), $row );
		if ( ! $ok ) {
			return new WP_Error( 'db', 'Could not save: ' . $wpdb->last_error );
		}
		return (int) $wpdb->insert_id;
	}

	/**
	 * Check that the email's domain can receive mail (MX record).
	 * This catches typos and dead domains, not every bad mailbox.
	 */
	public static function check_email( $email ) {
		if ( ! is_email( $email ) ) {
			return 'invalid';
		}
		$domain = substr( strrchr( $email, '@' ), 1 );
		static $cache = array();
		if ( ! isset( $cache[ $domain ] ) ) {
			$cache[ $domain ] = function_exists( 'checkdnsrr' ) ? ( checkdnsrr( $domain, 'MX' ) || checkdnsrr( $domain, 'A' ) ) : true;
		}
		if ( ! $cache[ $domain ] ) {
			return 'invalid';
		}
		$local = strtolower( strstr( $email, '@', true ) );
		if ( in_array( $local, array( 'info', 'contact', 'sales', 'support', 'hello', 'admin', 'office', 'enquiry', 'enquiries', 'hr', 'careers', 'jobs', 'noreply', 'no-reply' ), true ) ) {
			return 'risky';
		}
		return 'valid';
	}

	/** Check up to $limit unchecked emails. Returns how many were checked. */
	public static function verify_pending( $limit = 200 ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id, email FROM ' . KB_DB::t( 'leads' ) . " WHERE email <> '' AND email_status = 'unknown' LIMIT %d", $limit ) );
		foreach ( $rows as $r ) {
			$status = self::check_email( $r->email );
			KB_DB::update_lead( $r->id, array( 'email_status' => $status ) );
		}
		return count( $rows );
	}
}
