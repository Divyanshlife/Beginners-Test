<?php
/**
 * Divyansh Pro theme setup.
 *
 * @package divyansh-pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DP_VERSION', '2.6.0' );

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
		'dp_blog_title' => 'Playbooks for <em>booking more meetings.</em>',
		'dp_blog_intro' => 'Practical guides on outbound sales, cold email, LinkedIn and appointment setting for B2B teams.',
		'dp_ad_code'    => '',
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

	/* Blog */
	$wp_customize->add_section( 'dp_blog', array( 'title' => __( 'Kadvia Blog and Ads', 'divyansh-pro' ), 'priority' => 31 ) );
	$wp_customize->add_setting( 'dp_blog_title', array( 'default' => $d['dp_blog_title'], 'sanitize_callback' => 'dp_sanitize_em' ) );
	$wp_customize->add_control( 'dp_blog_title', array( 'label' => __( 'Blog page heading (wrap words in <em></em> for the colour effect)', 'divyansh-pro' ), 'section' => 'dp_blog', 'type' => 'text' ) );
	$wp_customize->add_setting( 'dp_blog_intro', array( 'default' => $d['dp_blog_intro'], 'sanitize_callback' => 'sanitize_text_field' ) );
	$wp_customize->add_control( 'dp_blog_intro', array( 'label' => __( 'Blog page intro line', 'divyansh-pro' ), 'section' => 'dp_blog', 'type' => 'textarea' ) );
	$wp_customize->add_setting( 'dp_ad_code', array( 'default' => '', 'sanitize_callback' => 'dp_sanitize_ad_code' ) );
	$wp_customize->add_control(
		'dp_ad_code',
		array(
			'label'       => __( 'Ad code (for example Google AdSense)', 'divyansh-pro' ),
			'description' => __( 'Paste the ad unit code here. It shows at the end of every blog post and on the Blog page. Leave empty for no ads.', 'divyansh-pro' ),
			'section'     => 'dp_blog',
			'type'        => 'textarea',
		)
	);
}

function dp_sanitize_em( $value ) {
	return wp_kses( $value, array( 'em' => array() ) );
}

/* Ad code may contain <script>, so only admins who may post raw HTML can save it as is */
function dp_sanitize_ad_code( $value ) {
	return current_user_can( 'unfiltered_html' ) ? $value : wp_kses_post( $value );
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
		'/blog/'       => __( 'Blog', 'divyansh-pro' ),
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

/* ---------- One-click page setup (creates the Elementor pages and the blog) ---------- */
require_once get_template_directory() . '/inc/starter-posts.php';
require_once get_template_directory() . '/inc/auto-setup.php';

/* =====================================================================
   Blog helpers
   ===================================================================== */

/* "5 min read" */
function dp_reading_time( $post = null ) {
	$words = str_word_count( wp_strip_all_tags( get_post_field( 'post_content', $post ) ) );
	/* translators: %d: minutes */
	return sprintf( __( '%d min read', 'divyansh-pro' ), max( 1, (int) ceil( $words / 220 ) ) );
}

/* Ad space from Customize > Kadvia Blog and Ads */
function dp_ad_slot( $where = 'post' ) {
	$code = dp_opt( 'dp_ad_code' );
	if ( ! $code ) {
		return;
	}
	echo '<aside class="dp-ad dp-ad--' . esc_attr( $where ) . '"><span class="dp-ad__label">' . esc_html__( 'Advertisement', 'divyansh-pro' ) . '</span>';
	echo $code; // phpcs:ignore WordPress.Security.EscapeOutput -- saved only by admins with unfiltered_html.
	echo '</aside>';
}

/* Lead box at the end of blog pages: turns readers into Kadvia enquiries */
function dp_blog_cta() {
	?>
	<section class="dp-blog-cta dp-reveal">
		<div>
			<p class="dp-kicker"><?php esc_html_e( 'Done-for-you outbound', 'divyansh-pro' ); ?></p>
			<h2><?php esc_html_e( 'Want these meetings without doing the work?', 'divyansh-pro' ); ?></h2>
			<p><?php esc_html_e( 'Kadvia researches your buyers, starts the conversations and books qualified meetings on your calendar.', 'divyansh-pro' ); ?></p>
		</div>
		<div class="dp-blog-cta__btns">
			<a class="dp-pill dp-pill--light" href="<?php echo esc_url( dp_link( dp_opt( 'dp_cta_url' ) ) ); ?>"><?php echo esc_html( dp_opt( 'dp_cta_text' ) ); ?></a>
			<?php $wa = preg_replace( '/\D+/', '', dp_opt( 'dp_whatsapp' ) ); ?>
			<?php if ( $wa ) : ?>
				<a class="dp-pill dp-pill--ghost" href="<?php echo esc_url( 'https://wa.me/' . $wa ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'WhatsApp us', 'divyansh-pro' ); ?></a>
			<?php endif; ?>
		</div>
	</section>
	<?php
}

/* [kadvia_latest_posts count="3"] : latest articles as cards (used on the Home page) */
function dp_latest_posts_shortcode( $atts ) {
	$atts = shortcode_atts( array( 'count' => 3 ), $atts, 'kadvia_latest_posts' );
	$q    = new WP_Query( array( 'posts_per_page' => max( 1, (int) $atts['count'] ), 'ignore_sticky_posts' => true, 'post_status' => 'publish' ) );
	if ( ! $q->have_posts() ) {
		return '';
	}
	ob_start();
	echo '<div class="dp-post-grid">';
	while ( $q->have_posts() ) {
		$q->the_post();
		get_template_part( 'template-parts/post-card' );
	}
	echo '</div>';
	wp_reset_postdata();
	return ob_get_clean();
}
add_shortcode( 'kadvia_latest_posts', 'dp_latest_posts_shortcode' );

/* Shorter "[...]" on excerpts */
function dp_excerpt_more() {
	return '&hellip;';
}
add_filter( 'excerpt_more', 'dp_excerpt_more' );

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
