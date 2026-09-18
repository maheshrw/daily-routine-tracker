<?php
/**
 * Plugin Name: Daily Routine Tracker
 * Plugin URI:  https://example.com
 * Description: Track your daily routine hour-by-hour, log what you actually did, run an auto timer per time slot, log billable sub-tasks inside a slot or as standalone one-off tasks with reminders on any date, mark recurring slots as permanently Auto Done, and view day/week/month/year reports.
 * Version:     1.4.0
 * Author:      Mahesh Pandey
 * License:     GPL v2 or later
 * Text Domain: daily-routine-tracker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'DRT_VERSION', '1.4.0' );
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
 * `remind` column), never touches existing data.
 */
function drt_maybe_upgrade() {
	if ( get_option( 'drt_db_version' ) !== DRT_VERSION ) {
		DRT_DB::create_tables();
		update_option( 'drt_db_version', DRT_VERSION );
	}
}
add_action( 'plugins_loaded', 'drt_maybe_upgrade' );

/**
 * A 5-minute WP-Cron interval, used to repeat task reminders until the
 * person clicks Start (or marks it Done).
 */
function drt_cron_schedules( $schedules ) {
	$schedules['drt_five_min'] = array(
		'interval' => 5 * MINUTE_IN_SECONDS,
		'display'  => __( 'Every 5 minutes (Daily Routine Tracker)', 'daily-routine-tracker' ),
	);
	return $schedules;
}
add_filter( 'cron_schedules', 'drt_cron_schedules' );

/**
 * Reminder for a one-off task, fired by WP-Cron — first at the task's
 * scheduled start time, then every 5 minutes after, until the person
 * clicks Start, marks it Done, or the task is deleted, at which point
 * this un-schedules itself.
 *
 * Note: like all WP-Cron jobs, this only fires on a visit to the site
 * (front-end or admin) at or after the scheduled time — there's no
 * background process on a typical WordPress host. For reliable timing
 * on a low-traffic or local site, point a real system cron (or an
 * uptime-monitor ping) at wp-cron.php every minute or so.
 */
function drt_send_task_reminder( $log_id ) {
	$log = DRT_DB::get_log( $log_id );

	$done = ! $log || 'done' === $log->status || ! empty( $log->actual_start );
	if ( $done ) {
		wp_clear_scheduled_hook( 'drt_send_task_reminder', array( $log_id ) );
		return;
	}

	$to      = get_option( 'admin_email' );
	$subject = 'Reminder: ' . $log->title;
	$body    = $log->title . ' is scheduled for ' . substr( $log->scheduled_start, 0, 5 )
		. '–' . substr( $log->scheduled_end, 0, 5 ) . ' on ' . $log->log_date . '.'
		. " You'll keep getting this every 5 minutes until you click Start or Done.\n\n"
		. 'Open Day View: ' . admin_url( 'admin.php?page=drt-today&date=' . $log->log_date );
	wp_mail( $to, $subject, $body );
}
add_action( 'drt_send_task_reminder', 'drt_send_task_reminder' );

/**
 * Bootstrap.
 */
function drt_init_plugin() {
	new DRT_Admin();
	new DRT_Ajax();
}
add_action( 'plugins_loaded', 'drt_init_plugin' );
