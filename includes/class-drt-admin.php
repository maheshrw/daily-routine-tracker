<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DRT_Admin {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_drt_save_slot', array( $this, 'handle_save_slot' ) );
		add_action( 'admin_post_drt_delete_slot', array( $this, 'handle_delete_slot' ) );
		add_action( 'admin_post_drt_export_billable_csv', array( $this, 'handle_export_billable_csv' ) );
		add_action( 'admin_post_drt_add_adhoc_log', array( $this, 'handle_add_adhoc_log' ) );
		add_action( 'admin_post_drt_delete_adhoc_log', array( $this, 'handle_delete_adhoc_log' ) );
		add_action( 'admin_post_drt_force_db_upgrade', array( $this, 'handle_force_db_upgrade' ) );
		add_action( 'admin_post_drt_toggle_auto_done', array( $this, 'handle_toggle_auto_done' ) );
	}

	public function register_menu() {
		add_menu_page(
			'Routine Tracker',
			'Routine Tracker',
			'manage_options',
			'drt-today',
			array( $this, 'render_today_page' ),
			'dashicons-clock',
			3
		);
		add_submenu_page( 'drt-today', 'Day View', 'Day View', 'manage_options', 'drt-today', array( $this, 'render_today_page' ) );
		add_submenu_page( 'drt-today', 'Routine Editor', 'Routine Editor', 'manage_options', 'drt-editor', array( $this, 'render_editor_page' ) );
		add_submenu_page( 'drt-today', 'Reports', 'Reports', 'manage_options', 'drt-reports', array( $this, 'render_reports_page' ) );
	}

	public function enqueue_assets( $hook ) {
		if ( strpos( $hook, 'drt-' ) === false ) {
			return;
		}
		wp_enqueue_style( 'drt-admin', DRT_PLUGIN_URL . 'assets/css/admin.css', array(), DRT_VERSION );
		wp_enqueue_script( 'drt-admin', DRT_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), DRT_VERSION, true );

		$view_date = $this->get_requested_date();
		wp_localize_script(
			'drt-admin',
			'DRT',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'drt_nonce' ),
				'today'    => current_time( 'Y-m-d' ),
				'now'      => current_time( 'H:i:s' ),
				'viewDate' => $view_date,
				'isToday'  => ( $view_date === current_time( 'Y-m-d' ) ),
			)
		);
	}

	/**
	 * Validate a Y-m-d date string from the query string; falls back to
	 * today's date (site timezone) if missing or malformed.
	 */
	private function get_requested_date() {
		$default = current_time( 'Y-m-d' );
		if ( empty( $_GET['date'] ) ) {
			return $default;
		}
		$date = sanitize_text_field( wp_unslash( $_GET['date'] ) );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return $default;
		}
		list( $y, $m, $d ) = array_map( 'intval', explode( '-', $date ) );
		if ( ! checkdate( $m, $d, $y ) ) {
			return $default;
		}
		return $date;
	}

	public function render_today_page() {
		$date = $this->get_requested_date();
		DRT_DB::ensure_logs_for_date( $date );
		$logs         = DRT_DB::get_logs_for_date( $date );
		$log_ids      = wp_list_pluck( $logs, 'id' );
		$subtasks_map = DRT_DB::get_subtasks_for_logs( $log_ids );

		$today_str    = current_time( 'Y-m-d' );
		$prev_date    = gmdate( 'Y-m-d', strtotime( $date . ' -1 day' ) );
		$next_date    = gmdate( 'Y-m-d', strtotime( $date . ' +1 day' ) );

		include DRT_PLUGIN_DIR . 'includes/views/today.php';
	}

	public function render_editor_page() {
		$weekday       = DRT_DB::get_routine( 'weekday' );
		$weekend       = DRT_DB::get_routine( 'weekend' );
		$upcoming_adhoc = DRT_DB::get_upcoming_adhoc_logs( current_time( 'Y-m-d' ) );
		$edit_slot     = null;
		if ( isset( $_GET['edit'] ) ) {
			$edit_slot = DRT_DB::get_slot( (int) $_GET['edit'] );
		}
		include DRT_PLUGIN_DIR . 'includes/views/editor.php';
	}

	public function render_reports_page() {
		include DRT_PLUGIN_DIR . 'includes/views/reports.php';
	}

	public function handle_save_slot() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Not allowed' );
		}
		check_admin_referer( 'drt_save_slot' );

		$id = isset( $_POST['slot_id'] ) ? (int) $_POST['slot_id'] : 0;
		DRT_DB::save_slot(
			array(
				'day_type'   => isset( $_POST['day_type'] ) ? sanitize_text_field( wp_unslash( $_POST['day_type'] ) ) : 'weekday',
				'start_time' => isset( $_POST['start_time'] ) ? sanitize_text_field( wp_unslash( $_POST['start_time'] ) ) : '00:00',
				'end_time'   => isset( $_POST['end_time'] ) ? sanitize_text_field( wp_unslash( $_POST['end_time'] ) ) : '00:00',
				'title'      => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
				'category'   => isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : 'other',
			),
			$id
		);

		wp_safe_redirect( admin_url( 'admin.php?page=drt-editor&saved=1' ) );
		exit;
	}

	/**
	 * Toggle a routine slot's "auto-done" flag — see
	 * DRT_DB::toggle_slot_auto_done() for what this does.
	 */
	public function handle_toggle_auto_done() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Not allowed' );
		}
		check_admin_referer( 'drt_toggle_auto_done' );
		$id        = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		$auto_done = isset( $_GET['auto_done'] ) ? (int) $_GET['auto_done'] : 0;
		if ( $id ) {
			DRT_DB::toggle_slot_auto_done( $id, $auto_done );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=drt-editor&auto_done_updated=1' ) );
		exit;
	}

	public function handle_delete_slot() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Not allowed' );
		}
		check_admin_referer( 'drt_delete_slot' );
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		if ( $id ) {
			DRT_DB::delete_slot( $id );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=drt-editor&deleted=1' ) );
		exit;
	}

	/**
	 * Add a one-off task to a single specific date (does not touch the
	 * recurring weekday/weekend routine template). If "remind" is
	 * checked, schedules a recurring 5-minute WP-Cron reminder starting
	 * at the task's scheduled time.
	 */
	public function handle_add_adhoc_log() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Not allowed' );
		}
		check_admin_referer( 'drt_add_adhoc_log' );

		$date = isset( $_POST['log_date'] ) ? sanitize_text_field( wp_unslash( $_POST['log_date'] ) ) : current_time( 'Y-m-d' );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			$date = current_time( 'Y-m-d' );
		}
		$start_time = isset( $_POST['start_time'] ) ? sanitize_text_field( wp_unslash( $_POST['start_time'] ) ) : '00:00';
		$remind     = ! empty( $_POST['remind'] );
		$billable   = ! empty( $_POST['billable'] );

		$log_id = DRT_DB::add_adhoc_log(
			$date,
			$start_time,
			isset( $_POST['end_time'] ) ? sanitize_text_field( wp_unslash( $_POST['end_time'] ) ) : '00:00',
			isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
			isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : 'other',
			$remind,
			$billable
		);

		$came_from_editor = ( isset( $_POST['redirect'] ) && 'editor' === $_POST['redirect'] );
		$back_page         = $came_from_editor ? 'drt-editor' : 'drt-today';

		if ( false === $log_id ) {
			global $wpdb;
			// Insert failed — most likely cause: the site's plugin files
			// were updated but the database table wasn't migrated yet
			// (e.g. a new column added in a recent version). Surface the
			// real error instead of silently saying "added" and losing
			// the task, and point at the one-click repair below.
			$error_msg   = $wpdb->last_error ? $wpdb->last_error : 'Unknown database error.';
			$redirect_to = admin_url( 'admin.php?page=' . $back_page . '&drt_error=' . rawurlencode( $error_msg ) );
			if ( 'drt-today' === $back_page ) {
				$redirect_to .= '&date=' . rawurlencode( $date );
			}
			wp_safe_redirect( $redirect_to );
			exit;
		}

		if ( $remind ) {
			$timestamp = strtotime( get_gmt_from_date( $date . ' ' . $start_time . ':00' ) );
			wp_clear_scheduled_hook( 'drt_send_task_reminder', array( $log_id ) );
			wp_schedule_event( $timestamp, 'drt_five_min', 'drt_send_task_reminder', array( $log_id ) );
		}

		$redirect_to = $came_from_editor
			? admin_url( 'admin.php?page=drt-editor&added=1' )
			: admin_url( 'admin.php?page=drt-today&date=' . rawurlencode( $date ) . '&added=1' );
		wp_safe_redirect( $redirect_to );
		exit;
	}

	public function handle_delete_adhoc_log() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Not allowed' );
		}
		check_admin_referer( 'drt_delete_adhoc_log' );

		$id   = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		$date = isset( $_GET['date'] ) ? sanitize_text_field( wp_unslash( $_GET['date'] ) ) : current_time( 'Y-m-d' );
		if ( $id ) {
			wp_clear_scheduled_hook( 'drt_send_task_reminder', array( $id ) );
			DRT_DB::delete_adhoc_log( $id );
		}

		$redirect_to = ( isset( $_GET['redirect'] ) && 'editor' === $_GET['redirect'] )
			? admin_url( 'admin.php?page=drt-editor&removed=1' )
			: admin_url( 'admin.php?page=drt-today&date=' . rawurlencode( $date ) . '&removed=1' );
		wp_safe_redirect( $redirect_to );
		exit;
	}

	/**
	 * One-click "re-check database tables" — re-runs dbDelta against the
	 * current schema (adds any missing columns/tables, touches nothing
	 * existing) and resets the stored version so future upgrades keep
	 * auto-running. Useful when files were updated in a way that skipped
	 * the automatic plugins_loaded migration (e.g. a stale PHP opcache).
	 */
	public function handle_force_db_upgrade() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Not allowed' );
		}
		check_admin_referer( 'drt_force_db_upgrade' );
		DRT_DB::create_tables();
		update_option( 'drt_db_version', DRT_VERSION );
		wp_safe_redirect( admin_url( 'admin.php?page=drt-editor&db_repaired=1' ) );
		exit;
	}

	/**
	 * Stream the billable task log for a report range as a CSV download —
	 * for handing clean records to clients or importing into accounting
	 * tools.
	 */
	public function handle_export_billable_csv() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Not allowed' );
		}
		check_admin_referer( 'drt_export_billable_csv' );

		$range    = isset( $_GET['range'] ) ? sanitize_text_field( wp_unslash( $_GET['range'] ) ) : 'week';
		$ref_date = isset( $_GET['ref_date'] ) ? sanitize_text_field( wp_unslash( $_GET['ref_date'] ) ) : current_time( 'Y-m-d' );
		$billable = DRT_Reports::build_billable_summary( $range, $ref_date );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="billable-' . $billable['start'] . '-to-' . $billable['end'] . '.csv"' );

		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'Date', 'Slot', 'Task', 'Billable', 'Duration (H:M:S)', 'Duration (minutes)' ) );
		foreach ( $billable['entries'] as $entry ) {
			$seconds = (int) $entry->duration_seconds;
			fputcsv(
				$out,
				array(
					$entry->log_date,
					$entry->slot_title,
					$entry->title,
					$entry->billable ? 'Yes' : 'No',
					$seconds ? gmdate( 'H:i:s', $seconds ) : '',
					$seconds ? round( $seconds / 60, 1 ) : '',
				)
			);
		}
		fclose( $out );
		exit;
	}
}
