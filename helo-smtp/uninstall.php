<?php
/**
 * Removes everything the plugin created. Runs only on delete, not deactivate.
 *
 * @package Helo
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

wp_clear_scheduled_hook( 'helo_purge_logs' );

delete_option( 'helo_settings' );
delete_option( 'helo_db_version' );
delete_transient( 'helo_notice' );
delete_transient( 'helo_sent_folder' );
delete_site_transient( 'helo_update_manifest' );

$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'helo_mail_log' );
