<?php
/**
 * Contact form: [kadvia_contact_form]
 *
 * Each enquiry is saved in wp-admin (Enquiries menu) so nothing is lost if
 * email fails, and it is also emailed to the address in Customizer → Kadvia contact.
 * Spam guard: hidden honeypot field, minimum fill time, and a per-IP hourly limit.
 * No nonce on purpose: page caches on hosting would make it go stale for visitors.
 *
 * @package divyansh-pro
 */

defined( 'ABSPATH' ) || exit;

function dp_contact_services() {
	return array(
		'full'      => __( 'Full appointment setting (we book your meetings)', 'divyansh-pro' ),
		'research'  => __( 'Lead research', 'divyansh-pro' ),
		'linkedin'  => __( 'LinkedIn outreach', 'divyansh-pro' ),
		'email'     => __( 'Cold email setup', 'divyansh-pro' ),
		'whatsapp'  => __( 'WhatsApp marketing', 'divyansh-pro' ),
		'calling'   => __( 'Calling and CRM reporting', 'divyansh-pro' ),
		'not_sure'  => __( 'Not sure yet', 'divyansh-pro' ),
	);
}

/* Enquiries list in wp-admin (private, never shown on the site). */
function dp_register_enquiries() {
	register_post_type(
		'dp_enquiry',
		array(
			'labels'          => array(
				'name'          => __( 'Enquiries', 'divyansh-pro' ),
				'singular_name' => __( 'Enquiry', 'divyansh-pro' ),
				'menu_name'     => __( 'Enquiries', 'divyansh-pro' ),
				'all_items'     => __( 'All enquiries', 'divyansh-pro' ),
				'edit_item'     => __( 'Enquiry', 'divyansh-pro' ),
			),
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => true,
			'menu_position'   => 4,
			'menu_icon'       => 'dashicons-email',
			'supports'        => array( 'title', 'editor' ),
			'capability_type' => 'post',
			'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
			'map_meta_cap'    => true,
		)
	);
}
add_action( 'init', 'dp_register_enquiries' );

/* Extra columns in the Enquiries list. */
add_filter(
	'manage_dp_enquiry_posts_columns',
	static function ( $cols ) {
		return array(
			'cb'         => $cols['cb'],
			'title'      => __( 'Name and company', 'divyansh-pro' ),
			'dp_email'   => __( 'Email', 'divyansh-pro' ),
			'dp_phone'   => __( 'Phone', 'divyansh-pro' ),
			'dp_service' => __( 'Interested in', 'divyansh-pro' ),
			'date'       => $cols['date'],
		);
	}
);
add_action(
	'manage_dp_enquiry_posts_custom_column',
	static function ( $col, $post_id ) {
		if ( 'dp_email' === $col ) {
			$e = get_post_meta( $post_id, '_dp_email', true );
			echo '<a href="' . esc_url( 'mailto:' . $e ) . '">' . esc_html( $e ) . '</a>';
		} elseif ( 'dp_phone' === $col ) {
			$p = get_post_meta( $post_id, '_dp_phone', true );
			echo esc_html( $p );
			if ( $p && get_post_meta( $post_id, '_dp_wa_ok', true ) ) {
				echo ' <a href="' . esc_url( 'https://wa.me/' . preg_replace( '/\D+/', '', $p ) ) . '" target="_blank" rel="noopener">WhatsApp</a>';
			}
		} elseif ( 'dp_service' === $col ) {
			$s = dp_contact_services();
			$k = get_post_meta( $post_id, '_dp_service', true );
			echo esc_html( $s[ $k ] ?? '' );
		}
	},
	10,
	2
);

/* The form. */
function dp_contact_form_shortcode() {
	// phpcs:disable WordPress.Security.NonceVerification
	$status = isset( $_GET['dp_sent'] ) ? sanitize_key( $_GET['dp_sent'] ) : '';
	// phpcs:enable
	ob_start();
	if ( 'ok' === $status ) {
		?>
		<div class="dp-form-done" role="status" id="dp-contact-form">
			<span class="dp-form-done__icon" aria-hidden="true">✓</span>
			<h3><?php esc_html_e( 'Thank you! Your message is with us.', 'divyansh-pro' ); ?></h3>
			<p><?php esc_html_e( 'We reply within one working day. Need it faster? Message us on WhatsApp.', 'divyansh-pro' ); ?></p>
		</div>
		<?php
		return ob_get_clean();
	}
	$errors = array(
		'missing' => __( 'Please fill in your name, work email, company and message.', 'divyansh-pro' ),
		'email'   => __( 'That email address does not look right. Please check it.', 'divyansh-pro' ),
		'fast'    => __( 'That was very quick. Please check your details and send again.', 'divyansh-pro' ),
		'limit'   => __( 'Too many messages from your network. Please try again in an hour, or email us.', 'divyansh-pro' ),
		'fail'    => __( 'Sorry, something went wrong. Please email or WhatsApp us instead.', 'divyansh-pro' ),
	);
	?>
	<form class="dp-form" id="dp-contact-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php if ( isset( $errors[ $status ] ) ) : ?>
			<p class="dp-form-error" role="alert"><?php echo esc_html( $errors[ $status ] ); ?></p>
		<?php endif; ?>
		<input type="hidden" name="action" value="dp_contact">
		<input type="hidden" name="dp_t" value="<?php echo esc_attr( time() ); ?>">
		<input type="hidden" name="dp_back" value="<?php echo esc_url( get_permalink() ); ?>">
		<div class="dp-form__hp" aria-hidden="true">
			<label>Leave this empty <input type="text" name="dp_company_site" tabindex="-1" autocomplete="off"></label>
		</div>

		<div class="dp-form__row">
			<label class="dp-field"><span><?php esc_html_e( 'Your name', 'divyansh-pro' ); ?> *</span>
				<input type="text" name="dp_name" required maxlength="100" autocomplete="name">
			</label>
			<label class="dp-field"><span><?php esc_html_e( 'Work email', 'divyansh-pro' ); ?> *</span>
				<input type="email" name="dp_email" required maxlength="150" autocomplete="email">
			</label>
		</div>
		<div class="dp-form__row">
			<label class="dp-field"><span><?php esc_html_e( 'Company', 'divyansh-pro' ); ?> *</span>
				<input type="text" name="dp_company" required maxlength="150" autocomplete="organization">
			</label>
			<label class="dp-field"><span><?php esc_html_e( 'Phone / WhatsApp', 'divyansh-pro' ); ?></span>
				<input type="tel" name="dp_phone" maxlength="30" autocomplete="tel" placeholder="+91">
			</label>
		</div>
		<label class="dp-field"><span><?php esc_html_e( 'What can we help with?', 'divyansh-pro' ); ?></span>
			<select name="dp_service">
				<?php foreach ( dp_contact_services() as $k => $label ) : ?>
					<option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<label class="dp-field"><span><?php esc_html_e( 'Tell us about your business', 'divyansh-pro' ); ?> *</span>
			<textarea name="dp_message" rows="5" required maxlength="3000" placeholder="<?php esc_attr_e( 'What do you sell, and who are your best clients?', 'divyansh-pro' ); ?>"></textarea>
		</label>
		<label class="dp-check">
			<input type="checkbox" name="dp_wa_ok" value="1">
			<span><?php esc_html_e( 'You can contact me on WhatsApp about my enquiry.', 'divyansh-pro' ); ?></span>
		</label>
		<button type="submit" class="dp-pill dp-form__submit"><?php esc_html_e( 'Send message', 'divyansh-pro' ); ?></button>
		<p class="dp-form__note"><?php esc_html_e( 'We use your details only to reply to you. No spam, ever.', 'divyansh-pro' ); ?></p>
	</form>
	<?php
	return ob_get_clean();
}
add_shortcode( 'kadvia_contact_form', 'dp_contact_form_shortcode' );

/* Handle a submission (logged-in or not). */
function dp_contact_submit() {
	// phpcs:disable WordPress.Security.NonceVerification -- public form, see file header.
	$in   = wp_unslash( $_POST );
	$back = wp_validate_redirect( esc_url_raw( $in['dp_back'] ?? '' ), home_url( '/contact/' ) );
	$go   = static function ( $code ) use ( $back ) {
		wp_safe_redirect( add_query_arg( 'dp_sent', $code, $back ) . '#dp-contact-form' );
		exit;
	};

	// Bots fill the hidden field: pretend it worked.
	if ( ! empty( $in['dp_company_site'] ) ) {
		$go( 'ok' );
	}
	if ( time() - (int) ( $in['dp_t'] ?? 0 ) < 3 ) {
		$go( 'fast' );
	}
	$ip_key = 'dp_cf_' . md5( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
	$count  = (int) get_transient( $ip_key );
	if ( $count >= 5 ) {
		$go( 'limit' );
	}

	$name     = sanitize_text_field( $in['dp_name'] ?? '' );
	$email    = sanitize_email( $in['dp_email'] ?? '' );
	$company  = sanitize_text_field( $in['dp_company'] ?? '' );
	$phone    = preg_replace( '/[^0-9+\s\-]/', '', (string) ( $in['dp_phone'] ?? '' ) );
	$message  = sanitize_textarea_field( $in['dp_message'] ?? '' );
	$services = dp_contact_services();
	$service  = isset( $services[ $in['dp_service'] ?? '' ] ) ? $in['dp_service'] : 'not_sure';
	$wa_ok    = ! empty( $in['dp_wa_ok'] ) ? 1 : 0;
	// phpcs:enable

	if ( '' === $name || '' === $company || '' === trim( $message ) || '' === trim( (string) ( $in['dp_email'] ?? '' ) ) ) {
		$go( 'missing' );
	}
	if ( ! is_email( $email ) ) {
		$go( 'email' );
	}
	set_transient( $ip_key, $count + 1, HOUR_IN_SECONDS );

	$details = __( 'Name', 'divyansh-pro' ) . ': ' . $name . "\n"
		. __( 'Email', 'divyansh-pro' ) . ': ' . $email . "\n"
		. __( 'Company', 'divyansh-pro' ) . ': ' . $company . "\n"
		. __( 'Phone', 'divyansh-pro' ) . ': ' . ( $phone ? $phone : '-' ) . ( $wa_ok ? ' (OK to WhatsApp)' : '' ) . "\n"
		. __( 'Interested in', 'divyansh-pro' ) . ': ' . $services[ $service ] . "\n\n"
		. $message;

	$post_id = wp_insert_post(
		array(
			'post_type'    => 'dp_enquiry',
			'post_status'  => 'private',
			'post_title'   => $name . ' — ' . $company,
			'post_content' => $details,
			'meta_input'   => array(
				'_dp_email'   => $email,
				'_dp_phone'   => $phone,
				'_dp_service' => $service,
				'_dp_wa_ok'   => $wa_ok,
			),
		),
		true
	);

	$to     = is_email( dp_opt( 'dp_email' ) ) ? dp_opt( 'dp_email' ) : get_option( 'admin_email' );
	$mailed = wp_mail(
		$to,
		sprintf( 'New enquiry: %s (%s)', $name, $company ),
		$details . "\n\n--\n" . __( 'Reply to this email to answer them. All enquiries: ', 'divyansh-pro' ) . admin_url( 'edit.php?post_type=dp_enquiry' ),
		array( 'Reply-To: ' . str_replace( array( "\r", "\n", '<', '>' ), '', $name ) . ' <' . $email . '>' )
	);

	if ( is_wp_error( $post_id ) && ! $mailed ) {
		$go( 'fail' );
	}
	do_action( 'dp_contact_submitted', $post_id, compact( 'name', 'email', 'company', 'phone', 'service', 'message', 'wa_ok' ) );
	$go( 'ok' );
}
add_action( 'admin_post_nopriv_dp_contact', 'dp_contact_submit' );
add_action( 'admin_post_dp_contact', 'dp_contact_submit' );
