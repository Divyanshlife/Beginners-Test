<?php
/**
 * One-time automatic setup: creates the 5 Elementor pages, fills them with
 * the designs from /elementor/*.json and sets Home as the front page.
 *
 * Runs once in wp-admin after Elementor is active. Pages that already have
 * an Elementor design are left untouched, so nothing you edited is overwritten.
 *
 * @package divyansh-pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function dp_setup_pages_list() {
	return array(
		'home'       => array( 'title' => 'Home', 'file' => 'home.json' ),
		'about'      => array( 'title' => 'About', 'file' => 'about.json' ),
		'services'   => array( 'title' => 'Services', 'file' => 'services.json' ),
		'industries' => array( 'title' => 'Industries', 'file' => 'industries.json' ),
		'contact'    => array( 'title' => 'Contact', 'file' => 'contact.json' ),
	);
}

/* Saves an Elementor design; {{THEME}} in the design becomes this theme's URL (for the illustrations) */
function dp_save_elementor_data( $page_id, $content ) {
	$json = wp_json_encode( $content );
	$json = str_replace( '{{THEME}}', esc_url_raw( get_template_directory_uri() ), $json );
	update_post_meta( $page_id, '_elementor_data', wp_slash( $json ) );
}

function dp_run_setup() {
	$done = array();

	foreach ( dp_setup_pages_list() as $slug => $page ) {
		$file = get_template_directory() . '/elementor/' . $page['file'];
		if ( ! file_exists( $file ) ) {
			continue;
		}
		$tpl = json_decode( file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( empty( $tpl['content'] ) ) {
			continue;
		}

		$existing = get_page_by_path( $slug, OBJECT, 'page' );
		if ( $existing ) {
			$page_id = $existing->ID;
			// Already designed with Elementor: keep the user's work.
			if ( get_post_meta( $page_id, '_elementor_data', true ) ) {
				$done[ $slug ] = $page_id;
				continue;
			}
			if ( 'publish' !== $existing->post_status ) {
				wp_update_post( array( 'ID' => $page_id, 'post_status' => 'publish' ) );
			}
		} else {
			$page_id = wp_insert_post(
				array(
					'post_title'  => $page['title'],
					'post_name'   => $slug,
					'post_status' => 'publish',
					'post_type'   => 'page',
				)
			);
			if ( ! $page_id || is_wp_error( $page_id ) ) {
				continue;
			}
		}

		update_post_meta( $page_id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $page_id, '_elementor_template_type', 'wp-page' );
		update_post_meta( $page_id, '_wp_page_template', 'elementor_header_footer' );
		update_post_meta( $page_id, '_elementor_page_settings', array( 'hide_title' => 'yes' ) );
		if ( defined( 'ELEMENTOR_VERSION' ) ) {
			update_post_meta( $page_id, '_elementor_version', ELEMENTOR_VERSION );
		}
		dp_save_elementor_data( $page_id, $tpl['content'] );

		$done[ $slug ] = $page_id;
	}

	// Home as the front page (only if the site still shows latest posts).
	if ( ! empty( $done['home'] ) && 'page' !== get_option( 'show_on_front' ) ) {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $done['home'] );
	}

	// Blog page and starter articles.
	dp_create_blog();

	// Pretty links like /about/ so the buttons work.
	if ( '' === get_option( 'permalink_structure' ) ) {
		update_option( 'permalink_structure', '/%postname%/' );
	}
	flush_rewrite_rules();

	if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
		\Elementor\Plugin::$instance->files_manager->clear_cache();
	}

	return $done;
}

/* Run automatically once, as soon as an admin opens wp-admin with Elementor active */
function dp_maybe_auto_setup() {
	if ( ! current_user_can( 'manage_options' ) || wp_doing_ajax() ) {
		return;
	}
	if ( ! did_action( 'elementor/loaded' ) ) {
		return;
	}
	if ( get_option( 'dp_setup_done' ) ) {
		return;
	}
	$done = dp_run_setup();
	update_option( 'dp_setup_done', time() );
	set_transient( 'dp_setup_notice', count( $done ), 60 );
}
add_action( 'admin_init', 'dp_maybe_auto_setup' );

/* Run setup again from Appearance > Divyansh Pro Setup */
function dp_setup_menu() {
	add_theme_page( 'Kadvia Setup', 'Kadvia Setup', 'manage_options', 'dp-setup', 'dp_setup_screen' );
}
add_action( 'admin_menu', 'dp_setup_menu' );

function dp_setup_screen() {
	$ran = false;
	if ( isset( $_POST['dp_run_setup'] ) && check_admin_referer( 'dp_run_setup' ) ) {
		$done = dp_run_setup();
		$ran  = true;
	}
	echo '<div class="wrap"><h1>Kadvia Setup</h1>';
	if ( ! did_action( 'elementor/loaded' ) ) {
		echo '<div class="notice notice-warning"><p>Please install and activate the <strong>Elementor</strong> plugin first.</p></div>';
	}
	if ( $ran ) {
		echo '<div class="notice notice-success"><p>Done. ' . esc_html( count( $done ) ) . ' pages are ready.</p></div>';
	}
	echo '<p>This creates any missing pages (Home, About, Services, Industries, Contact) with their design, and sets Home as your front page. Pages you already designed in Elementor are not changed.</p>';
	echo '<form method="post">';
	wp_nonce_field( 'dp_run_setup' );
	echo '<p><button class="button button-primary" name="dp_run_setup" value="1">Create my pages</button></p></form>';
	echo '<h2>Your pages</h2><ul>';
	foreach ( dp_setup_pages_list() as $slug => $page ) {
		$p = get_page_by_path( $slug, OBJECT, 'page' );
		if ( $p ) {
			echo '<li><strong>' . esc_html( $page['title'] ) . '</strong>: <a href="' . esc_url( get_permalink( $p ) ) . '" target="_blank">View</a> | <a href="' . esc_url( admin_url( 'post.php?post=' . $p->ID . '&action=elementor' ) ) . '">Edit with Elementor</a></li>';
		} else {
			echo '<li><strong>' . esc_html( $page['title'] ) . '</strong>: not created yet</li>';
		}
	}
	echo '</ul></div>';
}

function dp_setup_admin_notice() {
	$count = get_transient( 'dp_setup_notice' );
	if ( false === $count ) {
		return;
	}
	delete_transient( 'dp_setup_notice' );
	echo '<div class="notice notice-success is-dismissible"><p><strong>Kadvia:</strong> your ' . esc_html( $count ) . ' pages are ready. <a href="' . esc_url( home_url( '/' ) ) . '" target="_blank">View site</a> | <a href="' . esc_url( admin_url( 'themes.php?page=dp-setup' ) ) . '">Setup page</a></p></div>';
}
add_action( 'admin_notices', 'dp_setup_admin_notice' );

/* =====================================================================
   One-time update (v2): site name, demo clean-up, Contact email,
   remove the Proof section from Home. Runs once per site.
   ===================================================================== */

/* True if this Elementor element (or anything inside it) is a heading titled $title */
function dp_el_has_heading( $el, $title ) {
	if ( isset( $el['widgetType'], $el['settings']['title'] ) && 'heading' === $el['widgetType'] && $title === $el['settings']['title'] ) {
		return true;
	}
	if ( ! empty( $el['elements'] ) ) {
		foreach ( $el['elements'] as $child ) {
			if ( dp_el_has_heading( $child, $title ) ) {
				return true;
			}
		}
	}
	return false;
}

function dp_upgrade_v2() {
	if ( ! current_user_can( 'manage_options' ) || wp_doing_ajax() || get_option( 'dp_upgrade_v2' ) ) {
		return;
	}
	$log = array();

	// 1. Site name and tagline.
	if ( 'Divyansh Arora' !== get_option( 'blogname' ) ) {
		update_option( 'blogname', 'Divyansh Arora' );
		$log[] = 'Site name set to Divyansh Arora';
	}
	$tagline = get_option( 'blogdescription' );
	if ( '' === $tagline || 'Just another WordPress site' === $tagline ) {
		update_option( 'blogdescription', 'Sales and Growth Consultant' );
		$log[] = 'Tagline set';
	}

	// 2. Demo content to Trash (can be restored from Trash).
	$hello = get_page_by_path( 'hello-world', OBJECT, 'post' );
	if ( $hello && 'trash' !== $hello->post_status ) {
		wp_trash_post( $hello->ID );
		$log[] = '"Hello world!" moved to Trash';
	}
	$sample = get_page_by_path( 'sample-page', OBJECT, 'page' );
	if ( $sample && 'trash' !== $sample->post_status ) {
		wp_trash_post( $sample->ID );
		$log[] = '"Sample Page" moved to Trash';
	}

	// 3. Contact page: new email.
	$contact = get_page_by_path( 'contact', OBJECT, 'page' );
	if ( $contact ) {
		$data = get_post_meta( $contact->ID, '_elementor_data', true );
		if ( is_string( $data ) && false !== stripos( $data, 'Div.arora@zoxima.com' ) ) {
			$data = str_ireplace( 'Div.arora@zoxima.com', 'Divyansharora73@gmail.com', $data );
			update_post_meta( $contact->ID, '_elementor_data', wp_slash( $data ) );
			$log[] = 'Contact page email updated';
		}
	}

	// 4. Home page: remove the Proof section.
	$home = get_page_by_path( 'home', OBJECT, 'page' );
	if ( $home ) {
		$data = json_decode( (string) get_post_meta( $home->ID, '_elementor_data', true ), true );
		if ( is_array( $data ) ) {
			$kept = array();
			foreach ( $data as $section ) {
				if ( dp_el_has_heading( $section, 'Proof' ) ) {
					continue;
				}
				$kept[] = $section;
			}
			if ( count( $kept ) !== count( $data ) ) {
				update_post_meta( $home->ID, '_elementor_data', wp_slash( wp_json_encode( $kept ) ) );
				$log[] = 'Proof section removed from Home';
			}
		}
	}

	if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
		\Elementor\Plugin::$instance->files_manager->clear_cache();
	}

	update_option( 'dp_upgrade_v2', time() );
	set_transient( 'dp_upgrade_v2_log', $log, 120 );
}
add_action( 'admin_init', 'dp_upgrade_v2', 20 );

function dp_upgrade_v2_notice() {
	$log = get_transient( 'dp_upgrade_v2_log' );
	if ( false === $log ) {
		return;
	}
	delete_transient( 'dp_upgrade_v2_log' );
	echo '<div class="notice notice-success is-dismissible"><p><strong>Kadvia update done:</strong></p><ul style="list-style:disc;margin-left:20px">';
	if ( empty( $log ) ) {
		echo '<li>Everything was already up to date.</li>';
	}
	foreach ( $log as $line ) {
		echo '<li>' . esc_html( $line ) . '</li>';
	}
	echo '</ul><p><a href="' . esc_url( home_url( '/' ) ) . '" target="_blank">View site</a></p></div>';
}
add_action( 'admin_notices', 'dp_upgrade_v2_notice' );

/* =====================================================================
   One-time update (v3): rebrand to Kadvia and load the new page designs.
   Replaces the content of the 5 pages with the Kadvia designs.
   ===================================================================== */
function dp_upgrade_v3() {
	if ( ! current_user_can( 'manage_options' ) || wp_doing_ajax() || get_option( 'dp_upgrade_v3' ) ) {
		return;
	}
	if ( ! did_action( 'elementor/loaded' ) ) {
		return; // Wait until Elementor is active.
	}
	$log = array();

	update_option( 'blogname', 'Kadvia' );
	update_option( 'blogdescription', 'Your way to booked meetings' );
	$log[] = 'Site name set to Kadvia, tagline "Your way to booked meetings"';

	// The old Solutions page becomes Services.
	$old = get_page_by_path( 'solutions', OBJECT, 'page' );
	if ( $old && ! get_page_by_path( 'services', OBJECT, 'page' ) ) {
		wp_update_post( array( 'ID' => $old->ID, 'post_title' => 'Services', 'post_name' => 'services' ) );
		$log[] = 'Solutions page renamed to Services';
	}

	foreach ( dp_setup_pages_list() as $slug => $page ) {
		$file = get_template_directory() . '/elementor/' . $page['file'];
		$tpl  = file_exists( $file ) ? json_decode( file_get_contents( $file ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( empty( $tpl['content'] ) ) {
			continue;
		}
		$p = get_page_by_path( $slug, OBJECT, 'page' );
		if ( $p ) {
			$page_id = $p->ID;
			if ( 'publish' !== $p->post_status ) {
				wp_update_post( array( 'ID' => $page_id, 'post_status' => 'publish' ) );
			}
		} else {
			$page_id = wp_insert_post( array( 'post_title' => $page['title'], 'post_name' => $slug, 'post_status' => 'publish', 'post_type' => 'page' ) );
			if ( ! $page_id || is_wp_error( $page_id ) ) {
				continue;
			}
		}
		update_post_meta( $page_id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $page_id, '_elementor_template_type', 'wp-page' );
		update_post_meta( $page_id, '_wp_page_template', 'elementor_header_footer' );
		update_post_meta( $page_id, '_elementor_page_settings', array( 'hide_title' => 'yes' ) );
		if ( defined( 'ELEMENTOR_VERSION' ) ) {
			update_post_meta( $page_id, '_elementor_version', ELEMENTOR_VERSION );
		}
		dp_save_elementor_data( $page_id, $tpl['content'] );
		$log[] = $page['title'] . ' page updated with the Kadvia design';

		if ( 'home' === $slug ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $page_id );
		}
	}

	flush_rewrite_rules();
	if ( isset( \Elementor\Plugin::$instance->files_manager ) ) {
		\Elementor\Plugin::$instance->files_manager->clear_cache();
	}
	update_option( 'dp_upgrade_v3', time() );
	update_option( 'dp_upgrade_v2', time() ); // v2 changes are included here.
	set_transient( 'dp_upgrade_v2_log', $log, 120 );
}
add_action( 'admin_init', 'dp_upgrade_v3', 15 );

/* =====================================================================
   Blog: Blog page, categories, 6 starter articles with cover images.
   Safe to run again: anything that already exists is left alone.
   ===================================================================== */

/* Copies a theme image into the Media Library once and returns its ID */
function dp_theme_image_attachment( $file, $parent_id = 0 ) {
	$existing = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'meta_key' => '_dp_src', 'meta_value' => $file, 'numberposts' => 1, 'fields' => 'ids' ) );
	if ( $existing ) {
		return (int) $existing[0];
	}
	$path = get_template_directory() . '/assets/blog/' . $file;
	if ( ! file_exists( $path ) ) {
		return 0;
	}
	$upload = wp_upload_bits( $file, null, file_get_contents( $path ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( ! empty( $upload['error'] ) ) {
		return 0;
	}
	$type = wp_check_filetype( $upload['file'] );
	$id   = wp_insert_attachment(
		array(
			'post_mime_type' => $type['type'],
			'post_title'     => preg_replace( '/\.[^.]+$/', '', $file ),
			'post_status'    => 'inherit',
		),
		$upload['file'],
		$parent_id
	);
	if ( ! $id || is_wp_error( $id ) ) {
		return 0;
	}
	require_once ABSPATH . 'wp-admin/includes/image.php';
	wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $upload['file'] ) );
	update_post_meta( $id, '_dp_src', $file );
	return (int) $id;
}

function dp_create_blog() {
	$log = array();

	// 1. Blog page, used as the Posts page.
	$blog = get_page_by_path( 'blog', OBJECT, 'page' );
	if ( $blog ) {
		$blog_id = $blog->ID;
		if ( 'publish' !== $blog->post_status ) {
			wp_update_post( array( 'ID' => $blog_id, 'post_status' => 'publish' ) );
		}
	} else {
		$blog_id = wp_insert_post( array( 'post_title' => 'Blog', 'post_name' => 'blog', 'post_status' => 'publish', 'post_type' => 'page' ) );
		$log[]   = 'Blog page created';
	}
	if ( $blog_id && ! is_wp_error( $blog_id ) ) {
		update_option( 'page_for_posts', $blog_id );
		if ( 'page' !== get_option( 'show_on_front' ) ) {
			$home = get_page_by_path( 'home', OBJECT, 'page' );
			if ( $home ) {
				update_option( 'show_on_front', 'page' );
				update_option( 'page_on_front', $home->ID );
			}
		}
	}

	// 2. Starter articles.
	$i     = 0;
	$added = 0;
	foreach ( dp_starter_posts() as $p ) {
		$i++;
		if ( get_page_by_path( $p['slug'], OBJECT, 'post' ) ) {
			continue;
		}
		$term = term_exists( $p['cat'], 'category' );
		if ( ! $term ) {
			$term = wp_insert_term( $p['cat'], 'category' );
		}
		$cat_id = is_array( $term ) ? (int) $term['term_id'] : (int) $term;

		$post_id = wp_insert_post(
			array(
				'post_title'    => $p['title'],
				'post_name'     => $p['slug'],
				'post_content'  => $p['content'],
				'post_excerpt'  => $p['excerpt'],
				'post_status'   => 'publish',
				'post_type'     => 'post',
				'post_author'   => get_current_user_id() ? get_current_user_id() : 1,
				'post_category' => $cat_id ? array( $cat_id ) : array(),
				// Newest first in the order listed above.
				'post_date'     => gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - ( $i * 3 * DAY_IN_SECONDS ) ), // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp
			)
		);
		if ( ! $post_id || is_wp_error( $post_id ) ) {
			continue;
		}
		$img = dp_theme_image_attachment( $p['image'], $post_id );
		if ( $img ) {
			set_post_thumbnail( $post_id, $img );
		}
		$added++;
	}
	if ( $added ) {
		$log[] = $added . ' starter blog articles added';
	}

	// 3. Add Blog to the header menu, if a custom menu is in use.
	$locations = get_nav_menu_locations();
	if ( ! empty( $locations['primary'] ) && $blog_id && ! is_wp_error( $blog_id ) ) {
		$items = wp_get_nav_menu_items( $locations['primary'] );
		$has   = false;
		foreach ( (array) $items as $item ) {
			if ( (int) $item->object_id === (int) $blog_id ) {
				$has = true;
			}
		}
		if ( ! $has ) {
			wp_update_nav_menu_item( $locations['primary'], 0, array( 'menu-item-title' => 'Blog', 'menu-item-object' => 'page', 'menu-item-object-id' => $blog_id, 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish' ) );
			$log[] = 'Blog added to the header menu';
		}
	}
	return $log;
}

/* =====================================================================
   One-time update (v4): richer page designs with illustrations and
   animations, plus the new Blog. Replaces the content of the 5 pages.
   ===================================================================== */
function dp_upgrade_v4() {
	if ( ! current_user_can( 'manage_options' ) || wp_doing_ajax() || get_option( 'dp_upgrade_v4' ) ) {
		return;
	}
	if ( ! did_action( 'elementor/loaded' ) ) {
		return; // Wait until Elementor is active.
	}
	$log = array();
	foreach ( dp_setup_pages_list() as $slug => $page ) {
		$file = get_template_directory() . '/elementor/' . $page['file'];
		$tpl  = file_exists( $file ) ? json_decode( file_get_contents( $file ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions
		$p    = get_page_by_path( $slug, OBJECT, 'page' );
		if ( empty( $tpl['content'] ) || ! $p ) {
			continue;
		}
		dp_save_elementor_data( $p->ID, $tpl['content'] );
		if ( defined( 'ELEMENTOR_VERSION' ) ) {
			update_post_meta( $p->ID, '_elementor_version', ELEMENTOR_VERSION );
		}
		$log[] = $page['title'] . ' page updated with the new design';
	}
	$log = array_merge( $log, dp_create_blog() );

	flush_rewrite_rules();
	if ( isset( \Elementor\Plugin::$instance->files_manager ) ) {
		\Elementor\Plugin::$instance->files_manager->clear_cache();
	}
	update_option( 'dp_upgrade_v4', time() );
	update_option( 'dp_upgrade_v3', time() );
	update_option( 'dp_upgrade_v2', time() );
	set_transient( 'dp_upgrade_v2_log', $log, 120 );
}
add_action( 'admin_init', 'dp_upgrade_v4', 16 );

/* =====================================================================
   One-time update (v5): WhatsApp marketing added to the services.
   Re-applies the 5 page designs.
   ===================================================================== */
function dp_upgrade_v5() {
	if ( ! current_user_can( 'manage_options' ) || wp_doing_ajax() || get_option( 'dp_upgrade_v5' ) ) {
		return;
	}
	if ( ! did_action( 'elementor/loaded' ) ) {
		return; // Wait until Elementor is active.
	}
	$log = array();
	foreach ( dp_setup_pages_list() as $slug => $page ) {
		$file = get_template_directory() . '/elementor/' . $page['file'];
		$tpl  = file_exists( $file ) ? json_decode( file_get_contents( $file ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions
		$p    = get_page_by_path( $slug, OBJECT, 'page' );
		if ( empty( $tpl['content'] ) || ! $p ) {
			continue;
		}
		dp_save_elementor_data( $p->ID, $tpl['content'] );
		$log[] = $page['title'] . ' page updated (WhatsApp marketing added)';
	}
	if ( isset( \Elementor\Plugin::$instance->files_manager ) ) {
		\Elementor\Plugin::$instance->files_manager->clear_cache();
	}
	update_option( 'dp_upgrade_v5', time() );
	set_transient( 'dp_upgrade_v2_log', $log, 120 );
}
add_action( 'admin_init', 'dp_upgrade_v5', 17 );
