<?php
/**
 * Divyansh Pro theme setup.
 *
 * @package divyansh-pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DP_VERSION', '1.0.0' );

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
		'dp_accent'     => '#0f6e64',
		'dp_ink'        => '#0e1a1f',
		'dp_bg'         => '#f4f6f5',
		'dp_cta_text'   => 'Book a 15-min call',
		'dp_cta_url'    => '/contact/',
		'dp_tagline'    => 'I help mid-market companies sell more and protect margin with apps, automations and AI.',
		'dp_email'      => 'Div.arora@zoxima.com',
		'dp_phone'      => '+91 87997 14396',
		'dp_whatsapp'   => '918799714396',
		'dp_location'   => 'Delhi NCR, India',
		'dp_linkedin'   => '',
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
			'title'    => __( 'Divyansh Pro Settings', 'divyansh-pro' ),
			'priority' => 30,
		)
	);

	$colors = array(
		'dp_accent' => __( 'Accent color (links, small labels, icons)', 'divyansh-pro' ),
		'dp_ink'    => __( 'Dark color (text, buttons, footer)', 'divyansh-pro' ),
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
		'/solutions/'  => __( 'Solutions', 'divyansh-pro' ),
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
