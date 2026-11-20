<?php
/**
 * Plugin Name:       Kadvia Bot
 * Description:       Outreach bot for Kadvia. Imports leads, writes personal emails with Claude, sends follow-ups, reads replies, drafts answers, prepares LinkedIn and call tasks, sends WhatsApp templates to opted-in contacts, and reports to clients.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            Kadvia
 * License:           GPL-2.0-or-later
 * Text Domain:       kadvia-bot
 *
 * @package kadvia-bot
 */

defined( 'ABSPATH' ) || exit;

define( 'KB_VERSION', '1.0.0' );
define( 'KB_FILE', __FILE__ );
define( 'KB_DIR', plugin_dir_path( __FILE__ ) );
define( 'KB_URL', plugin_dir_url( __FILE__ ) );

if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p>Kadvia Bot needs PHP 8.1 or newer. In Hostinger: hPanel → Advanced → PHP Configuration → choose 8.2.</p></div>';
		}
	);
	return;
}

require_once KB_DIR . 'vendor/autoload.php';
require_once KB_DIR . 'includes/class-kb-settings.php';
require_once KB_DIR . 'includes/class-kb-db.php';
require_once KB_DIR . 'includes/class-kb-log.php';
require_once KB_DIR . 'includes/class-kb-transport.php';
require_once KB_DIR . 'includes/class-kb-ai.php';
require_once KB_DIR . 'includes/class-kb-leads.php';
require_once KB_DIR . 'includes/class-kb-mailer.php';
require_once KB_DIR . 'includes/class-kb-imap.php';
require_once KB_DIR . 'includes/class-kb-whatsapp.php';
require_once KB_DIR . 'includes/class-kb-engine.php';
require_once KB_DIR . 'includes/class-kb-reports.php';
if ( is_admin() ) {
	require_once KB_DIR . 'admin/class-kb-admin.php';
	KB_Admin::init();
}

register_activation_hook( __FILE__, array( 'KB_Engine', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'KB_Engine', 'deactivate' ) );

KB_Engine::init();
KB_Mailer::init();
