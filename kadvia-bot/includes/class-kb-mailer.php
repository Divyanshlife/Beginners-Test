<?php
/**
 * Sends email through wp_mail with the bot's SMTP account, threading headers,
 * a real postal address, and a one-click unsubscribe link (law in the US, UK, EU and good practice everywhere).
 *
 * @package kadvia-bot
 */

defined( 'ABSPATH' ) || exit;

class KB_Mailer {

	/** Settings for the email being sent right now (null when another plugin sends mail). */
	private static $current = null;

	public static function init() {
		add_action( 'phpmailer_init', array( __CLASS__, 'configure' ), 99 );
		add_action( 'init', array( __CLASS__, 'handle_unsubscribe' ) );
	}

	public static function unsubscribe_url( $lead ) {
		return add_query_arg( 'kb_unsub', rawurlencode( $lead->token ), home_url( '/' ) );
	}

	/**
	 * Send one email to a lead.
	 *
	 * @param object $lead        Lead row.
	 * @param object $campaign    Campaign row.
	 * @param string $subject     Subject.
	 * @param string $body        Plain-text body without signature.
	 * @param string $in_reply_to Message-ID to thread under, or ''.
	 * @return string|WP_Error The Message-ID we used, or an error.
	 */
	public static function send( $lead, $campaign, $subject, $body, $in_reply_to = '' ) {
		$s = KB_Settings::all();
		if ( ! is_email( $s['sender_email'] ) ) {
			return new WP_Error( 'sender', 'Sender email is not set.' );
		}
		if ( ! $s['business_address'] ) {
			return new WP_Error( 'address', 'Business address is not set (needed in every cold email).' );
		}
		$unsub = self::unsubscribe_url( $lead );
		$text  = rtrim( $body ) . "\n\n" . trim( (string) $campaign->signature ) . "\n\n--\n"
			. trim( preg_replace( '/\s*\n\s*/', ', ', $s['business_address'] ) ) . "\n"
			. "Not the right fit? Reply \"stop\" or unsubscribe here: " . $unsub . "\n";

		$domain     = substr( strrchr( $s['sender_email'], '@' ), 1 );
		$message_id = '<kb.' . $lead->id . '.' . wp_generate_password( 12, false ) . '@' . $domain . '>';

		$headers = array(
			'From: ' . self::header_safe( $s['sender_name'] ) . ' <' . $s['sender_email'] . '>',
			'Reply-To: ' . $s['sender_email'],
			'Content-Type: text/plain; charset=UTF-8',
			'List-Unsubscribe: <' . $unsub . '>, <mailto:' . $s['sender_email'] . '?subject=unsubscribe>',
			'List-Unsubscribe-Post: List-Unsubscribe=One-Click',
		);
		if ( $in_reply_to ) {
			$headers[] = 'In-Reply-To: ' . $in_reply_to;
			$headers[] = 'References: ' . $in_reply_to;
		}

		self::$current = array( 'message_id' => $message_id );
		$error         = null;
		$catch         = static function ( $wp_error ) use ( &$error ) {
			$error = $wp_error;
		};
		add_action( 'wp_mail_failed', $catch );
		$ok = wp_mail( $lead->email, self::header_safe( $subject ), $text, $headers );
		remove_action( 'wp_mail_failed', $catch );
		self::$current = null;

		if ( ! $ok ) {
			return $error instanceof WP_Error ? $error : new WP_Error( 'mail', 'wp_mail failed.' );
		}
		return $message_id;
	}

	/** Plain notification email (reports to clients). Uses the same SMTP account. */
	public static function send_plain( $to, $subject, $text ) {
		$s             = KB_Settings::all();
		self::$current = array( 'message_id' => '' );
		$ok            = wp_mail(
			$to,
			self::header_safe( $subject ),
			$text,
			array(
				'From: ' . self::header_safe( $s['sender_name'] ) . ' <' . $s['sender_email'] . '>',
				'Content-Type: text/plain; charset=UTF-8',
			)
		);
		self::$current = null;
		return $ok;
	}

	private static function header_safe( $text ) {
		return trim( str_replace( array( "\r", "\n" ), ' ', (string) $text ) );
	}

	/**
	 * Point PHPMailer at the bot's SMTP account, only for mail the bot sends.
	 *
	 * @param PHPMailer\PHPMailer\PHPMailer $phpmailer Mailer.
	 */
	public static function configure( $phpmailer ) {
		if ( null === self::$current ) {
			return;
		}
		$s = KB_Settings::all();
		if ( self::$current['message_id'] ) {
			$phpmailer->MessageID = self::$current['message_id'];
		}
		if ( $s['smtp_host'] ) {
			$phpmailer->isSMTP();
			$phpmailer->Host       = $s['smtp_host'];
			$phpmailer->Port       = (int) $s['smtp_port'];
			$phpmailer->SMTPAuth   = (bool) $s['smtp_user'];
			$phpmailer->Username   = $s['smtp_user'];
			$phpmailer->Password   = $s['smtp_pass'];
			$phpmailer->SMTPSecure = in_array( $s['smtp_secure'], array( 'tls', 'ssl' ), true ) ? $s['smtp_secure'] : '';
			$phpmailer->Timeout    = 30;
		}
		$phpmailer->Sender = $s['sender_email'];
	}

	/** ?kb_unsub=TOKEN link from the email footer. */
	public static function handle_unsubscribe() {
		if ( empty( $_GET['kb_unsub'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		global $wpdb;
		$token = sanitize_text_field( wp_unslash( $_GET['kb_unsub'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$lead  = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . KB_DB::t( 'leads' ) . ' WHERE token = %s', $token ) );
		if ( $lead ) {
			KB_Engine::unsubscribe( $lead, 'link' );
		}
		// One-click unsubscribe from mail apps posts here and only needs a 200.
		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			status_header( 200 );
			exit;
		}
		wp_die(
			esc_html__( 'You have been unsubscribed. You will not get more emails from us.', 'kadvia-bot' ),
			esc_html__( 'Unsubscribed', 'kadvia-bot' ),
			array( 'response' => 200 )
		);
	}
}
