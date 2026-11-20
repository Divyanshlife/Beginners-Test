<?php
/**
 * WhatsApp Business Cloud API (Meta). Sends an approved template message
 * to leads who opted in. First messages to a person must be templates; Meta
 * blocks free text until they reply.
 *
 * @package kadvia-bot
 */

defined( 'ABSPATH' ) || exit;

class KB_WhatsApp {

	public static function ready() {
		$s = KB_Settings::all();
		return $s['wa_token'] && $s['wa_phone_id'] && $s['wa_template'];
	}

	/** Text shown in the approval screen: template name and the values filled in. */
	public static function preview( $lead ) {
		$s = KB_Settings::all();
		return 'Template: ' . $s['wa_template'] . ' (' . $s['wa_lang'] . ")\nValues: " . implode( ' | ', self::params( $lead ) );
	}

	private static function params( $lead ) {
		$out = array();
		foreach ( array_filter( array_map( 'trim', explode( ',', (string) KB_Settings::get( 'wa_params' ) ) ) ) as $field ) {
			$val   = isset( $lead->$field ) ? trim( (string) $lead->$field ) : '';
			$out[] = '' === $val ? ( 'first_name' === $field ? 'there' : '-' ) : $val;
		}
		return $out;
	}

	/**
	 * @return string|WP_Error WhatsApp message id or error.
	 */
	public static function send( $lead ) {
		$s = KB_Settings::all();
		if ( ! self::ready() ) {
			return new WP_Error( 'wa', 'WhatsApp is not set up (Settings → WhatsApp).' );
		}
		if ( ! $lead->wa_opt_in ) {
			return new WP_Error( 'optin', 'This lead has not opted in to WhatsApp.' );
		}
		$to = preg_replace( '/\D/', '', $lead->phone );
		if ( strlen( $to ) < 10 ) {
			return new WP_Error( 'phone', 'Phone number needs the country code, like 9198xxxxxxxx.' );
		}
		if ( 10 === strlen( $to ) ) {
			$to = '91' . $to; // Indian mobile without country code.
		}
		$template = array(
			'name'     => $s['wa_template'],
			'language' => array( 'code' => $s['wa_lang'] ),
		);
		$params   = self::params( $lead );
		if ( $params ) {
			$template['components'] = array(
				array(
					'type'       => 'body',
					'parameters' => array_map(
						static fn( $p ) => array(
							'type' => 'text',
							'text' => $p,
						),
						$params
					),
				),
			);
		}
		$res = wp_remote_post(
			'https://graph.facebook.com/' . rawurlencode( $s['wa_api_version'] ) . '/' . rawurlencode( $s['wa_phone_id'] ) . '/messages',
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Bearer ' . $s['wa_token'],
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'messaging_product' => 'whatsapp',
						'to'                => $to,
						'type'              => 'template',
						'template'          => $template,
					)
				),
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$data = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( ! empty( $data['messages'][0]['id'] ) ) {
			return $data['messages'][0]['id'];
		}
		return new WP_Error( 'wa', 'WhatsApp error: ' . ( $data['error']['message'] ?? 'HTTP ' . wp_remote_retrieve_response_code( $res ) ) );
	}
}
