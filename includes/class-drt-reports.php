<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DRT_Reports {

	/**
	 * Returns [start_date, end_date] (Y-m-d) for a given range type anchored on $ref_date.
	 */
	public static function get_range( $range, $ref_date ) {
		$ts = strtotime( $ref_date );
		switch ( $range ) {
			case 'week':
				$start = date( 'Y-m-d', strtotime( 'monday this week', $ts ) );
				$end   = date( 'Y-m-d', strtotime( 'sunday this week', $ts ) );
				break;
			case 'month':
				$start = date( 'Y-m-01', $ts );
				$end   = date( 'Y-m-t', $ts );
				break;
			case 'year':
				$start = date( 'Y-01-01', $ts );
				$end   = date( 'Y-12-31', $ts );
				break;
			case 'day':
			default:
				$start = date( 'Y-m-d', $ts );
				$end   = $start;
				break;
		}
		return array( $start, $end );
	}

	public static function build_summary( $range, $ref_date ) {
		list( $start, $end ) = self::get_range( $range, $ref_date );
		$logs = DRT_DB::get_logs_between( $start, $end );

		$summary = array(
			'range'          => $range,
			'start'          => $start,
			'end'            => $end,
			'total'          => 0,
			'done'           => 0,
			'missed'         => 0,
			'by_category'    => array(),
			'by_day'         => array(),
			'total_seconds'  => 0,
			'logs'           => $logs,
		);

		foreach ( $logs as $log ) {
			$summary['total']++;
			$status = $log->status ? $log->status : 'missed';
			if ( isset( $summary[ $status ] ) ) {
				$summary[ $status ]++;
			}

			if ( ! isset( $summary['by_category'][ $log->category ] ) ) {
				$summary['by_category'][ $log->category ] = array(
					'total'   => 0,
					'done'    => 0,
					'seconds' => 0,
				);
			}
			$summary['by_category'][ $log->category ]['total']++;
			if ( 'done' === $status ) {
				$summary['by_category'][ $log->category ]['done']++;
			}
			if ( $log->duration_seconds ) {
				$summary['by_category'][ $log->category ]['seconds'] += (int) $log->duration_seconds;
				$summary['total_seconds'] += (int) $log->duration_seconds;
			}

			if ( ! isset( $summary['by_day'][ $log->log_date ] ) ) {
				$summary['by_day'][ $log->log_date ] = array(
					'total' => 0,
					'done'  => 0,
				);
			}
			$summary['by_day'][ $log->log_date ]['total']++;
			if ( 'done' === $status ) {
				$summary['by_day'][ $log->log_date ]['done']++;
			}
		}

		$summary['completion_rate'] = $summary['total'] > 0
			? round( ( $summary['done'] / $summary['total'] ) * 100 )
			: 0;

		ksort( $summary['by_day'] );

		return $summary;
	}

	/**
	 * Sub-task time breakdown for the same range — billable vs
	 * non-billable, for weekly/monthly invoicing review.
	 */
	public static function build_billable_summary( $range, $ref_date ) {
		list( $start, $end ) = self::get_range( $range, $ref_date );
		$subtasks = DRT_DB::get_subtasks_between( $start, $end );

		$out = array(
			'start'              => $start,
			'end'                => $end,
			'billable_count'     => 0,
			'billable_seconds'   => 0,
			'non_billable_count' => 0,
			'non_billable_seconds' => 0,
			'subtasks'           => $subtasks,
		);

		foreach ( $subtasks as $st ) {
			$seconds = (int) $st->duration_seconds;
			if ( $st->billable ) {
				$out['billable_count']++;
				$out['billable_seconds'] += $seconds;
			} else {
				$out['non_billable_count']++;
				$out['non_billable_seconds'] += $seconds;
			}
		}

		return $out;
	}
}
