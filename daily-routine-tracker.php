<?php
/**
 * Plugin Name: Daily Routine Tracker
 * Plugin URI:  https://example.com
 * Description: Track your daily routine hour-by-hour, log what you actually did, run an auto timer per time slot, log billable sub-tasks inside a slot, and view day/week/month/year reports.
 * Version:     1.1.0
 * Author:      Mahesh Pandey
 * License:     GPL v2 or later
 * Text Domain: daily-routine-tracker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'DRT_VERSION', '1.1.0' );
define( 'DRT_PLUGIN_FILE', __FILE__ );
define( 'DRT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DRT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once DRT_PLUGIN_DIR . 'includes/class-drt-db.php';
require_once DRT_PLUGIN_DIR . 'includes/class-drt-admin.php';
require_once DRT_PLUGIN_DIR . 'includes/class-drt-ajax.php';
require_once DRT_PLUGIN_DIR . 'includes/class-drt-reports.php';

/**
 * Activation: create tables + seed default routine.
 */
function drt_activate_plugin() {
	DRT_DB::create_tables();
	DRT_DB::maybe_seed_default_routine();
	update_option( 'drt_db_version', DRT_VERSION );
}
register_activation_hook( __FILE__, 'drt_activate_plugin' );

/**
 * Auto-migrate on every load if the plugin files were updated without a
 * fresh activate/deactivate cycle (e.g. files replaced via SFTP) — dbDelta
 * is idempotent, so this only ever adds what's missing (like the new
 * subtasks table), never touches existing data.
 */
function drt_maybe_upgrade() {
	if ( get_option( 'drt_db_version' ) !== DRT_VERSION ) {
		DRT_DB::create_tables();
		update_option( 'drt_db_version', DRT_VERSION );
	}
}
add_action( 'plugins_loaded', 'drt_maybe_upgrade' );

/**
 * Bootstrap.
 */
function drt_init_plugin() {
	new DRT_Admin();
	new DRT_Ajax();
}
add_action( 'plugins_loaded', 'drt_init_plugin' );
