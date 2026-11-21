<?php
/**
 * Plugin settings. Secrets can also be set in wp-config.php as constants
 * (KADVIA_BOT_API_KEY, KADVIA_BOT_SMTP_PASS, KADVIA_BOT_IMAP_PASS, KADVIA_BOT_WA_TOKEN),
 * which then win over the values saved in the database.
 *
 * @package kadvia-bot
 */

defined( 'ABSPATH' ) || exit;

class KB_Settings {

	const OPTION = 'kb_settings';

	/** Constants that override a saved secret. */
	const SECRET_CONSTANTS = array(
		'api_key'   => 'KADVIA_BOT_API_KEY',
		'smtp_pass' => 'KADVIA_BOT_SMTP_PASS',
		'imap_pass' => 'KADVIA_BOT_IMAP_PASS',
		'wa_token'  => 'KADVIA_BOT_WA_TOKEN',
	);

	const MODELS = array(
		'claude-opus-5-5'   => 'Claude Opus 5.5 (best writing, default)',
		'claude-sonnet-5-5' => 'Claude Sonnet 5.5 (cheaper)',
		'claude-haiku-5-5'  => 'Claude Haiku 5.5 (cheapest)',
		'demo'              => 'Demo mode: free, no API key (fixed templates, not AI)',
	);

	public static function defaults() {
		return array(
			'api_key'          => '',
			'model'            => 'claude-opus-5-5',
			'sender_name'      => '',
			'sender_email'     => '',
			'business_address' => '',
			'smtp_host'        => '',
			'smtp_port'        => 587,
			'smtp_secure'      => 'tls',
			'smtp_user'        => '',
			'smtp_pass'        => '',
			'imap_host'        => '',
			'imap_port'        => 993,
			'imap_user'        => '',
			'imap_pass'        => '',
			'dry_run'          => 1,
			'require_approval' => 1,
			'daily_limit'      => 30,
			'per_run_limit'    => 3,
			'ai_per_run'       => 5,
			'window_start'     => 10,
			'window_end'       => 18,
			'skip_weekends'    => 1,
			'wa_token'         => '',
			'wa_phone_id'      => '',
			'wa_api_version'   => 'v21.0',
			'wa_template'      => '',
			'wa_lang'          => 'en',
			'wa_params'        => 'first_name',
			'weekly_report'    => 1,
		);
	}

	public static function all() {
		$saved = get_option( self::OPTION, array() );
		$all   = wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
		foreach ( self::SECRET_CONSTANTS as $key => $const ) {
			if ( defined( $const ) && constant( $const ) ) {
				$all[ $key ] = constant( $const );
			}
		}
		return $all;
	}

	public static function get( $key ) {
		$all = self::all();
		return $all[ $key ] ?? null;
	}

	public static function is_constant( $key ) {
		return isset( self::SECRET_CONSTANTS[ $key ] ) && defined( self::SECRET_CONSTANTS[ $key ] ) && constant( self::SECRET_CONSTANTS[ $key ] );
	}

	/**
	 * Save from the settings form. Empty secret fields keep the old value.
	 */
	public static function save( array $in ) {
		$old  = get_option( self::OPTION, array() );
		$old  = is_array( $old ) ? $old : array();
		$def  = self::defaults();
		$out  = array();
		$ints = array( 'smtp_port', 'imap_port', 'daily_limit', 'per_run_limit', 'ai_per_run', 'window_start', 'window_end' );
		$bool = array( 'dry_run', 'require_approval', 'skip_weekends', 'weekly_report' );
		foreach ( $def as $key => $default ) {
			if ( in_array( $key, $bool, true ) ) {
				$out[ $key ] = empty( $in[ $key ] ) ? 0 : 1;
			} elseif ( in_array( $key, $ints, true ) ) {
				$out[ $key ] = isset( $in[ $key ] ) ? absint( $in[ $key ] ) : $default;
			} elseif ( isset( self::SECRET_CONSTANTS[ $key ] ) ) {
				$val         = isset( $in[ $key ] ) ? trim( wp_unslash( $in[ $key ] ) ) : '';
				$out[ $key ] = '' === $val ? ( $old[ $key ] ?? '' ) : $val;
			} elseif ( 'sender_email' === $key ) {
				$out[ $key ] = sanitize_email( wp_unslash( $in[ $key ] ?? '' ) );
			} elseif ( 'business_address' === $key ) {
				$out[ $key ] = sanitize_textarea_field( wp_unslash( $in[ $key ] ?? '' ) );
			} elseif ( 'model' === $key ) {
				$m           = sanitize_text_field( wp_unslash( $in[ $key ] ?? '' ) );
				$out[ $key ] = isset( self::MODELS[ $m ] ) ? $m : $default;
			} else {
				$out[ $key ] = sanitize_text_field( wp_unslash( $in[ $key ] ?? $default ) );
			}
		}
		$out['daily_limit']   = max( 1, min( 200, $out['daily_limit'] ) );
		$out['per_run_limit'] = max( 1, min( 20, $out['per_run_limit'] ) );
		$out['ai_per_run']    = max( 1, min( 30, $out['ai_per_run'] ) );
		$out['window_start']  = min( 23, $out['window_start'] );
		$out['window_end']    = max( $out['window_start'] + 1, min( 24, $out['window_end'] ) );
		update_option( self::OPTION, $out, false );
	}

	/** Problems that stop the bot from working, shown on the dashboard. */
	public static function problems() {
		$s   = self::all();
		$out = array();
		if ( ! $s['api_key'] && 'demo' !== $s['model'] ) {
			$out[] = 'Claude API key missing (Settings → Claude). To test for free, choose "Demo mode" as the model.';
		}
		if ( ! $s['sender_name'] || ! is_email( $s['sender_email'] ) ) {
			$out[] = 'Sender name / email missing (Settings → Email sending).';
		}
		if ( ! $s['business_address'] ) {
			$out[] = 'Business address missing. Every cold email must show a real postal address.';
		}
		if ( ! $s['smtp_host'] ) {
			$out[] = 'SMTP not set, so emails would go out through the hosting mail server (poor inbox rate).';
		}
		if ( ! $s['imap_host'] ) {
			$out[] = 'IMAP not set, so the bot cannot read replies.';
		}
		return $out;
	}
}
