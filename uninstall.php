<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}drt_routine" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}drt_logs" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}drt_subtasks" );
