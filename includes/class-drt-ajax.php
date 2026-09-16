<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DRT_Ajax {

	public function __construct() {
		add_action( 'wp_ajax_drt_start_task', array( $this, 'start_task' ) );
		add_action( 'wp_ajax_drt_mark_done', array( $this, 'mark_done' ) );
		add_action( 'wp_ajax_drt_mark_missed', array( $this, 'mark_missed' ) );
		add_action( 'wp_ajax_drt_save_notes', array( $this, 'save_notes' ) );

		add_action( 'wp_ajax_drt_add_subtask', array( $this, 'add_subtask' ) );
		add_action( 'wp_ajax_drt_stop_subtask', array( $this, 'stop_subtask' ) );
		add_action( 'wp_ajax_drt_toggle_subtask_billable', array( $this, 'toggle_subtask_billable' ) );
		add_action( 'wp_ajax_drt_delete_subtask', array( $this, 'delete_subtask' ) );

		add_action( 'wp_ajax_drt_update_duration', array( $this, 'update_duration' ) );
	}

	private function verify() {
		check_ajax_referer( 'drt_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Not allowed', 403 );
		}
	}

	private function get_log_id() {
		return isset( $_POST['log_id'] ) ? (int) $_POST['log_id'] : 0;
	}

	/**
	 * Start (or restart) the precise actual-time clock for a slot. This
	 * only records actual_start — it doesn't touch Done/Missed, so you
	 * can time a 25-minute slot and stop at 15 minutes, then click Done.
	 */
	public function start_task() {
		$this->verify();
		$id  = $this->get_log_id();
		$log = DRT_DB::get_log( $id );
		if ( ! $log ) {
			wp_send_json_error( 'Not found' );
		}
		$now = current_time( 'mysql' );
		DRT_DB::update_log(
			$id,
			array(
				'actual_start' => $now,
				'actual_end'   => null,
			),
			array( '%s', '%s' )
		);
		wp_send_json_success( array( 'actual_start' => $now ) );
	}

	/**
	 * Mark a task Done. Can be clicked any time (even days later) to flip
	 * a task from its default Missed state. The logged duration defaults
	 * to, in priority order: the sum of any subtasks already logged
	 * inside this slot, the precise actual_start→now gap if Start was
	 * clicked, or the slot's scheduled length — and it's always editable
	 * afterward via the Logged Time field in the Tasks panel.
	 */
	public function mark_done() {
		$this->verify();
		$id  = $this->get_log_id();
		$log = DRT_DB::get_log( $id );
		if ( ! $log ) {
			wp_send_json_error( 'Not found' );
		}

		$now             = current_time( 'mysql' );
		$subtask_seconds = DRT_DB::sum_subtask_seconds( $id );

		if ( $subtask_seconds > 0 ) {
			$duration = $subtask_seconds;
		} elseif ( ! empty( $log->actual_start ) ) {
			$duration = max( 0, strtotime( $now ) - strtotime( $log->actual_start ) );
		} elseif ( $log->scheduled_start && $log->scheduled_end ) {
			$duration = max( 0, strtotime( $log->scheduled_end ) - strtotime( $log->scheduled_start ) );
		} else {
			$duration = 0;
		}

		DRT_DB::update_log(
			$id,
			array(
				'status'           => 'done',
				'actual_end'       => $now,
				'duration_seconds' => $duration,
			),
			array( '%s', '%s', '%d' )
		);
		wp_send_json_success( array( 'duration_seconds' => $duration ) );
	}

	/**
	 * Mark a task Missed. Also clears any in-progress actual_start so a
	 * fresh Start can begin cleanly if you flip it back to Done later.
	 */
	public function mark_missed() {
		$this->verify();
		$id = $this->get_log_id();
		DRT_DB::update_log(
			$id,
			array(
				'status'            => 'missed',
				'actual_start'      => null,
				'actual_end'        => null,
				'duration_seconds'  => null,
			),
			array( '%s', '%s', '%s', '%d' )
		);
		wp_send_json_success();
	}

	public function save_notes() {
		$this->verify();
		$id    = $this->get_log_id();
		$notes = isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';
		DRT_DB::update_log( $id, array( 'notes' => $notes ), array( '%s' ) );
		wp_send_json_success();
	}

	/**
	 * Manually override a slot's logged time (in minutes), any time —
	 * this is what lets you correct the auto-computed default.
	 */
	public function update_duration() {
		$this->verify();
		$id      = $this->get_log_id();
		$minutes = isset( $_POST['minutes'] ) ? (float) $_POST['minutes'] : 0;
		$seconds = max( 0, (int) round( $minutes * 60 ) );
		DRT_DB::update_log( $id, array( 'duration_seconds' => $seconds ), array( '%d' ) );
		wp_send_json_success( array( 'duration_seconds' => $seconds ) );
	}

	/* ------------------------- Subtasks ------------------------- */

	public function add_subtask() {
		$this->verify();
		$log_id   = $this->get_log_id();
		$title    = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$billable = ! empty( $_POST['billable'] );
		$minutes  = ( isset( $_POST['minutes'] ) && '' !== $_POST['minutes'] ) ? (float) $_POST['minutes'] : null;

		if ( ! $log_id || '' === trim( $title ) ) {
			wp_send_json_error( 'Missing title' );
		}

		$id      = DRT_DB::add_subtask( $log_id, $title, $billable, $minutes );
		$subtask = DRT_DB::get_subtask( $id );

		wp_send_json_success(
			array(
				'id'               => $id,
				'title'            => esc_html( $title ),
				'billable'         => $billable ? 1 : 0,
				'actual_start'     => $subtask->actual_start,
				'actual_end'       => $subtask->actual_end,
				'duration_seconds' => $subtask->duration_seconds,
			)
		);
	}

	public function stop_subtask() {
		$this->verify();
		$id       = isset( $_POST['subtask_id'] ) ? (int) $_POST['subtask_id'] : 0;
		$duration = DRT_DB::stop_subtask( $id );
		wp_send_json_success( array( 'duration_seconds' => $duration ) );
	}

	public function toggle_subtask_billable() {
		$this->verify();
		$id       = isset( $_POST['subtask_id'] ) ? (int) $_POST['subtask_id'] : 0;
		$billable = ! empty( $_POST['billable'] );
		DRT_DB::set_subtask_billable( $id, $billable );
		wp_send_json_success();
	}

	public function delete_subtask() {
		$this->verify();
		$id = isset( $_POST['subtask_id'] ) ? (int) $_POST['subtask_id'] : 0;
		DRT_DB::delete_subtask( $id );
		wp_send_json_success();
	}
}
