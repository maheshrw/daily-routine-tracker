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
	<?php if ( ! empty( $_GET['drt_error'] ) ) : ?>
		<div class="notice notice-error">
			<p>
				<strong>That task wasn't saved.</strong>
				Database said: <code><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['drt_error'] ) ) ); ?></code>
			</p>
			<p>
				This usually means the database table is out of date with the plugin files. Try:
				<a class="button button-small" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=drt_force_db_upgrade' ), 'drt_force_db_upgrade' ) ); ?>">Re-check database tables</a>
			</p>
		</div>
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
			The current slot shows how much time has elapsed automatically. Click <strong>Start</strong> to time your own actual work, <strong>Pause</strong> to stop the clock without finishing, and <strong>Done</strong> to log it.
		<?php else : ?>
			Viewing a <?php echo $date < $today_str ? 'past' : 'future'; ?> day — live timers and notifications only run on Today.
		<?php endif; ?>
		Every slot defaults to <strong>Missed</strong> automatically — there's nothing to click for that. Click <strong>Done</strong> (or <strong>+ Tasks</strong>) to edit the logged time or add billable tasks inside a slot; a small <strong>Undo</strong> link appears next to Done if you need to flip it back.
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
			$is_done      = ( 'done' === $log->status );
			$is_paused    = ( empty( $log->actual_start ) && ! empty( $log->banked_seconds ) && ! $is_done );
			$is_running   = ( ! empty( $log->actual_start ) && ! $is_done );

			// On non-today views there's no live JS tick to fill these in,
			// so compute a sensible static status/timer server-side.
			$static_row_class = '';
			$static_status    = '—';
			$static_timer     = '—';
			if ( ! $is_today ) {
				if ( $is_done ) {
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
				data-banked-seconds="<?php echo esc_attr( (int) $log->banked_seconds ); ?>"
				data-logged-duration="<?php echo esc_attr( $log->duration_seconds ); ?>"
				data-remind="<?php echo esc_attr( ! empty( $log->remind ) ? 1 : 0 ); ?>">
				<td class="drt-time"><?php echo esc_html( substr( $log->scheduled_start, 0, 5 ) . '–' . substr( $log->scheduled_end, 0, 5 ) ); ?></td>
				<td class="drt-title">
					<?php echo esc_html( $log->title ); ?>
					<?php if ( $is_adhoc ) : ?><span class="drt-adhoc-tag" title="One-off task, only on this day">one-off</span><?php endif; ?>
					<?php if ( ! empty( $log->billable ) ) : ?><span class="drt-billable-tag" title="Billable">💰</span><?php endif; ?>
					<?php if ( ! empty( $log->remind ) ) : ?><span class="drt-remind-tag" title="Reminds every 5 minutes until you click Start or Done">🔔</span><?php endif; ?>
				</td>
				<td><span class="drt-badge" style="background:<?php echo esc_attr( $color ); ?>"><?php echo esc_html( $log->category ); ?></span></td>
				<td class="drt-status"><span class="drt-status-label"><?php echo esc_html( $static_status ); ?></span></td>
				<td class="drt-timer"><?php echo esc_html( $static_timer ); ?></td>
				<td class="drt-actions">
					<?php if ( $is_today ) : ?>
						<button class="button drt-btn-outline drt-btn-start" style="<?php echo $is_running ? 'display:none;' : ''; ?>">
							<span class="dashicons dashicons-controls-play"></span><span class="drt-btn-label"><?php echo $is_paused ? 'Resume' : 'Start'; ?></span>
						</button>
						<button class="button drt-btn-warn drt-btn-pause" style="<?php echo $is_running ? '' : 'display:none;'; ?>">
							<span class="dashicons dashicons-controls-pause"></span><span class="drt-btn-label">Pause</span>
						</button>
					<?php endif; ?>
					<button class="button drt-btn-done <?php echo $is_done ? 'drt-btn-active-done' : ''; ?>">
						<span class="dashicons dashicons-yes-alt"></span><span class="drt-btn-label">Done</span>
					</button>
					<button class="button drt-btn-ghost drt-btn-toggle-subtasks" title="Break this slot into individually-timed, billable tasks">
						<span class="dashicons dashicons-list-view"></span> Tasks
						<span class="drt-subtask-count-badge"><?php echo count( $subtasks ); ?></span>
					</button>
					<button class="button drt-btn-ghost drt-btn-danger-text drt-btn-undo-done" style="<?php echo $is_done ? '' : 'display:none;'; ?>" title="Flip this back to Missed">
						<span class="dashicons dashicons-undo"></span> Undo
					</button>
					<?php if ( $is_adhoc ) : ?>
						<a class="button drt-btn-ghost drt-btn-danger-text drt-btn-icon-only drt-btn-delete-adhoc" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=drt_delete_adhoc_log&id=' . $log->id . '&date=' . $date ), 'drt_delete_adhoc_log' ) ); ?>" onclick="return confirm('Remove this one-off task?');" title="Remove this one-off task">
							<span class="dashicons dashicons-trash"></span>
						</a>
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
									<th style="width:150px;"></th>
								</tr>
							</thead>
							<tbody class="drt-subtask-list">
							<?php foreach ( $subtasks as $st ) : ?>
								<?php
								$st_running = ! empty( $st->actual_start ) && empty( $st->actual_end );
								$st_paused  = empty( $st->actual_start ) && ! empty( $st->banked_seconds ) && empty( $st->actual_end );
								?>
								<tr class="drt-subtask-row"
									data-subtask-id="<?php echo esc_attr( $st->id ); ?>"
									data-actual-start="<?php echo esc_attr( $st->actual_start ); ?>"
									data-actual-end="<?php echo esc_attr( $st->actual_end ); ?>"
									data-banked-seconds="<?php echo esc_attr( (int) $st->banked_seconds ); ?>"
									data-duration="<?php echo esc_attr( $st->duration_seconds ); ?>">
									<td><?php echo esc_html( $st->title ); ?></td>
									<td>
										<label class="drt-billable-toggle">
											<input type="checkbox" class="drt-subtask-billable" <?php checked( $st->billable, 1 ); ?>>
											<span>Billable</span>
										</label>
									</td>
									<td class="drt-subtask-timer">—</td>
									<td class="drt-subtask-row-actions">
										<button class="button drt-btn-ghost drt-btn-icon-only drt-btn-pause-subtask" style="<?php echo $st_running ? '' : 'display:none;'; ?>" title="Pause"><span class="dashicons dashicons-controls-pause"></span></button>
										<button class="button drt-btn-ghost drt-btn-icon-only drt-btn-resume-subtask" style="<?php echo $st_paused ? '' : 'display:none;'; ?>" title="Resume"><span class="dashicons dashicons-controls-play"></span></button>
										<button class="button drt-btn-ghost drt-btn-icon-only drt-btn-stop-subtask" style="<?php echo $st->actual_end ? 'display:none;' : ''; ?>" title="Stop &amp; log"><span class="dashicons dashicons-yes-alt"></span></button>
										<button class="button drt-btn-ghost drt-btn-danger-text drt-btn-icon-only drt-btn-delete-subtask" title="Delete"><span class="dashicons dashicons-trash"></span></button>
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
							<button class="button button-primary drt-btn-add-subtask"><span class="dashicons dashicons-plus-alt2"></span> Add</button>
						</div>
						<p class="description" style="margin-top:6px;">Leave minutes blank to start a live timer instead (pause/stop it later); fill it in to log a task with an exact time right away.</p>
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
		<label class="drt-adhoc-reminder"><input type="checkbox" name="remind" value="1"> Remind me (every 5 min until Start/Done)</label>
		<label class="drt-adhoc-reminder"><input type="checkbox" name="billable" value="1"> Billable</label>
		<button type="submit" class="button button-primary">Add task to <?php echo esc_html( $date ); ?></button>
	</form>
	<p class="description">This only adds the task to <?php echo esc_html( $date_label ); ?> — your recurring weekday/weekend routine is untouched. Edit the recurring routine itself, or manage all one-off tasks with reminders, from Routine Editor.</p>
</div>
