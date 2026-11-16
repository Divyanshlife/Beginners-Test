<?php
/**
 * Site header.
 *
 * @package divyansh-pro
 */
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link screen-reader-text" href="#content"><?php esc_html_e( 'Skip to content', 'divyansh-pro' ); ?></a>

<header class="dp-header" id="dp-header">
	<div class="dp-container dp-header__row">
		<?php if ( has_custom_logo() ) : ?>
			<?php the_custom_logo(); ?>
		<?php else : ?>
			<a class="dp-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
				<span class="dp-brand__mark" aria-hidden="true"><?php echo esc_html( mb_substr( get_bloginfo( 'name' ), 0, 1 ) ); ?></span>
				<span class="dp-brand__name"><?php bloginfo( 'name' ); ?></span>
			</a>
		<?php endif; ?>

		<button class="dp-menu-toggle" aria-controls="dp-nav" aria-expanded="false"><?php esc_html_e( 'Menu', 'divyansh-pro' ); ?></button>

		<nav class="dp-nav" id="dp-nav" aria-label="<?php esc_attr_e( 'Main menu', 'divyansh-pro' ); ?>">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'container'      => false,
					'depth'          => 1,
					'fallback_cb'    => 'dp_menu_fallback',
				)
			);
			?>
		</nav>

		<?php if ( dp_opt( 'dp_cta_text' ) ) : ?>
			<a class="dp-pill" href="<?php echo esc_url( dp_link( dp_opt( 'dp_cta_url' ) ) ); ?>"><?php echo esc_html( dp_opt( 'dp_cta_text' ) ); ?></a>
		<?php endif; ?>
	</div>
</header>

<main id="content" class="dp-main">
