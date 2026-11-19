<?php
/**
 * Site footer.
 *
 * @package divyansh-pro
 */

$dp_email = dp_opt( 'dp_email' );
$dp_phone = dp_opt( 'dp_phone' );
$dp_wa    = preg_replace( '/\D+/', '', dp_opt( 'dp_whatsapp' ) );
$dp_li    = dp_opt( 'dp_linkedin' );
?>
</main>

<footer class="dp-footer">
	<div class="dp-container">
		<div class="dp-footer__grid">
			<div>
				<p class="dp-footer__title"><?php bloginfo( 'name' ); ?></p>
				<p><?php echo esc_html( dp_opt( 'dp_tagline' ) ); ?></p>
			</div>
			<div>
				<h4><?php esc_html_e( 'Pages', 'divyansh-pro' ); ?></h4>
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'footer',
						'container'      => false,
						'depth'          => 1,
						'fallback_cb'    => 'dp_menu_fallback',
					)
				);
				?>
			</div>
			<div>
				<h4><?php esc_html_e( 'Contact', 'divyansh-pro' ); ?></h4>
				<ul>
					<?php if ( $dp_email ) : ?>
						<li><a href="mailto:<?php echo esc_attr( $dp_email ); ?>"><?php echo esc_html( $dp_email ); ?></a></li>
					<?php endif; ?>
					<?php if ( $dp_phone ) : ?>
						<li><a href="tel:<?php echo esc_attr( preg_replace( '/[^\d+]/', '', $dp_phone ) ); ?>"><?php echo esc_html( $dp_phone ); ?></a></li>
					<?php endif; ?>
					<?php if ( $dp_wa ) : ?>
						<li><a href="<?php echo esc_url( 'https://wa.me/' . $dp_wa ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'WhatsApp', 'divyansh-pro' ); ?></a></li>
					<?php endif; ?>
					<?php if ( $dp_li ) : ?>
						<li><a href="<?php echo esc_url( $dp_li ); ?>" target="_blank" rel="noopener">LinkedIn</a></li>
					<?php endif; ?>
					<li><?php echo esc_html( dp_opt( 'dp_location' ) ); ?></li>
				</ul>
			</div>
		</div>
		<?php if ( dp_opt( 'dp_blessing' ) ) : ?>
			<div class="dp-blessing dp-blessing--footer"><span class="dp-blessing__orn" aria-hidden="true">&#10022;</span> <?php echo esc_html( dp_opt( 'dp_blessing' ) ); ?> <span class="dp-blessing__orn" aria-hidden="true">&#10022;</span></div>
		<?php endif; ?>
		<div class="dp-footer__bottom">
			<span>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?></span>
			<span><?php esc_html_e( 'Built with WordPress and Elementor', 'divyansh-pro' ); ?></span>
		</div>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
