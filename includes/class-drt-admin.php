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
		add_submenu_page( 'drt-today', 'Today', 'Today', 'manage_options', 'drt-today', array( $this, 'render_today_page' ) );
		add_submenu_page( 'drt-today', 'Routine Editor', 'Routine Editor', 'manage_options', 'drt-editor', array( $this, 'render_editor_page' ) );
		add_submenu_page( 'drt-today', 'Reports', 'Reports', 'manage_options', 'drt-reports', array( $this, 'render_reports_page' ) );
	}

	public function enqueue_assets( $hook ) {
		if ( strpos( $hook, 'drt-' ) === false ) {
			return;
		}
		wp_enqueue_style( 'drt-admin', DRT_PLUGIN_URL . 'assets/css/admin.css', array(), DRT_VERSION );
		wp_enqueue_script( 'drt-admin', DRT_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), DRT_VERSION, true );
		wp_localize_script(
			'drt-admin',
			'DRT',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'drt_nonce' ),
				'today'   => current_time( 'Y-m-d' ),
				'now'     => current_time( 'H:i:s' ),
			)
		);
	}

	private function today_day_type() {
		// N: 1 (Monday) - 7 (Sunday).
		$dow = (int) current_time( 'N' );
		return ( $dow >= 6 ) ? 'weekend' : 'weekday';
	}

	public function render_today_page() {
		$date     = current_time( 'Y-m-d' );
		$day_type = $this->today_day_type();
		DRT_DB::ensure_today_logs( $date, $day_type );
		$logs         = DRT_DB::get_logs_for_date( $date );
		$log_ids      = wp_list_pluck( $logs, 'id' );
		$subtasks_map = DRT_DB::get_subtasks_for_logs( $log_ids );
		include DRT_PLUGIN_DIR . 'includes/views/today.php';
	}

	public function render_editor_page() {
		$weekday = DRT_DB::get_routine( 'weekday' );
		$weekend = DRT_DB::get_routine( 'weekend' );
		$edit_slot = null;
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
		foreach ( $billable['subtasks'] as $st ) {
			$seconds = (int) $st->duration_seconds;
			fputcsv(
				$out,
				array(
					$st->log_date,
					$st->slot_title,
					$st->title,
					$st->billable ? 'Yes' : 'No',
					$seconds ? gmdate( 'H:i:s', $seconds ) : '',
					$seconds ? round( $seconds / 60, 1 ) : '',
				)
			);
		}
		fclose( $out );
		exit;
	}
}
