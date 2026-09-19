<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DRT_DB {

	public static function routine_table() {
		global $wpdb;
		return $wpdb->prefix . 'drt_routine';
	}

	public static function logs_table() {
		global $wpdb;
		return $wpdb->prefix . 'drt_logs';
	}

	public static function subtasks_table() {
		global $wpdb;
		return $wpdb->prefix . 'drt_subtasks';
	}

	public static function create_tables() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$routine_table   = self::routine_table();
		$logs_table      = self::logs_table();
		$subtasks_table  = self::subtasks_table();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql1 = "CREATE TABLE {$routine_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			day_type VARCHAR(10) NOT NULL DEFAULT 'weekday',
			start_time TIME NOT NULL,
			end_time TIME NOT NULL,
			title VARCHAR(191) NOT NULL,
			category VARCHAR(20) NOT NULL DEFAULT 'other',
			sort_order INT NOT NULL DEFAULT 0,
			active TINYINT(1) NOT NULL DEFAULT 1,
			auto_done TINYINT(1) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY day_type (day_type)
		) {$charset_collate};";
		dbDelta( $sql1 );

		$sql2 = "CREATE TABLE {$logs_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			log_date DATE NOT NULL,
			slot_id BIGINT UNSIGNED NULL,
			title VARCHAR(191) NOT NULL,
			category VARCHAR(20) NOT NULL DEFAULT 'other',
			scheduled_start TIME NULL,
			scheduled_end TIME NULL,
			status VARCHAR(10) NOT NULL DEFAULT 'missed',
			remind TINYINT(1) NOT NULL DEFAULT 0,
			billable TINYINT(1) NOT NULL DEFAULT 0,
			actual_start DATETIME NULL,
			actual_end DATETIME NULL,
			banked_seconds INT NOT NULL DEFAULT 0,
			duration_seconds INT NULL,
			notes TEXT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY log_date (log_date),
			KEY slot_id (slot_id),
			KEY category (category)
		) {$charset_collate};";
		dbDelta( $sql2 );

		$sql3 = "CREATE TABLE {$subtasks_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			log_id BIGINT UNSIGNED NOT NULL,
			title VARCHAR(191) NOT NULL,
			billable TINYINT(1) NOT NULL DEFAULT 0,
			actual_start DATETIME NULL,
			actual_end DATETIME NULL,
			banked_seconds INT NOT NULL DEFAULT 0,
			duration_seconds INT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY log_id (log_id),
			KEY billable (billable)
		) {$charset_collate};";
		dbDelta( $sql3 );
	}

	/**
	 * Seed a sensible default routine (weekday + weekend) only if the
	 * routine table is empty, so re-activating never overwrites edits.
	 */
	public static function maybe_seed_default_routine() {
		global $wpdb;
		$table = self::routine_table();
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		if ( $count > 0 ) {
			return;
		}

		$weekday = array(
			array( '05:45', '06:00', 'Wake Up & Water', 'other' ),
			array( '06:00', '06:40', 'Morning Walk', 'exercise' ),
			array( '06:40', '06:50', 'Stretching', 'exercise' ),
			array( '06:50', '07:15', 'Shower & Get Ready', 'other' ),
			array( '07:15', '07:45', 'Breakfast', 'eat' ),
			array( '07:45', '08:00', 'Reading', 'read' ),
			array( '08:00', '08:30', 'Plan the Day', 'work' ),
			array( '08:30', '12:30', 'Deep Work Block 1', 'work' ),
			array( '12:30', '13:00', 'Walk / Stretch', 'exercise' ),
			array( '13:00', '13:45', 'Lunch', 'eat' ),
			array( '13:45', '14:15', 'Rest / Short Walk', 'rest' ),
			array( '14:15', '17:30', 'Work Block 2', 'work' ),
			array( '17:30', '18:15', 'Evening Walk', 'exercise' ),
			array( '18:15', '19:00', 'Free Time / Hobby', 'other' ),
			array( '19:00', '19:30', 'Dinner', 'eat' ),
			array( '19:30', '21:30', 'Free Time / Gaming', 'game' ),
			array( '21:30', '22:00', 'Wind Down / Reading', 'read' ),
			array( '22:00', '23:59', 'Sleep', 'rest' ),
		);

		$weekend = array(
			array( '05:45', '06:00', 'Wake Up & Water', 'other' ),
			array( '06:00', '07:00', 'Long Walk / Outdoor Activity', 'exercise' ),
			array( '07:00', '07:30', 'Shower & Get Ready', 'other' ),
			array( '07:30', '08:15', 'Breakfast', 'eat' ),
			array( '08:15', '10:00', 'Reading Session', 'read' ),
			array( '10:00', '13:00', 'Free Time / Chores / Hobby', 'other' ),
			array( '13:00', '13:45', 'Lunch', 'eat' ),
			array( '13:45', '14:30', 'Rest', 'rest' ),
			array( '14:30', '17:00', 'Reading / Hobby Block', 'read' ),
			array( '17:00', '18:00', 'Walk', 'exercise' ),
			array( '18:00', '19:00', 'Free Time', 'other' ),
			array( '19:00', '19:30', 'Dinner', 'eat' ),
			array( '19:30', '22:30', 'Gaming Block', 'game' ),
			array( '22:30', '23:00', 'Wind Down', 'other' ),
			array( '23:00', '23:59', 'Sleep', 'rest' ),
		);

		$order = 0;
		foreach ( array( 'weekday' => $weekday, 'weekend' => $weekend ) as $day_type => $slots ) {
			foreach ( $slots as $slot ) {
				$wpdb->insert(
					$table,
					array(
						'day_type'   => $day_type,
						'start_time' => $slot[0] . ':00',
						'end_time'   => $slot[1] . ':00',
						'title'      => $slot[2],
						'category'   => $slot[3],
						'sort_order' => $order++,
						'active'     => 1,
					),
					array( '%s', '%s', '%s', '%s', '%s', '%d', '%d' )
				);
			}
		}
	}

	public static function get_routine( $day_type ) {
		global $wpdb;
		$table = self::routine_table();
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE day_type = %s AND active = 1 ORDER BY start_time ASC",
				$day_type
			)
		);
	}

	public static function get_slot( $id ) {
		global $wpdb;
		$table = self::routine_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
	}

	public static function save_slot( $data, $id = 0 ) {
		global $wpdb;
		$table   = self::routine_table();
		$fields  = array(
			'day_type'   => sanitize_text_field( $data['day_type'] ),
			'start_time' => sanitize_text_field( $data['start_time'] ) . ':00',
			'end_time'   => sanitize_text_field( $data['end_time'] ) . ':00',
			'title'      => sanitize_text_field( $data['title'] ),
			'category'   => sanitize_text_field( $data['category'] ),
			'sort_order' => isset( $data['sort_order'] ) ? (int) $data['sort_order'] : 0,
			'active'     => 1,
		);
		$formats = array( '%s', '%s', '%s', '%s', '%s', '%d', '%d' );

		if ( $id ) {
			$wpdb->update( $table, $fields, array( 'id' => $id ), $formats, array( '%d' ) );
			return $id;
		}
		$wpdb->insert( $table, $fields, $formats );
		return $wpdb->insert_id;
	}

	public static function delete_slot( $id ) {
		global $wpdb;
		$table = self::routine_table();
		return $wpdb->update( $table, array( 'active' => 0 ), array( 'id' => $id ), array( '%d' ), array( '%d' ) );
	}

	/**
	 * Weekday vs weekend for an arbitrary date (Mon-Fri = weekday,
	 * Sat/Sun = weekend), used to pick which recurring routine a given
	 * date should be seeded from.
	 */
	public static function day_type_for_date( $date ) {
		$dow = (int) date( 'N', strtotime( $date ) ); // 1 (Mon) - 7 (Sun)
		return ( $dow >= 6 ) ? 'weekend' : 'weekday';
	}

	/**
	 * Ensure a given date's log rows exist (one per active slot for that
	 * date's day_type). Works for today, past, or future dates — called
	 * whenever the Day view loads for a date it hasn't seeded yet.
	 *
	 * Checks specifically for template-based rows (slot_id IS NOT NULL),
	 * not just any row — otherwise a one-off task added for a date before
	 * its Day View was ever opened would make this think the date was
	 * already seeded, and the whole recurring routine would never appear
	 * for that day.
	 */
	public static function ensure_logs_for_date( $date ) {
		global $wpdb;
		$logs_table = self::logs_table();

		$existing = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$logs_table} WHERE log_date = %s AND slot_id IS NOT NULL", $date )
		);
		if ( $existing > 0 ) {
			return;
		}

		$day_type = self::day_type_for_date( $date );
		$slots    = self::get_routine( $day_type );
		$now      = current_time( 'mysql' );
		foreach ( $slots as $slot ) {
			$wpdb->insert(
				$logs_table,
				array(
					'log_date'        => $date,
					'slot_id'         => $slot->id,
					'title'           => $slot->title,
					'category'        => $slot->category,
					'scheduled_start' => $slot->start_time,
					'scheduled_end'   => $slot->end_time,
					'status'          => 'missed',
					'created_at'      => $now,
					'updated_at'      => $now,
				),
				array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
			);
		}
	}

	/**
	 * Complete any outstanding Auto Done slots for a date, but only once
	 * their scheduled start time has actually arrived — never ahead of
	 * time. For a date wholly in the past, every slot has "started" by
	 * definition; for today, only slots whose start time is at or before
	 * the current moment; for a future date, none yet (they stay
	 * Upcoming). Checks the slot's *current* auto_done setting via a
	 * live join, so turning it off before a slot's time arrives correctly
	 * leaves that slot alone. Returns how many logs were completed.
	 */
	public static function apply_auto_done_catchup( $date ) {
		global $wpdb;
		$logs_table    = self::logs_table();
		$routine_table = self::routine_table();
		$today         = current_time( 'Y-m-d' );

		if ( $date > $today ) {
			return 0; // Future day — nothing has started yet.
		}
		$cutoff_time = ( $date < $today ) ? '23:59:59' : current_time( 'H:i:s' );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT l.id, l.scheduled_start, l.scheduled_end
				 FROM {$logs_table} l
				 INNER JOIN {$routine_table} r ON r.id = l.slot_id
				 WHERE l.log_date = %s AND r.auto_done = 1 AND l.status != 'done' AND l.scheduled_start <= %s",
				$date,
				$cutoff_time
			)
		);

		if ( empty( $rows ) ) {
			return 0;
		}

		$now = current_time( 'mysql' );
		foreach ( $rows as $row ) {
			$duration = max( 0, strtotime( $row->scheduled_end ) - strtotime( $row->scheduled_start ) );
			$wpdb->update(
				$logs_table,
				array(
					'status'           => 'done',
					'actual_start'     => null,
					'actual_end'       => $now,
					'banked_seconds'   => 0,
					'duration_seconds' => $duration,
					'updated_at'       => $now,
				),
				array( 'id' => $row->id ),
				array( '%s', '%s', '%s', '%d', '%d', '%s' ),
				array( '%d' )
			);
		}

		return count( $rows );
	}

	/**
	 * Turn a slot's "auto-done" flag on or off. While on, every future day
	 * that includes this slot has its log created already marked Done
	 * (with the slot's scheduled length as the logged time) instead of
	 * defaulting to Missed — stays permanent until toggled off here. This
	 * only affects days not yet seeded; days whose logs already exist are
	 * untouched (same as any other routine-template edit).
	 */
	public static function toggle_slot_auto_done( $id, $auto_done ) {
		global $wpdb;
		return $wpdb->update(
			self::routine_table(),
			array( 'auto_done' => $auto_done ? 1 : 0 ),
			array( 'id' => $id ),
			array( '%d' ),
			array( '%d' )
		);
	}

	/**
	 * Add a one-off task for a single specific date only — it does not
	 * touch the recurring weekday/weekend routine template, so it never
	 * shows up on any other day. Returns the new log's id, or false (with
	 * $wpdb->last_error populated) if the insert failed.
	 */
	public static function add_adhoc_log( $date, $start_time, $end_time, $title, $category, $remind = false, $billable = false ) {
		global $wpdb;
		$now    = current_time( 'mysql' );
		$result = $wpdb->insert(
			self::logs_table(),
			array(
				'log_date'        => $date,
				'slot_id'         => null,
				'title'           => sanitize_text_field( $title ),
				'category'        => sanitize_text_field( $category ),
				'scheduled_start' => $start_time . ':00',
				'scheduled_end'   => $end_time . ':00',
				'status'          => 'missed',
				'remind'          => $remind ? 1 : 0,
				'billable'        => $billable ? 1 : 0,
				'created_at'      => $now,
				'updated_at'      => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
		);
		if ( false === $result ) {
			return false;
		}
		return $wpdb->insert_id;
	}

	/**
	 * One-off (slot_id IS NULL) tasks from a date onward, for the Routine
	 * Editor's "upcoming one-off tasks" list.
	 */
	public static function get_upcoming_adhoc_logs( $from_date, $limit = 50 ) {
		global $wpdb;
		$table = self::logs_table();
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE slot_id IS NULL AND log_date >= %s ORDER BY log_date ASC, scheduled_start ASC LIMIT %d",
				$from_date,
				$limit
			)
		);
	}

	/**
	 * All one-off (slot_id IS NULL) tasks in a date range, billable or
	 * not — merged into the Reports billable summary alongside in-slot
	 * subtasks (mirrors get_subtasks_between).
	 */
	public static function get_adhoc_logs_between( $start_date, $end_date ) {
		global $wpdb;
		$table = self::logs_table();
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE slot_id IS NULL AND log_date BETWEEN %s AND %s ORDER BY log_date ASC, scheduled_start ASC",
				$start_date,
				$end_date
			)
		);
	}

	/**
	 * Remove a log row entirely — only meaningful for one-off (slot_id
	 * IS NULL) entries added via add_adhoc_log(); template-based rows
	 * should be flipped to Missed instead, not deleted.
	 */
	public static function delete_adhoc_log( $id ) {
		global $wpdb;
		return $wpdb->delete(
			self::logs_table(),
			array( 'id' => $id, 'slot_id' => null ),
			array( '%d', '%d' )
		);
	}

	/**
	 * A date's logs, each annotated with slot_auto_done — whether the
	 * originating routine slot currently has Auto Done switched on (NULL
	 * for one-off logs, which have no slot). Used so the browser can
	 * auto-complete a slot the moment its time arrives, live, without a
	 * page reload.
	 */
	public static function get_logs_for_date( $date ) {
		global $wpdb;
		$logs_table    = self::logs_table();
		$routine_table = self::routine_table();
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT l.*, r.auto_done AS slot_auto_done
				 FROM {$logs_table} l
				 LEFT JOIN {$routine_table} r ON r.id = l.slot_id
				 WHERE l.log_date = %s ORDER BY l.scheduled_start ASC",
				$date
			)
		);
	}

	public static function get_log( $id ) {
		global $wpdb;
		$table = self::logs_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
	}

	public static function update_log( $id, $fields, $formats ) {
		global $wpdb;
		$fields['updated_at'] = current_time( 'mysql' );
		$formats[]            = '%s';
		$table                = self::logs_table();
		return $wpdb->update( $table, $fields, array( 'id' => $id ), $formats, array( '%d' ) );
	}

	public static function get_logs_between( $start_date, $end_date ) {
		global $wpdb;
		$table = self::logs_table();
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE log_date BETWEEN %s AND %s ORDER BY log_date ASC, scheduled_start ASC",
				$start_date,
				$end_date
			)
		);
	}

	/**
	 * All-time row count and earliest date — shown on the Reports data
	 * cleanup card so you know how much history exists before deciding
	 * whether to prune anything.
	 */
	public static function get_logs_storage_stats() {
		global $wpdb;
		$table = self::logs_table();
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		$since = $wpdb->get_var( "SELECT MIN(log_date) FROM {$table}" );
		return array( 'count' => $count, 'since' => $since );
	}

	/**
	 * Permanently delete every log (and its subtasks) dated before
	 * $cutoff_date. Nothing auto-runs this — it's only triggered by an
	 * explicit click on the Reports "clean up old data" tool. Returns the
	 * number of logs removed.
	 */
	public static function delete_logs_before( $cutoff_date ) {
		global $wpdb;
		$logs_table     = self::logs_table();
		$subtasks_table = self::subtasks_table();

		// Subtasks first, since they reference log_id.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE s FROM {$subtasks_table} s
				 INNER JOIN {$logs_table} l ON l.id = s.log_id
				 WHERE l.log_date < %s",
				$cutoff_date
			)
		);

		$deleted = $wpdb->query(
			$wpdb->prepare( "DELETE FROM {$logs_table} WHERE log_date < %s", $cutoff_date )
		);

		return (int) $deleted;
	}

	/* ---------------------------------------------------------------
	 * Full backup: export everything (routine template + every logged
	 * day + every in-slot task) as one portable structure, and import it
	 * back in — typically into a brand-new site. IDs are always remapped
	 * on import (never reused verbatim), so this is safe to run against
	 * an empty install without colliding with the default seeded routine
	 * or anything else already there.
	 * ------------------------------------------------------------- */

	public static function get_all_routine_slots_raw() {
		global $wpdb;
		$table = self::routine_table();
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY day_type ASC, sort_order ASC, start_time ASC" );
	}

	public static function get_all_logs_raw() {
		global $wpdb;
		$table = self::logs_table();
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY log_date ASC, scheduled_start ASC" );
	}

	public static function get_all_subtasks_raw() {
		global $wpdb;
		$table = self::subtasks_table();
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY log_id ASC, id ASC" );
	}

	/**
	 * Import a full backup produced by the Reports "Export All Data"
	 * button. Always inserts as new rows with fresh auto-increment IDs —
	 * it never overwrites or matches against existing data, so importing
	 * the same file twice (or into a site that already has entries)
	 * duplicates everything. Intended for moving data to a fresh site.
	 *
	 * $data is the decoded JSON backup: ['routine' => [...], 'logs' =>
	 * [...], 'subtasks' => [...]]. Returns counts on success, or a
	 * WP_Error on a structurally invalid file. Runs inside a transaction
	 * so a failure partway through doesn't leave a half-imported mess.
	 */
	public static function import_full_backup( $data ) {
		if ( ! is_array( $data ) || ! isset( $data['routine'], $data['logs'], $data['subtasks'] )
			|| ! is_array( $data['routine'] ) || ! is_array( $data['logs'] ) || ! is_array( $data['subtasks'] ) ) {
			return new WP_Error( 'drt_bad_backup', 'That file doesn\'t look like a Daily Routine Tracker backup (missing routine/logs/subtasks).' );
		}

		global $wpdb;
		$routine_table  = self::routine_table();
		$logs_table     = self::logs_table();
		$subtasks_table = self::subtasks_table();
		$now            = current_time( 'mysql' );

		$wpdb->query( 'START TRANSACTION' );

		// 1. Routine slots — build old_id => new_id map.
		$slot_id_map = array();
		foreach ( $data['routine'] as $slot ) {
			if ( ! isset( $slot['id'] ) ) {
				continue;
			}
			$ok = $wpdb->insert(
				$routine_table,
				array(
					'day_type'   => isset( $slot['day_type'] ) ? sanitize_text_field( $slot['day_type'] ) : 'weekday',
					'start_time' => isset( $slot['start_time'] ) ? sanitize_text_field( $slot['start_time'] ) : '00:00:00',
					'end_time'   => isset( $slot['end_time'] ) ? sanitize_text_field( $slot['end_time'] ) : '00:00:00',
					'title'      => isset( $slot['title'] ) ? sanitize_text_field( $slot['title'] ) : '',
					'category'   => isset( $slot['category'] ) ? sanitize_text_field( $slot['category'] ) : 'other',
					'sort_order' => isset( $slot['sort_order'] ) ? (int) $slot['sort_order'] : 0,
					'active'     => isset( $slot['active'] ) ? (int) $slot['active'] : 1,
					'auto_done'  => isset( $slot['auto_done'] ) ? (int) $slot['auto_done'] : 0,
				),
				array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d' )
			);
			if ( false === $ok ) {
				$wpdb->query( 'ROLLBACK' );
				return new WP_Error( 'drt_import_failed', 'Failed while importing the routine template: ' . $wpdb->last_error );
			}
			$slot_id_map[ (int) $slot['id'] ] = $wpdb->insert_id;
		}

		// 2. Logs — build old_id => new_id map, remapping slot_id.
		$log_id_map = array();
		foreach ( $data['logs'] as $log ) {
			if ( ! isset( $log['id'] ) ) {
				continue;
			}
			$old_slot_id = isset( $log['slot_id'] ) ? (int) $log['slot_id'] : null;
			$new_slot_id = ( $old_slot_id && isset( $slot_id_map[ $old_slot_id ] ) ) ? $slot_id_map[ $old_slot_id ] : null;

			$ok = $wpdb->insert(
				$logs_table,
				array(
					'log_date'         => isset( $log['log_date'] ) ? sanitize_text_field( $log['log_date'] ) : $now,
					'slot_id'          => $new_slot_id,
					'title'            => isset( $log['title'] ) ? sanitize_text_field( $log['title'] ) : '',
					'category'         => isset( $log['category'] ) ? sanitize_text_field( $log['category'] ) : 'other',
					'scheduled_start'  => isset( $log['scheduled_start'] ) ? $log['scheduled_start'] : null,
					'scheduled_end'    => isset( $log['scheduled_end'] ) ? $log['scheduled_end'] : null,
					'status'           => isset( $log['status'] ) ? sanitize_text_field( $log['status'] ) : 'missed',
					'remind'           => isset( $log['remind'] ) ? (int) $log['remind'] : 0,
					'billable'         => isset( $log['billable'] ) ? (int) $log['billable'] : 0,
					'actual_start'     => isset( $log['actual_start'] ) ? $log['actual_start'] : null,
					'actual_end'       => isset( $log['actual_end'] ) ? $log['actual_end'] : null,
					'banked_seconds'   => isset( $log['banked_seconds'] ) ? (int) $log['banked_seconds'] : 0,
					'duration_seconds' => isset( $log['duration_seconds'] ) ? $log['duration_seconds'] : null,
					'notes'            => isset( $log['notes'] ) ? sanitize_textarea_field( $log['notes'] ) : null,
					'created_at'       => isset( $log['created_at'] ) ? $log['created_at'] : $now,
					'updated_at'       => $now,
				),
				array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s' )
			);
			if ( false === $ok ) {
				$wpdb->query( 'ROLLBACK' );
				return new WP_Error( 'drt_import_failed', 'Failed while importing logs: ' . $wpdb->last_error );
			}
			$log_id_map[ (int) $log['id'] ] = $wpdb->insert_id;
		}

		// 3. Subtasks — remap log_id.
		foreach ( $data['subtasks'] as $st ) {
			$old_log_id = isset( $st['log_id'] ) ? (int) $st['log_id'] : 0;
			if ( ! $old_log_id || ! isset( $log_id_map[ $old_log_id ] ) ) {
				continue; // Orphaned in the source data — skip rather than guess.
			}
			$ok = $wpdb->insert(
				$subtasks_table,
				array(
					'log_id'           => $log_id_map[ $old_log_id ],
					'title'            => isset( $st['title'] ) ? sanitize_text_field( $st['title'] ) : '',
					'billable'         => isset( $st['billable'] ) ? (int) $st['billable'] : 0,
					'actual_start'     => isset( $st['actual_start'] ) ? $st['actual_start'] : null,
					'actual_end'       => isset( $st['actual_end'] ) ? $st['actual_end'] : null,
					'banked_seconds'   => isset( $st['banked_seconds'] ) ? (int) $st['banked_seconds'] : 0,
					'duration_seconds' => isset( $st['duration_seconds'] ) ? $st['duration_seconds'] : null,
					'created_at'       => isset( $st['created_at'] ) ? $st['created_at'] : $now,
					'updated_at'       => $now,
				),
				array( '%s', '%s', '%d', '%s', '%s', '%d', '%d', '%s', '%s' )
			);
			if ( false === $ok ) {
				$wpdb->query( 'ROLLBACK' );
				return new WP_Error( 'drt_import_failed', 'Failed while importing in-slot tasks: ' . $wpdb->last_error );
			}
		}

		$wpdb->query( 'COMMIT' );

		return array(
			'routine'  => count( $slot_id_map ),
			'logs'     => count( $log_id_map ),
			'subtasks' => count( $data['subtasks'] ),
		);
	}

	/**
	 * Add a subtask. If $duration_minutes is given (e.g. entered directly
	 * in the Done panel instead of using a live timer), the subtask is
	 * created already-completed with that exact duration. Otherwise it
	 * starts a live timer (actual_start = now) for a Stop button later.
	 */
	public static function add_subtask( $log_id, $title, $billable, $duration_minutes = null ) {
		global $wpdb;
		$now = current_time( 'mysql' );

		if ( null !== $duration_minutes && is_numeric( $duration_minutes ) && $duration_minutes > 0 ) {
			$seconds = (int) round( $duration_minutes * 60 );
			$fields  = array(
				'log_id'            => (int) $log_id,
				'title'             => sanitize_text_field( $title ),
				'billable'          => $billable ? 1 : 0,
				'actual_start'      => null,
				'actual_end'        => $now,
				'duration_seconds'  => $seconds,
				'created_at'        => $now,
				'updated_at'        => $now,
			);
			$formats = array( '%d', '%s', '%d', '%s', '%s', '%d', '%s', '%s' );
		} else {
			$fields  = array(
				'log_id'       => (int) $log_id,
				'title'        => sanitize_text_field( $title ),
				'billable'     => $billable ? 1 : 0,
				'actual_start' => $now,
				'created_at'   => $now,
				'updated_at'   => $now,
			);
			$formats = array( '%d', '%s', '%d', '%s', '%s', '%s' );
		}

		$wpdb->insert( self::subtasks_table(), $fields, $formats );
		return $wpdb->insert_id;
	}

	public static function get_subtask( $id ) {
		global $wpdb;
		$table = self::subtasks_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
	}

	public static function stop_subtask( $id ) {
		global $wpdb;
		$subtask = self::get_subtask( $id );
		if ( ! $subtask ) {
			return false;
		}
		$now      = current_time( 'mysql' );
		$duration = (int) $subtask->banked_seconds;
		if ( ! empty( $subtask->actual_start ) ) {
			$duration += max( 0, strtotime( $now ) - strtotime( $subtask->actual_start ) );
		}
		$wpdb->update(
			self::subtasks_table(),
			array(
				'actual_start'     => null,
				'actual_end'       => $now,
				'banked_seconds'   => 0,
				'duration_seconds' => $duration,
				'updated_at'       => $now,
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%d', '%d', '%s' ),
			array( '%d' )
		);
		return $duration;
	}

	/**
	 * Pause a running subtask's timer — banks the elapsed time of the
	 * current segment and stops it from counting, without finishing the
	 * subtask. resume_subtask() (or add_subtask picking it back up isn't
	 * applicable here — use resume_subtask) starts a new segment on top
	 * of the banked total.
	 */
	public static function pause_subtask( $id ) {
		global $wpdb;
		$subtask = self::get_subtask( $id );
		if ( ! $subtask || empty( $subtask->actual_start ) ) {
			return false;
		}
		$now     = current_time( 'mysql' );
		$banked  = (int) $subtask->banked_seconds + max( 0, strtotime( $now ) - strtotime( $subtask->actual_start ) );
		$wpdb->update(
			self::subtasks_table(),
			array(
				'actual_start'   => null,
				'banked_seconds' => $banked,
				'updated_at'     => $now,
			),
			array( 'id' => $id ),
			array( '%s', '%d', '%s' ),
			array( '%d' )
		);
		return $banked;
	}

	public static function resume_subtask( $id ) {
		global $wpdb;
		$now = current_time( 'mysql' );
		return $wpdb->update(
			self::subtasks_table(),
			array( 'actual_start' => $now, 'actual_end' => null, 'updated_at' => $now ),
			array( 'id' => $id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	public static function set_subtask_billable( $id, $billable ) {
		global $wpdb;
		return $wpdb->update(
			self::subtasks_table(),
			array( 'billable' => $billable ? 1 : 0, 'updated_at' => current_time( 'mysql' ) ),
			array( 'id' => $id ),
			array( '%d', '%s' ),
			array( '%d' )
		);
	}

	public static function delete_subtask( $id ) {
		global $wpdb;
		return $wpdb->delete( self::subtasks_table(), array( 'id' => $id ), array( '%d' ) );
	}

	public static function get_subtasks_for_log( $log_id ) {
		global $wpdb;
		$table = self::subtasks_table();
		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE log_id = %d ORDER BY actual_start ASC, id ASC", $log_id )
		);
	}

	/**
	 * Batch-fetch subtasks for many logs in a single query (used by the
	 * Today screen so it isn't running one query per slot — 15-20 extra
	 * round trips otherwise). Returns [ log_id => [subtask, ...] ].
	 */
	public static function get_subtasks_for_logs( array $log_ids ) {
		if ( empty( $log_ids ) ) {
			return array();
		}
		global $wpdb;
		$table        = self::subtasks_table();
		$placeholders = implode( ',', array_fill( 0, count( $log_ids ), '%d' ) );
		$sql          = $wpdb->prepare(
			"SELECT * FROM {$table} WHERE log_id IN ({$placeholders}) ORDER BY actual_start ASC, id ASC",
			$log_ids
		);
		$rows    = $wpdb->get_results( $sql );
		$grouped = array();
		foreach ( $rows as $row ) {
			$grouped[ (int) $row->log_id ][] = $row;
		}
		return $grouped;
	}

	/**
	 * Sum of completed subtask durations for a log — used as the smart
	 * default when marking a slot Done, so a slot split into several
	 * logged tasks doesn't have to be re-timed separately.
	 */
	public static function sum_subtask_seconds( $log_id ) {
		global $wpdb;
		$table = self::subtasks_table();
		$sum   = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(duration_seconds) FROM {$table} WHERE log_id = %d AND duration_seconds IS NOT NULL",
				$log_id
			)
		);
		return $sum ? (int) $sum : 0;
	}

	/**
	 * All subtasks in a date range, joined to their parent log for date,
	 * slot title, and category — used by the Reports / billable summary.
	 */
	public static function get_subtasks_between( $start_date, $end_date ) {
		global $wpdb;
		$subtasks_table = self::subtasks_table();
		$logs_table     = self::logs_table();
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.*, l.log_date AS log_date, l.title AS slot_title, l.category AS category
				 FROM {$subtasks_table} s
				 INNER JOIN {$logs_table} l ON l.id = s.log_id
				 WHERE l.log_date BETWEEN %s AND %s
				 ORDER BY l.log_date ASC, s.actual_start ASC",
				$start_date,
				$end_date
			)
		);
	}
}
