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
$is_today    = ( $date === $today_str );
$day_type    = DRT_DB::day_type_for_date( $date );
$date_label  = date_i18n( 'l, j F Y', strtotime( $date ) );
?>
<div class="wrap drt-wrap">
	<h1>
		Day View
		<?php if ( $is_today ) : ?>
			<span class="drt-live-clock" id="drt-live-clock"></span>
			<button type="button" id="drt-enable-notifications" class="button">🔔 Enable notifications</button>
		<?php endif; ?>
	</h1>

	<?php if ( isset( $_GET['added'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p>Task added.</p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['removed'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p>Task removed.</p></div>
	<?php endif; ?>

	<div class="drt-date-nav">
		<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=drt-today&date=' . $prev_date ) ); ?>">&larr; Prev day</a>
		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="drt-date-form">
			<input type="hidden" name="page" value="drt-today">
			<input type="date" name="date" value="<?php echo esc_attr( $date ); ?>" onchange="this.form.submit()">
		</form>
		<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=drt-today&date=' . $next_date ) ); ?>">Next day &rarr;</a>
		<?php if ( ! $is_today ) : ?>
			<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=drt-today' ) ); ?>">Today</a>
		<?php endif; ?>
		<span class="drt-date-label"><?php echo esc_html( $date_label ); ?> &middot; <?php echo esc_html( ucfirst( $day_type ) ); ?> routine</span>
	</div>

	<p class="description">
		<?php if ( $is_today ) : ?>
			The current slot shows how much time has elapsed automatically — click <strong>Start</strong> only if you want to time your own actual work separately, then <strong>Done</strong> to log it.
		<?php else : ?>
			Viewing a <?php echo $date < $today_str ? 'past' : 'future'; ?> day — live timers and notifications only run on Today.
		<?php endif; ?>
		Every slot defaults to <strong>Missed</strong> until you tick Done, and you can flip either button any time. Click Done (or <strong>+ Tasks</strong>) to edit the logged time or add billable tasks inside a slot.
		Use <strong>+ Add a task for this day</strong> below to schedule something just for <?php echo esc_html( $date_label ); ?> — it won't affect any other day.
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
			$is_adhoc     = empty( $log->slot_id );

			// On non-today views there's no live JS tick to fill these in,
			// so compute a sensible static status/timer server-side.
			$static_row_class = '';
			$static_status    = '—';
			$static_timer     = '—';
			if ( ! $is_today ) {
				if ( 'done' === $log->status ) {
					$static_status    = 'Done';
					$static_row_class = 'drt-status-done';
					$static_timer     = $log->duration_seconds ? '✓ ' . gmdate( 'H:i:s', (int) $log->duration_seconds ) . ' logged' : '✓ logged';
				} elseif ( $date > $today_str ) {
					$static_status    = 'Scheduled';
					$static_row_class = 'drt-status-upcoming';
				} else {
					$static_status    = 'Missed';
					$static_row_class = 'drt-status-missed-final';
				}
			}
			?>
			<tr class="drt-row <?php echo $is_adhoc ? 'drt-row-adhoc' : ''; ?> <?php echo esc_attr( $static_row_class ); ?>"
				data-log-id="<?php echo esc_attr( $log->id ); ?>"
				data-start="<?php echo esc_attr( $log->scheduled_start ); ?>"
				data-end="<?php echo esc_attr( $log->scheduled_end ); ?>"
				data-status="<?php echo esc_attr( $log->status ); ?>"
				data-actual-start="<?php echo esc_attr( $log->actual_start ); ?>"
				data-logged-duration="<?php echo esc_attr( $log->duration_seconds ); ?>">
				<td class="drt-time"><?php echo esc_html( substr( $log->scheduled_start, 0, 5 ) . '–' . substr( $log->scheduled_end, 0, 5 ) ); ?></td>
				<td class="drt-title">
					<?php echo esc_html( $log->title ); ?>
					<?php if ( $is_adhoc ) : ?><span class="drt-adhoc-tag" title="One-off task, only on this day">one-off</span><?php endif; ?>
				</td>
				<td><span class="drt-badge" style="background:<?php echo esc_attr( $color ); ?>"><?php echo esc_html( $log->category ); ?></span></td>
				<td class="drt-status"><span class="drt-status-label"><?php echo esc_html( $static_status ); ?></span></td>
				<td class="drt-timer"><?php echo esc_html( $static_timer ); ?></td>
				<td class="drt-actions">
					<?php if ( $is_today ) : ?>
						<button class="button drt-btn-start">Start</button>
					<?php endif; ?>
					<button class="button drt-btn-done <?php echo ( 'done' === $log->status ) ? 'drt-btn-active-done' : ''; ?>">Done</button>
					<button class="button drt-btn-missed <?php echo ( 'missed' === $log->status ) ? 'drt-btn-active-missed' : ''; ?>">Missed</button>
					<button class="button-link drt-btn-toggle-subtasks">+ Tasks (<span class="drt-subtask-count"><?php echo count( $subtasks ); ?></span>)</button>
					<?php if ( $is_adhoc ) : ?>
						<a class="button-link drt-btn-delete-adhoc" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=drt_delete_adhoc_log&id=' . $log->id . '&date=' . $date ), 'drt_delete_adhoc_log' ) ); ?>" onclick="return confirm('Remove this one-off task?');" title="Remove this one-off task">✕ Remove</a>
					<?php endif; ?>
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

	<h2 class="drt-adhoc-heading">+ Add a task for this day</h2>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="drt-adhoc-form">
		<?php wp_nonce_field( 'drt_add_adhoc_log' ); ?>
		<input type="hidden" name="action" value="drt_add_adhoc_log">
		<input type="hidden" name="log_date" value="<?php echo esc_attr( $date ); ?>">
		<label>Start <input type="time" name="start_time" required></label>
		<label>End <input type="time" name="end_time" required></label>
		<label class="drt-adhoc-title">Title <input type="text" name="title" placeholder="e.g. Dentist appointment" required></label>
		<label>Category
			<select name="category">
				<option value="work">Work</option>
				<option value="eat">Eat</option>
				<option value="exercise">Exercise</option>
				<option value="read">Read</option>
				<option value="game">Game</option>
				<option value="rest">Rest</option>
				<option value="other" selected>Other</option>
			</select>
		</label>
		<button type="submit" class="button button-primary">Add task to <?php echo esc_html( $date ); ?></button>
	</form>
	<p class="description">This only adds the task to <?php echo esc_html( $date_label ); ?> — your recurring weekday/weekend routine is untouched. Edit the recurring routine itself from Routine Editor.</p>
</div>
