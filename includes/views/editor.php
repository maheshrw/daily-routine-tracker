<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
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

	<h2><?php echo $edit_slot ? 'Edit Slot' : 'Add New Slot'; ?></h2>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="drt-slot-form">
		<?php wp_nonce_field( 'drt_save_slot' ); ?>
		<input type="hidden" name="action" value="drt_save_slot">
		<input type="hidden" name="slot_id" value="<?php echo $edit_slot ? esc_attr( $edit_slot->id ) : 0; ?>">
		<table class="form-table">
			<tr>
				<th>Day Type</th>
				<td>
					<select name="day_type">
						<option value="weekday" <?php selected( $edit_slot ? $edit_slot->day_type : 'weekday', 'weekday' ); ?>>Weekday</option>
						<option value="weekend" <?php selected( $edit_slot ? $edit_slot->day_type : '', 'weekend' ); ?>>Weekend</option>
					</select>
				</td>
			</tr>
			<tr>
				<th>Start Time</th>
				<td><input type="time" name="start_time" value="<?php echo $edit_slot ? esc_attr( substr( $edit_slot->start_time, 0, 5 ) ) : ''; ?>" required></td>
			</tr>
			<tr>
				<th>End Time</th>
				<td><input type="time" name="end_time" value="<?php echo $edit_slot ? esc_attr( substr( $edit_slot->end_time, 0, 5 ) ) : ''; ?>" required></td>
			</tr>
			<tr>
				<th>Title</th>
				<td><input type="text" name="title" class="regular-text" value="<?php echo $edit_slot ? esc_attr( $edit_slot->title ) : ''; ?>" required></td>
			</tr>
			<tr>
				<th>Category</th>
				<td>
					<select name="category">
						<?php foreach ( $categories as $cat ) : ?>
							<option value="<?php echo esc_attr( $cat ); ?>" <?php selected( $edit_slot ? $edit_slot->category : 'other', $cat ); ?>><?php echo esc_html( ucfirst( $cat ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
		</table>
		<?php submit_button( $edit_slot ? 'Update Slot' : 'Add Slot' ); ?>
	</form>

	<hr>

	<div class="drt-editor-columns">
		<div>
			<h2>Weekday Routine</h2>
			<?php self_drt_render_slot_table( $weekday ); ?>
		</div>
		<div>
			<h2>Weekend Routine</h2>
			<?php self_drt_render_slot_table( $weekend ); ?>
		</div>
	</div>
</div>

<?php
if ( ! function_exists( 'self_drt_render_slot_table' ) ) :
function self_drt_render_slot_table( $slots ) {
	?>
	<table class="widefat striped">
		<thead>
			<tr><th>Time</th><th>Title</th><th>Category</th><th></th></tr>
		</thead>
		<tbody>
		<?php foreach ( $slots as $slot ) : ?>
			<tr>
				<td><?php echo esc_html( substr( $slot->start_time, 0, 5 ) . '–' . substr( $slot->end_time, 0, 5 ) ); ?></td>
				<td><?php echo esc_html( $slot->title ); ?></td>
				<td><?php echo esc_html( ucfirst( $slot->category ) ); ?></td>
				<td>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=drt-editor&edit=' . $slot->id ) ); ?>">Edit</a>
					|
					<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=drt_delete_slot&id=' . $slot->id ), 'drt_delete_slot' ) ); ?>" onclick="return confirm('Remove this slot?');">Delete</a>
				</td>
			</tr>
		<?php endforeach; ?>
		<?php if ( empty( $slots ) ) : ?>
			<tr><td colspan="4">No slots yet.</td></tr>
		<?php endif; ?>
		</tbody>
	</table>
	<?php
}
endif;
