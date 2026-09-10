<?php
/**
 * Plugin Name:       Helo — SMTP & Mail Log
 * Plugin URI:        https://github.com/mkev07/helo
 * Description:       Sends all WordPress mail through your own SMTP server, logs every message, and lets you preview or resend it.
 * Version:           1.3.0
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            Kevin Mukoond
 * Author URI:        https://vectorads.mu
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       helo-smtp
 * Update URI:        https://github.com/mkev07/helo/releases/latest/download/update.json
 *
 * @package Helo
 */

defined( 'ABSPATH' ) || exit;

define( 'HELO_VERSION', '1.3.0' );
define( 'HELO_FILE', __FILE__ );
define( 'HELO_PATH', plugin_dir_path( __FILE__ ) );
define( 'HELO_URL', plugin_dir_url( __FILE__ ) );

require_once HELO_PATH . 'includes/class-helo-settings.php';
require_once HELO_PATH . 'includes/class-helo-logger.php';
require_once HELO_PATH . 'includes/class-helo-mailer.php';
require_once HELO_PATH . 'includes/class-helo-imap.php';
require_once HELO_PATH . 'includes/class-helo-updater.php';

Helo_Mailer::init();
Helo_Logger::init();
Helo_Imap::init();
Helo_Updater::init();

if ( is_admin() ) {
	require_once HELO_PATH . 'admin/class-helo-admin.php';
	Helo_Admin::init();
}

register_activation_hook( __FILE__, array( 'Helo_Logger', 'install' ) );
register_deactivation_hook( __FILE__, array( 'Helo_Logger', 'deactivate' ) );
