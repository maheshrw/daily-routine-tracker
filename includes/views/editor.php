<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'drt_render_slot_table' ) ) {
	function drt_render_slot_table( $slots ) {
		?>
		<table class="drt-table">
			<thead>
				<tr><th>Time</th><th>Title</th><th>Category</th><th style="width:130px;">Auto Done</th><th style="width:90px;"></th></tr>
			</thead>
			<tbody>
			<?php foreach ( $slots as $slot ) : ?>
				<?php $is_auto_done = ! empty( $slot->auto_done ); ?>
				<tr class="<?php echo $is_auto_done ? 'drt-slot-auto-done' : ''; ?>">
					<td class="drt-time"><?php echo esc_html( substr( $slot->start_time, 0, 5 ) . '–' . substr( $slot->end_time, 0, 5 ) ); ?></td>
					<td class="drt-title"><?php echo esc_html( $slot->title ); ?></td>
					<td><?php echo esc_html( ucfirst( $slot->category ) ); ?></td>
					<td>
						<a class="drt-toggle-switch <?php echo $is_auto_done ? 'is-on' : ''; ?>" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=drt_toggle_auto_done&id=' . $slot->id . '&auto_done=' . ( $is_auto_done ? 0 : 1 ) ), 'drt_toggle_auto_done' ) ); ?>" title="<?php echo $is_auto_done ? 'Click to turn off' : 'Click to mark this slot Auto Done'; ?>">
							<span class="drt-toggle-track"><span class="drt-toggle-thumb"></span></span>
							<span class="drt-toggle-label"><?php echo $is_auto_done ? 'Auto Done' : 'Off'; ?></span>
						</a>
					</td>
					<td>
						<div class="drt-table-actions">
							<a class="button drt-btn-ghost drt-btn-icon-only" href="<?php echo esc_url( admin_url( 'admin.php?page=drt-editor&edit=' . $slot->id ) ); ?>" title="Edit"><span class="dashicons dashicons-edit"></span></a>
							<a class="button drt-btn-ghost drt-btn-danger-text drt-btn-icon-only" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=drt_delete_slot&id=' . $slot->id ), 'drt_delete_slot' ) ); ?>" onclick="return confirm('Remove this slot?');" title="Delete"><span class="dashicons dashicons-trash"></span></a>
						</div>
					</td>
				</tr>
			<?php endforeach; ?>
			<?php if ( empty( $slots ) ) : ?>
				<tr><td colspan="5">No slots yet.</td></tr>
			<?php endif; ?>
			</tbody>
		</table>
		<?php
	}
}

$categories = array( 'work', 'eat', 'exercise', 'read', 'game', 'rest', 'other' );
?>
<div class="wrap drt-wrap">
	<h1>Routine Editor</h1>

	<?php if ( isset( $_GET['saved'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p>Slot saved.</p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['deleted'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p>Slot removed.</p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['added'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p>Task added.</p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['removed'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p>Task removed.</p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['db_repaired'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p>Database tables re-checked — any missing columns have been added.</p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['auto_done_updated'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p>Auto Done setting updated — this applies from the next day that slot hasn't been generated yet.</p></div>
	<?php endif; ?>
	<?php if ( ! empty( $_GET['drt_error'] ) ) : ?>
		<div class="notice notice-error">
			<p>
				<strong>That task wasn't saved.</strong>
				Database said: <code><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['drt_error'] ) ) ); ?></code>
			</p>
			<p>
				This usually means the database table is out of date with the plugin files. Try:
				<a class="button drt-btn-outline" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=drt_force_db_upgrade' ), 'drt_force_db_upgrade' ) ); ?>"><span class="dashicons dashicons-update"></span> Re-check database tables</a>
			</p>
		</div>
	<?php endif; ?>

	<h2><?php echo $edit_slot ? 'Edit Slot' : 'Add New Slot'; ?></h2>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="drt-slot-form-card">
		<?php wp_nonce_field( 'drt_save_slot' ); ?>
		<input type="hidden" name="action" value="drt_save_slot">
		<input type="hidden" name="slot_id" value="<?php echo $edit_slot ? esc_attr( $edit_slot->id ) : 0; ?>">
		<label>Day Type
			<select name="day_type">
				<option value="weekday" <?php selected( $edit_slot ? $edit_slot->day_type : 'weekday', 'weekday' ); ?>>Weekday</option>
				<option value="weekend" <?php selected( $edit_slot ? $edit_slot->day_type : '', 'weekend' ); ?>>Weekend</option>
			</select>
		</label>
		<label>Start Time <input type="time" name="start_time" value="<?php echo $edit_slot ? esc_attr( substr( $edit_slot->start_time, 0, 5 ) ) : ''; ?>" required></label>
		<label>End Time <input type="time" name="end_time" value="<?php echo $edit_slot ? esc_attr( substr( $edit_slot->end_time, 0, 5 ) ) : ''; ?>" required></label>
		<label class="drt-adhoc-title">Title <input type="text" name="title" class="regular-text" value="<?php echo $edit_slot ? esc_attr( $edit_slot->title ) : ''; ?>" required></label>
		<label>Category
			<select name="category">
				<?php foreach ( $categories as $cat ) : ?>
					<option value="<?php echo esc_attr( $cat ); ?>" <?php selected( $edit_slot ? $edit_slot->category : 'other', $cat ); ?>><?php echo esc_html( ucfirst( $cat ) ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<button type="submit" class="button button-primary"><span class="dashicons dashicons-plus-alt2"></span> <?php echo $edit_slot ? 'Update Slot' : 'Add Slot'; ?></button>
	</form>

	<h2 style="margin-bottom:4px;margin-top:28px;">Weekday &amp; Weekend Routine</h2>
	<p class="description">
		<strong>Auto Done</strong> marks a slot as already completed the moment its daily entry is created — no click needed, and it never shows as Missed. It logs the slot's full scheduled length automatically. This is permanent until you click "Turn off" here, and only affects days not yet generated (days already showing in Day View keep whatever status they have).
	</p>
	<div class="drt-editor-columns">
		<div>
			<h3>Weekday Routine</h3>
			<?php drt_render_slot_table( $weekday ); ?>
		</div>
		<div>
			<h3>Weekend Routine</h3>
			<?php drt_render_slot_table( $weekend ); ?>
		</div>
	</div>

	<h2 style="margin-top:32px;">One-off tasks on specific dates</h2>
	<p class="description">
		Add a task to a single date without touching the recurring weekday/weekend routine above — and optionally get a desktop reminder notification when it's time.
	</p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="drt-adhoc-form">
		<?php wp_nonce_field( 'drt_add_adhoc_log' ); ?>
		<input type="hidden" name="action" value="drt_add_adhoc_log">
		<input type="hidden" name="redirect" value="editor">
		<label>Date <input type="date" name="log_date" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" required></label>
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
		<label class="drt-adhoc-reminder"><input type="checkbox" name="remind" value="1"> Remind me</label>
		<label class="drt-adhoc-reminder"><input type="checkbox" name="billable" value="1"> Billable</label>
		<button type="submit" class="button button-primary"><span class="dashicons dashicons-plus-alt2"></span> Add task</button>
	</form>

	<h2 style="margin-top:28px;">Upcoming one-off tasks</h2>
	<table class="drt-table">
		<thead><tr><th>Date</th><th>Time</th><th>Title</th><th>Category</th><th>Billable</th><th>Reminder</th><th style="width:130px;"></th></tr></thead>
		<tbody>
		<?php foreach ( $upcoming_adhoc as $log ) : ?>
			<tr>
				<td><?php echo esc_html( $log->log_date ); ?></td>
				<td class="drt-time"><?php echo esc_html( substr( $log->scheduled_start, 0, 5 ) . '–' . substr( $log->scheduled_end, 0, 5 ) ); ?></td>
				<td class="drt-title"><?php echo esc_html( $log->title ); ?></td>
				<td><?php echo esc_html( ucfirst( $log->category ) ); ?></td>
				<td><?php echo $log->billable ? '💰 Yes' : '—'; ?></td>
				<td><?php echo $log->remind ? '🔔 On' : '—'; ?></td>
				<td>
					<div class="drt-table-actions">
						<a class="button drt-btn-ghost drt-btn-icon-only" href="<?php echo esc_url( admin_url( 'admin.php?page=drt-today&date=' . $log->log_date ) ); ?>" title="View day"><span class="dashicons dashicons-calendar-alt"></span></a>
						<a class="button drt-btn-ghost drt-btn-danger-text drt-btn-icon-only" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=drt_delete_adhoc_log&id=' . $log->id . '&date=' . $log->log_date . '&redirect=editor' ), 'drt_delete_adhoc_log' ) ); ?>" onclick="return confirm('Remove this task?');" title="Remove"><span class="dashicons dashicons-trash"></span></a>
					</div>
				</td>
			</tr>
		<?php endforeach; ?>
		<?php if ( empty( $upcoming_adhoc ) ) : ?>
			<tr><td colspan="7">No upcoming one-off tasks.</td></tr>
		<?php endif; ?>
		</tbody>
	</table>

	<p class="description" style="margin-top:20px;">
		<a class="button drt-btn-ghost" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=drt_force_db_upgrade' ), 'drt_force_db_upgrade' ) ); ?>"><span class="dashicons dashicons-update"></span> Re-check database tables</a>
		— safe to click any time; adds any columns/tables the current plugin version needs without touching existing data. Use this after updating the plugin files if something stops saving.
	</p>
</div>
