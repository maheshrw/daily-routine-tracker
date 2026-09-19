<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$range    = isset( $_GET['range'] ) ? sanitize_text_field( wp_unslash( $_GET['range'] ) ) : 'week';
$ref_date = isset( $_GET['ref_date'] ) ? sanitize_text_field( wp_unslash( $_GET['ref_date'] ) ) : current_time( 'Y-m-d' );
if ( ! in_array( $range, array( 'day', 'week', 'month', 'year' ), true ) ) {
	$range = 'week';
}

$summary  = DRT_Reports::build_summary( $range, $ref_date );
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

// Cap what actually renders in the browser for large ranges (e.g. Year);
// the full set is always available via CSV export regardless of this cap.
$row_cap        = 200;
$billable_all   = array_reverse( $billable['entries'] );
$billable_total = count( $billable_all );
$billable_shown = array_slice( $billable_all, 0, $row_cap );

$logs_all   = array_reverse( $summary['logs'] );
$logs_total = count( $logs_all );
$logs_shown = array_slice( $logs_all, 0, $row_cap );
?>
<div class="wrap drt-wrap">
	<h1>Reports</h1>

	<?php if ( isset( $_GET['pruned'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p>Deleted <?php echo esc_html( (int) $_GET['pruned'] ); ?> logged day(s) and their in-slot tasks.</p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['prune_error'] ) ) : ?>
		<div class="notice notice-error is-dismissible"><p>Please pick a valid cutoff date.</p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['imported'] ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p>
				Import complete: <?php echo esc_html( (int) ( $_GET['imp_routine'] ?? 0 ) ); ?> routine slots,
				<?php echo esc_html( (int) ( $_GET['imp_logs'] ?? 0 ) ); ?> logged days,
				<?php echo esc_html( (int) ( $_GET['imp_subtasks'] ?? 0 ) ); ?> in-slot tasks.
			</p>
		</div>
	<?php endif; ?>
	<?php if ( ! empty( $_GET['import_error'] ) ) : ?>
		<div class="notice notice-error is-dismissible"><p><strong>Import failed:</strong> <?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['import_error'] ) ) ); ?></p></div>
	<?php endif; ?>

	<form method="get" class="drt-report-filters-card">
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
		<button type="submit" class="button button-primary"><span class="dashicons dashicons-filter"></span> Update</button>
		<span class="drt-date-label" style="margin-left:auto;">
			Showing <strong><?php echo esc_html( ucfirst( $range ) ); ?></strong>:
			<?php echo esc_html( $summary['start'] === $summary['end'] ? $summary['start'] : $summary['start'] . ' → ' . $summary['end'] ); ?>
		</span>
	</form>

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
		<table class="drt-table" style="margin-bottom:24px;">
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

	<h2>Billable / Invoicing (in-slot tasks and one-off tasks)</h2>
	<p>
		<a class="button drt-btn-outline drt-btn-export" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=drt_export_billable_csv&range=' . $range . '&ref_date=' . $ref_date ), 'drt_export_billable_csv' ) ); ?>"><span class="dashicons dashicons-download"></span> Export CSV for this range</a>
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
	<?php if ( $billable_total > $row_cap ) : ?>
		<p class="description">Showing the most recent <?php echo esc_html( $row_cap ); ?> of <?php echo esc_html( $billable_total ); ?> entries in this range — use Export CSV above for the complete set.</p>
	<?php endif; ?>
	<table class="drt-table" style="margin-bottom:24px;">
		<thead><tr><th>Date</th><th>Slot</th><th>Task</th><th>Billable</th><th>Duration</th></tr></thead>
		<tbody>
		<?php foreach ( $billable_shown as $entry ) : ?>
			<tr>
				<td><?php echo esc_html( $entry->log_date ); ?></td>
				<td><?php echo esc_html( $entry->slot_title ); ?></td>
				<td class="drt-title"><?php echo esc_html( $entry->title ); ?></td>
				<td><?php echo $entry->billable ? '<strong>Yes</strong>' : 'No'; ?></td>
				<td><?php echo $entry->duration_seconds ? esc_html( gmdate( 'H:i:s', (int) $entry->duration_seconds ) ) : '— (running or not stopped)'; ?></td>
			</tr>
		<?php endforeach; ?>
		<?php if ( empty( $billable_shown ) ) : ?>
			<tr><td colspan="5">No in-slot or one-off tasks logged for this range yet.</td></tr>
		<?php endif; ?>
		</tbody>
	</table>

	<h2>Task Log</h2>
	<p>
		<a class="button drt-btn-outline drt-btn-export" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=drt_export_task_log_csv&range=' . $range . '&ref_date=' . $ref_date ), 'drt_export_task_log_csv' ) ); ?>"><span class="dashicons dashicons-download"></span> Export CSV for this range</a>
	</p>
	<?php if ( $logs_total > $row_cap ) : ?>
		<p class="description">Showing the most recent <?php echo esc_html( $row_cap ); ?> of <?php echo esc_html( $logs_total ); ?> entries in this range — use Export CSV above for the complete set.</p>
	<?php endif; ?>
	<table class="drt-table">
		<thead><tr><th>Date</th><th>Time</th><th>Task</th><th>Category</th><th>Status</th><th>Duration</th><th>Notes</th></tr></thead>
		<tbody>
		<?php foreach ( $logs_shown as $log ) : ?>
			<tr>
				<td><?php echo esc_html( $log->log_date ); ?></td>
				<td class="drt-time"><?php echo esc_html( substr( $log->scheduled_start, 0, 5 ) ); ?></td>
				<td class="drt-title"><?php echo esc_html( $log->title ); ?></td>
				<td><?php echo esc_html( ucfirst( $log->category ) ); ?></td>
				<td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $log->status ) ) ); ?></td>
				<td><?php echo $log->duration_seconds ? esc_html( gmdate( 'H:i:s', $log->duration_seconds ) ) : '—'; ?></td>
				<td><?php echo esc_html( $log->notes ); ?></td>
			</tr>
		<?php endforeach; ?>
		<?php if ( empty( $logs_shown ) ) : ?>
			<tr><td colspan="7">No logs for this range.</td></tr>
		<?php endif; ?>
		</tbody>
	</table>

	<h2 style="margin-top:32px;">Data, Storage &amp; Backup</h2>
	<div class="drt-card">
		<p>
			<strong><?php echo esc_html( number_format_i18n( $storage_stats['count'] ) ); ?></strong> logged entries stored in total
			<?php if ( $storage_stats['since'] ) : ?>
				, going back to <strong><?php echo esc_html( $storage_stats['since'] ); ?></strong>.
			<?php else : ?>
				.
			<?php endif; ?>
			Nothing is deleted automatically — the database just keeps growing (roughly 18-20 rows per day). That's not a performance problem for years of normal use, but if you'd like to trim old history, you can do it manually below. This is permanent and cannot be undone; it does not touch your recurring weekday/weekend routine template, only past daily logs and their in-slot tasks.
		</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="drt-adhoc-form" style="box-shadow:none;border-color:var(--drt-red-light);" onsubmit="return confirm('Permanently delete all logs before the date you chose? This cannot be undone.');">
			<?php wp_nonce_field( 'drt_prune_old_logs' ); ?>
			<input type="hidden" name="action" value="drt_prune_old_logs">
			<label>Delete logs older than <input type="date" name="cutoff_date" required></label>
			<button type="submit" class="button drt-btn-ghost drt-btn-danger-text"><span class="dashicons dashicons-trash"></span> Delete old logs</button>
		</form>

		<h3 style="margin-top:26px;">Move to another site</h3>
		<p class="description">
			Export everything — your recurring weekday/weekend routine, every logged day, and every in-slot task — as one file, then import it on a different WordPress site running this same plugin.
		</p>
		<div style="display:flex;gap:24px;flex-wrap:wrap;margin-top:12px;">
			<div>
				<p style="margin:0 0 8px;font-weight:600;">Export</p>
				<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=drt_export_full_backup' ), 'drt_export_full_backup' ) ); ?>"><span class="dashicons dashicons-download"></span> Export All Data (JSON)</a>
			</div>
			<div>
				<p style="margin:0 0 8px;font-weight:600;">Import</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;" onsubmit="return confirm('Import this backup? Everything in the file will be added as new data. Only do this on a fresh site with no existing Routine Tracker data, or you\'ll end up with duplicates.');">
					<?php wp_nonce_field( 'drt_import_full_backup' ); ?>
					<input type="hidden" name="action" value="drt_import_full_backup">
					<input type="file" name="backup_file" accept=".json" required>
					<button type="submit" class="button drt-btn-outline"><span class="dashicons dashicons-upload"></span> Import</button>
				</form>
			</div>
		</div>
		<p class="description" style="margin-top:12px;">
			Import always adds new data with fresh IDs — it never matches or overwrites existing entries. Use it on a brand-new site (right after activating the plugin there), not on a site that already has its own logs, or you'll end up with duplicates.
		</p>
	</div>
</div>
