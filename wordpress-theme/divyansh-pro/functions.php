<?php
/**
 * Divyansh Pro theme setup.
 *
 * @package divyansh-pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DP_VERSION', '2.3.0' );

/* ---------- Theme supports and menus ---------- */
function dp_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'custom-logo', array( 'height' => 80, 'width' => 240, 'flex-height' => true, 'flex-width' => true ) );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'responsive-embeds' );
	register_nav_menus(
		array(
			'primary' => __( 'Header Menu', 'divyansh-pro' ),
			'footer'  => __( 'Footer Menu', 'divyansh-pro' ),
		)
	);
}
add_action( 'after_setup_theme', 'dp_setup' );

/* Elementor: let it use the full page width */
function dp_content_width() {
	$GLOBALS['content_width'] = 1160;
}
add_action( 'after_setup_theme', 'dp_content_width', 0 );

/* ---------- Fonts, styles, script ---------- */
function dp_assets() {
	wp_enqueue_style(
		'dp-fonts',
		'https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;1,9..144,500&family=Manrope:wght@400;500;600;700&display=swap',
		array(),
		null
	);
	wp_enqueue_style( 'dp-style', get_stylesheet_uri(), array( 'dp-fonts' ), DP_VERSION );
	wp_add_inline_style( 'dp-style', dp_customizer_css() );
	wp_enqueue_script( 'dp-site', get_template_directory_uri() . '/assets/site.js', array(), DP_VERSION, true );
}
add_action( 'wp_enqueue_scripts', 'dp_assets', 20 );

/* ---------- Customizer: colors, header button, contact details ---------- */
function dp_defaults() {
	return array(
		'dp_accent'     => '#2447d6',
		'dp_pink'       => '#e5307f',
		'dp_ink'        => '#0f1638',
		'dp_bg'         => '#f3f6ff',
		'dp_cta_text'   => 'Book a strategy call',
		'dp_cta_url'    => '/contact/',
		'dp_tagline'    => 'Kadvia books qualified sales meetings with the decision-makers you want to reach. You show up and close.',
		'dp_email'      => 'Divyansharora73@gmail.com',
		'dp_phone'      => '+91 87997 14396',
		'dp_whatsapp'   => '918799714396',
		'dp_location'   => 'Delhi NCR, India',
		'dp_linkedin'   => '',
		'dp_blessing'   => 'Blessings always Guruji',
	);
}

function dp_opt( $key ) {
	$d = dp_defaults();
	return get_theme_mod( $key, isset( $d[ $key ] ) ? $d[ $key ] : '' );
}

function dp_customize_register( $wp_customize ) {
	$d = dp_defaults();

	$wp_customize->add_section(
		'dp_settings',
		array(
			'title'    => __( 'Kadvia Settings', 'divyansh-pro' ),
			'priority' => 30,
		)
	);

	$colors = array(
		'dp_accent' => __( 'Main color: royal blue (buttons, links, icons)', 'divyansh-pro' ),
		'dp_pink'   => __( 'Second color: pink (labels, highlights, hover)', 'divyansh-pro' ),
		'dp_ink'    => __( 'Text color', 'divyansh-pro' ),
		'dp_bg'     => __( 'Page background', 'divyansh-pro' ),
	);
	foreach ( $colors as $key => $label ) {
		$wp_customize->add_setting( $key, array( 'default' => $d[ $key ], 'sanitize_callback' => 'sanitize_hex_color' ) );
		$wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, $key, array( 'label' => $label, 'section' => 'dp_settings' ) ) );
	}

	$texts = array(
		'dp_cta_text' => __( 'Header button text', 'divyansh-pro' ),
		'dp_cta_url'  => __( 'Header button link', 'divyansh-pro' ),
		'dp_tagline'  => __( 'Footer short intro', 'divyansh-pro' ),
		'dp_email'    => __( 'Email', 'divyansh-pro' ),
		'dp_phone'    => __( 'Phone', 'divyansh-pro' ),
		'dp_whatsapp' => __( 'WhatsApp number (digits only, with country code)', 'divyansh-pro' ),
		'dp_location' => __( 'Location', 'divyansh-pro' ),
		'dp_linkedin' => __( 'LinkedIn profile URL (optional)', 'divyansh-pro' ),
		'dp_blessing' => __( 'Blessing line (top of header and footer). Leave empty to hide.', 'divyansh-pro' ),
	);
	foreach ( $texts as $key => $label ) {
		$wp_customize->add_setting( $key, array( 'default' => $d[ $key ], 'sanitize_callback' => 'sanitize_text_field' ) );
		$wp_customize->add_control( $key, array( 'label' => $label, 'section' => 'dp_settings', 'type' => 'dp_tagline' === $key ? 'textarea' : 'text' ) );
	}
}
add_action( 'customize_register', 'dp_customize_register' );

function dp_customizer_css() {
	$accent = sanitize_hex_color( dp_opt( 'dp_accent' ) );
	$ink    = sanitize_hex_color( dp_opt( 'dp_ink' ) );
	$bg     = sanitize_hex_color( dp_opt( 'dp_bg' ) );
	$css    = ':root{';
	if ( $accent ) {
		$css .= '--dp-accent:' . $accent . ';--dp-accent-soft:color-mix(in srgb,' . $accent . ' 12%,#fff);';
	}
	$pink = sanitize_hex_color( dp_opt( 'dp_pink' ) );
	if ( $pink ) {
		$css .= '--dp-pink:' . $pink . ';';
	}
	if ( $ink ) {
		$css .= '--dp-ink:' . $ink . ';';
	}
	if ( $bg ) {
		$css .= '--dp-bg:' . $bg . ';';
	}
	return $css . '}';
}

/* ---------- Menu fallback: shows the 5 pages until a menu is created ---------- */
function dp_menu_fallback() {
	$links = array(
		'/'            => __( 'Home', 'divyansh-pro' ),
		'/about/'      => __( 'About', 'divyansh-pro' ),
		'/services/'   => __( 'Services', 'divyansh-pro' ),
		'/industries/' => __( 'Industries', 'divyansh-pro' ),
		'/contact/'    => __( 'Contact', 'divyansh-pro' ),
	);
	echo '<ul>';
	foreach ( $links as $path => $label ) {
		echo '<li><a href="' . esc_url( home_url( $path ) ) . '">' . esc_html( $label ) . '</a></li>';
	}
	echo '</ul>';
}

/* Turns a relative link like /contact/ into a full site link */
function dp_link( $url ) {
	if ( 0 === strpos( $url, '/' ) ) {
		return home_url( $url );
	}
	return $url;
}

/* ---------- One-click page setup (creates the 5 Elementor pages) ---------- */
require_once get_template_directory() . '/inc/auto-setup.php';

/* ---------- Browser tab icon: Kadvia icon until a Site Icon is set in Customize ---------- */
function dp_default_favicon() {
	if ( has_site_icon() ) {
		return;
	}
	$dir = get_template_directory_uri() . '/assets/';
	echo '<link rel="icon" type="image/png" sizes="32x32" href="' . esc_url( $dir . 'kadvia-icon-32.png' ) . '">' . "\n";
	echo '<link rel="icon" type="image/png" sizes="512x512" href="' . esc_url( $dir . 'kadvia-icon-512.png' ) . '">' . "\n";
	echo '<link rel="apple-touch-icon" href="' . esc_url( $dir . 'kadvia-icon-180.png' ) . '">' . "\n";
}
add_action( 'wp_head', 'dp_default_favicon', 5 );
add_action( 'admin_head', 'dp_default_favicon', 5 );
