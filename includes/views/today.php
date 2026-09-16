<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$categories = array(
	'work'     => '#4C6EF5',
	'eat'      => '#F59F00',
	'exercise' => '#37B24D',
	'read'     => '#7048E8',
	'game'     => '#E64980',
	'rest'     => '#868E96',
	'other'    => '#495057',
);
?>
<div class="wrap drt-wrap">
	<h1>Today's Routine <span class="drt-live-clock" id="drt-live-clock"></span> <button type="button" id="drt-enable-notifications" class="button">🔔 Enable notifications</button></h1>
	<p class="description">
		The current slot shows how much time has elapsed automatically — click <strong>Start</strong> only if you want to time your own actual work separately (e.g. 15 of a scheduled 25 minutes), then <strong>Done</strong> to log it. Skip Start and Done just logs the full scheduled length (or the sum of any tasks already logged inside it, if you've added some). Every slot defaults to <strong>Missed</strong> until you tick Done, and you can flip either button any time, even days later.
		Click Done (or <strong>+ Tasks</strong>) to open the panel where you can edit the <strong>logged time</strong> directly, or add individual tasks with their own time and a billable flag for invoicing.
		The browser tab title shows the current slot's remaining time, and if you enable notifications above, you'll get a desktop alert the moment a slot ends.
	</p>

	<table class="widefat striped drt-today-table">
		<thead>
			<tr>
				<th style="width:110px;">Time</th>
				<th>Task</th>
				<th style="width:100px;">Category</th>
				<th style="width:120px;">Status</th>
				<th style="width:150px;">Timer</th>
				<th style="width:230px;">Actions</th>
				<th>Notes</th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $logs as $log ) : ?>
			<?php
			$color        = isset( $categories[ $log->category ] ) ? $categories[ $log->category ] : '#495057';
			$subtasks     = isset( $subtasks_map[ $log->id ] ) ? $subtasks_map[ $log->id ] : array();
			$minutes_now  = $log->duration_seconds ? round( $log->duration_seconds / 60, 1 ) : '';
			?>
			<tr class="drt-row"
				data-log-id="<?php echo esc_attr( $log->id ); ?>"
				data-start="<?php echo esc_attr( $log->scheduled_start ); ?>"
				data-end="<?php echo esc_attr( $log->scheduled_end ); ?>"
				data-status="<?php echo esc_attr( $log->status ); ?>"
				data-actual-start="<?php echo esc_attr( $log->actual_start ); ?>"
				data-logged-duration="<?php echo esc_attr( $log->duration_seconds ); ?>">
				<td class="drt-time"><?php echo esc_html( substr( $log->scheduled_start, 0, 5 ) . '–' . substr( $log->scheduled_end, 0, 5 ) ); ?></td>
				<td class="drt-title"><?php echo esc_html( $log->title ); ?></td>
				<td><span class="drt-badge" style="background:<?php echo esc_attr( $color ); ?>"><?php echo esc_html( $log->category ); ?></span></td>
				<td class="drt-status"><span class="drt-status-label">—</span></td>
				<td class="drt-timer">—</td>
				<td class="drt-actions">
					<button class="button drt-btn-start">Start</button>
					<button class="button drt-btn-done <?php echo ( 'done' === $log->status ) ? 'drt-btn-active-done' : ''; ?>">Done</button>
					<button class="button drt-btn-missed <?php echo ( 'missed' === $log->status ) ? 'drt-btn-active-missed' : ''; ?>">Missed</button>
					<button class="button-link drt-btn-toggle-subtasks">+ Tasks (<span class="drt-subtask-count"><?php echo count( $subtasks ); ?></span>)</button>
				</td>
				<td class="drt-notes-cell">
					<textarea class="drt-notes" rows="1" placeholder="What did you actually do…"><?php echo esc_textarea( $log->notes ); ?></textarea>
				</td>
			</tr>
			<tr class="drt-subtask-panel" data-panel-for="<?php echo esc_attr( $log->id ); ?>" style="display:none;">
				<td colspan="7">
					<div class="drt-subtask-box">
						<div class="drt-duration-row">
							<label>
								Logged time for this slot (minutes):
								<input type="number" min="0" step="1" class="drt-duration-input" value="<?php echo esc_attr( $minutes_now ); ?>" placeholder="e.g. 15">
							</label>
							<span class="drt-duration-saved" style="display:none;">Saved ✓</span>
						</div>

						<table class="drt-subtask-table">
							<thead>
								<tr>
									<th>Task</th>
									<th style="width:90px;">Billable</th>
									<th style="width:150px;">Timer</th>
									<th style="width:90px;"></th>
								</tr>
							</thead>
							<tbody class="drt-subtask-list">
							<?php foreach ( $subtasks as $st ) : ?>
								<tr class="drt-subtask-row"
									data-subtask-id="<?php echo esc_attr( $st->id ); ?>"
									data-actual-start="<?php echo esc_attr( $st->actual_start ); ?>"
									data-actual-end="<?php echo esc_attr( $st->actual_end ); ?>"
									data-duration="<?php echo esc_attr( $st->duration_seconds ); ?>">
									<td><?php echo esc_html( $st->title ); ?></td>
									<td>
										<label class="drt-billable-toggle">
											<input type="checkbox" class="drt-subtask-billable" <?php checked( $st->billable, 1 ); ?>>
											<span>Billable</span>
										</label>
									</td>
									<td class="drt-subtask-timer">—</td>
									<td>
										<button class="button-link drt-btn-stop-subtask" <?php echo $st->actual_end ? 'style="display:none;"' : ''; ?>>Stop</button>
										<button class="button-link drt-btn-delete-subtask" title="Delete">✕</button>
									</td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
						<div class="drt-subtask-add">
							<input type="text" class="drt-subtask-title-input" placeholder="Task name, e.g. Rosie work">
							<input type="number" min="0" step="1" class="drt-subtask-minutes-input" placeholder="minutes (optional)">
							<label class="drt-billable-toggle">
								<input type="checkbox" class="drt-subtask-billable-input">
								<span>Billable</span>
							</label>
							<button class="button button-primary drt-btn-add-subtask">Add</button>
						</div>
						<p class="description" style="margin-top:6px;">Leave minutes blank to start a live timer instead (stop it later); fill it in to log a task with an exact time right away.</p>
					</div>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</div>
