<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$range    = isset( $_GET['range'] ) ? sanitize_text_field( wp_unslash( $_GET['range'] ) ) : 'week';
$ref_date = isset( $_GET['ref_date'] ) ? sanitize_text_field( wp_unslash( $_GET['ref_date'] ) ) : current_time( 'Y-m-d' );
if ( ! in_array( $range, array( 'day', 'week', 'month', 'year' ), true ) ) {
	$range = 'week';
}

$summary = DRT_Reports::build_summary( $range, $ref_date );
$billable = DRT_Reports::build_billable_summary( $range, $ref_date );

$category_colors = array(
	'work'     => '#4C6EF5',
	'eat'      => '#F59F00',
	'exercise' => '#37B24D',
	'read'     => '#7048E8',
	'game'     => '#E64980',
	'rest'     => '#868E96',
	'other'    => '#495057',
);

$max_cat_total = 1;
foreach ( $summary['by_category'] as $cat_data ) {
	$max_cat_total = max( $max_cat_total, $cat_data['total'] );
}
?>
<div class="wrap drt-wrap">
	<h1>Reports</h1>

	<form method="get" class="drt-report-filters">
		<input type="hidden" name="page" value="drt-reports">
		<label>
			Range:
			<select name="range">
				<?php foreach ( array( 'day' => 'Day', 'week' => 'Week', 'month' => 'Month', 'year' => 'Year' ) as $val => $label ) : ?>
					<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $range, $val ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<label>
			Anchor date:
			<input type="date" name="ref_date" value="<?php echo esc_attr( $ref_date ); ?>">
		</label>
		<?php submit_button( 'Update', 'secondary', '', false ); ?>
	</form>

	<p class="description">
		Showing <strong><?php echo esc_html( ucfirst( $range ) ); ?></strong>:
		<?php echo esc_html( $summary['start'] === $summary['end'] ? $summary['start'] : $summary['start'] . ' → ' . $summary['end'] ); ?>
	</p>

	<div class="drt-stat-cards">
		<div class="drt-stat-card">
			<span class="drt-stat-num"><?php echo esc_html( $summary['completion_rate'] ); ?>%</span>
			<span class="drt-stat-label">Completion Rate</span>
		</div>
		<div class="drt-stat-card">
			<span class="drt-stat-num"><?php echo esc_html( $summary['done'] ); ?>/<?php echo esc_html( $summary['total'] ); ?></span>
			<span class="drt-stat-label">Tasks Done</span>
		</div>
		<div class="drt-stat-card">
			<span class="drt-stat-num"><?php echo esc_html( $summary['missed'] ); ?></span>
			<span class="drt-stat-label">Missed</span>
		</div>
		<div class="drt-stat-card">
			<span class="drt-stat-num"><?php echo esc_html( gmdate( 'H:i', $summary['total_seconds'] ) ); ?></span>
			<span class="drt-stat-label">Tracked Time (h:m)</span>
		</div>
	</div>

	<h2>By Category</h2>
	<div class="drt-bar-chart">
		<?php foreach ( $summary['by_category'] as $cat => $data ) : ?>
			<?php
			$pct   = $max_cat_total > 0 ? round( ( $data['total'] / $max_cat_total ) * 100 ) : 0;
			$color = isset( $category_colors[ $cat ] ) ? $category_colors[ $cat ] : '#495057';
			?>
			<div class="drt-bar-row">
				<span class="drt-bar-label"><?php echo esc_html( ucfirst( $cat ) ); ?></span>
				<div class="drt-bar-track">
					<div class="drt-bar-fill" style="width:<?php echo esc_attr( $pct ); ?>%;background:<?php echo esc_attr( $color ); ?>"></div>
				</div>
				<span class="drt-bar-value"><?php echo esc_html( $data['done'] ); ?>/<?php echo esc_html( $data['total'] ); ?> done</span>
			</div>
		<?php endforeach; ?>
		<?php if ( empty( $summary['by_category'] ) ) : ?>
			<p>No data for this range yet.</p>
		<?php endif; ?>
	</div>

	<?php if ( 'day' !== $range ) : ?>
		<h2>Day by Day</h2>
		<table class="widefat striped">
			<thead><tr><th>Date</th><th>Done</th><th>Total</th><th>Completion</th></tr></thead>
			<tbody>
			<?php foreach ( $summary['by_day'] as $day => $d ) : ?>
				<tr>
					<td><?php echo esc_html( $day ); ?></td>
					<td><?php echo esc_html( $d['done'] ); ?></td>
					<td><?php echo esc_html( $d['total'] ); ?></td>
					<td><?php echo esc_html( $d['total'] > 0 ? round( ( $d['done'] / $d['total'] ) * 100 ) : 0 ); ?>%</td>
				</tr>
			<?php endforeach; ?>
			<?php if ( empty( $summary['by_day'] ) ) : ?>
				<tr><td colspan="4">No data.</td></tr>
			<?php endif; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<h2>Billable / Invoicing (from tasks logged inside slots)</h2>
	<p>
		<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=drt_export_billable_csv&range=' . $range . '&ref_date=' . $ref_date ), 'drt_export_billable_csv' ) ); ?>">Export CSV for this range</a>
	</p>
	<div class="drt-stat-cards">
		<div class="drt-stat-card">
			<span class="drt-stat-num"><?php echo esc_html( $billable['billable_count'] ); ?></span>
			<span class="drt-stat-label">Billable Tasks</span>
		</div>
		<div class="drt-stat-card">
			<span class="drt-stat-num"><?php echo esc_html( gmdate( 'H:i', $billable['billable_seconds'] ) ); ?></span>
			<span class="drt-stat-label">Billable Time (h:m)</span>
		</div>
		<div class="drt-stat-card">
			<span class="drt-stat-num"><?php echo esc_html( $billable['non_billable_count'] ); ?></span>
			<span class="drt-stat-label">Non-Billable Tasks</span>
		</div>
		<div class="drt-stat-card">
			<span class="drt-stat-num"><?php echo esc_html( gmdate( 'H:i', $billable['non_billable_seconds'] ) ); ?></span>
			<span class="drt-stat-label">Non-Billable Time (h:m)</span>
		</div>
	</div>
	<table class="widefat striped">
		<thead><tr><th>Date</th><th>Slot</th><th>Task</th><th>Billable</th><th>Duration</th></tr></thead>
		<tbody>
		<?php foreach ( array_reverse( $billable['subtasks'] ) as $st ) : ?>
			<tr>
				<td><?php echo esc_html( $st->log_date ); ?></td>
				<td><?php echo esc_html( $st->slot_title ); ?></td>
				<td><?php echo esc_html( $st->title ); ?></td>
				<td><?php echo $st->billable ? '<strong>Yes</strong>' : 'No'; ?></td>
				<td><?php echo $st->duration_seconds ? esc_html( gmdate( 'H:i:s', (int) $st->duration_seconds ) ) : '— (running or not stopped)'; ?></td>
			</tr>
		<?php endforeach; ?>
		<?php if ( empty( $billable['subtasks'] ) ) : ?>
			<tr><td colspan="5">No tasks logged inside slots for this range yet.</td></tr>
		<?php endif; ?>
		</tbody>
	</table>

	<h2>Task Log</h2>
	<table class="widefat striped">
		<thead><tr><th>Date</th><th>Time</th><th>Task</th><th>Category</th><th>Status</th><th>Duration</th><th>Notes</th></tr></thead>
		<tbody>
		<?php foreach ( array_reverse( $summary['logs'] ) as $log ) : ?>
			<tr>
				<td><?php echo esc_html( $log->log_date ); ?></td>
				<td><?php echo esc_html( substr( $log->scheduled_start, 0, 5 ) ); ?></td>
				<td><?php echo esc_html( $log->title ); ?></td>
				<td><?php echo esc_html( ucfirst( $log->category ) ); ?></td>
				<td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $log->status ) ) ); ?></td>
				<td><?php echo $log->duration_seconds ? esc_html( gmdate( 'H:i:s', $log->duration_seconds ) ) : '—'; ?></td>
				<td><?php echo esc_html( $log->notes ); ?></td>
			</tr>
		<?php endforeach; ?>
		<?php if ( empty( $summary['logs'] ) ) : ?>
			<tr><td colspan="7">No logs for this range.</td></tr>
		<?php endif; ?>
		</tbody>
	</table>
</div>
