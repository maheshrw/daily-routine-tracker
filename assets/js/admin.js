(function ($) {
	'use strict';

	if (typeof DRT === 'undefined') {
		return;
	}

	// Offset between the browser clock and the WP server clock (site
	// timezone), so all comparisons use the site's time, not the visitor's.
	var serverNowMs = new Date(DRT.today + 'T' + DRT.now).getTime();
	var offsetMs = serverNowMs - Date.now();

	var originalTitle = document.title;
	var notifiedEnded = {}; // log_id -> true once we've handled its end-of-slot notification

	function serverNow() {
		return new Date(Date.now() + offsetMs);
	}

	function pad(n) {
		return n < 10 ? '0' + n : '' + n;
	}

	function formatHMS(totalSeconds) {
		totalSeconds = Math.max(0, Math.floor(totalSeconds));
		var h = Math.floor(totalSeconds / 3600);
		var m = Math.floor((totalSeconds % 3600) / 60);
		var s = totalSeconds % 60;
		return pad(h) + ':' + pad(m) + ':' + pad(s);
	}

	function timeStringToSeconds(timeStr) {
		var parts = (timeStr || '00:00:00').split(':').map(Number);
		return parts[0] * 3600 + parts[1] * 60 + (parts[2] || 0);
	}

	function secondsSinceMidnight(date) {
		return date.getHours() * 3600 + date.getMinutes() * 60 + date.getSeconds();
	}

	function mysqlDatetimeToMs(str) {
		if (!str) {
			return null;
		}
		var t = new Date(str.replace(' ', 'T')).getTime();
		return isNaN(t) ? null : t;
	}

	function notifyPermissionGranted() {
		return ( 'Notification' in window ) && Notification.permission === 'granted';
	}

	function notifySlotEnded(title, startStr, endStr) {
		if ( ! notifyPermissionGranted() ) {
			return;
		}
		try {
			new Notification( 'Slot ended: ' + title, {
				body: 'Scheduled ' + (startStr || '').substring(0, 5) + '\u2013' + (endStr || '').substring(0, 5) + '. Mark it Done or Missed.'
			} );
		} catch (e) {
			// Some browsers throw if Notification is called outside a
			// secure context / service worker requirement — fail quietly.
		}
	}

	function tickClock() {
		var now = serverNow();
		$('#drt-live-clock').text(
			pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds())
		);
	}

	function tickRows() {
		var nowMs = Date.now() + offsetMs;
		var nowSec = secondsSinceMidnight(new Date(nowMs));
		var activeSlot = null;

		$('.drt-row').each(function () {
			var $row = $(this);
			var logId = $row.data('log-id');
			var start = timeStringToSeconds($row.data('start'));
			var end = timeStringToSeconds($row.data('end'));
			var status = $row.data('status');
			var actualStart = $row.attr('data-actual-start');
			var loggedDuration = $row.attr('data-logged-duration');

			var isFuture = nowSec < start;
			var isActive = nowSec >= start && nowSec < end;
			var isPast = ! isFuture && ! isActive;

			$row.toggleClass('drt-active', isActive);

			if (isActive && status !== 'done') {
				activeSlot = {
					title: $row.find('.drt-title').text(),
					remaining: end - nowSec
				};
			}

			// Fire a one-time desktop notification the moment a slot's
			// scheduled end passes, if it wasn't already marked Done.
			if (!(logId in notifiedEnded)) {
				notifiedEnded[logId] = isPast; // seed: don't notify for slots already past on page load
			} else if (isPast && !notifiedEnded[logId]) {
				notifiedEnded[logId] = true;
				if (status !== 'done') {
					notifySlotEnded($row.find('.drt-title').text(), $row.data('start'), $row.data('end'));
				}
			}

			var $timer = $row.find('.drt-timer');
			var $statusLabel = $row.find('.drt-status-label');

			$timer.removeClass('drt-timer-live drt-timer-future drt-timer-past drt-timer-done drt-timer-ambient');

			if (status === 'done') {
				var secs = parseInt(loggedDuration, 10);
				$timer.text('✓ ' + (isNaN(secs) ? '—' : formatHMS(secs)) + ' logged').addClass('drt-timer-done');
			} else if (actualStart) {
				var startMs = mysqlDatetimeToMs(actualStart);
				var elapsed = startMs ? (nowMs - startMs) / 1000 : 0;
				$timer.text('recording ' + formatHMS(elapsed)).addClass('drt-timer-live');
			} else if (isFuture) {
				$timer.text('starts in ' + formatHMS(start - nowSec)).addClass('drt-timer-future');
			} else if (isActive) {
				$timer.text('elapsed ' + formatHMS(nowSec - start)).addClass('drt-timer-ambient');
			} else {
				$timer.text('slot ended').addClass('drt-timer-past');
			}

			$row.removeClass('drt-status-done drt-status-missed-final drt-status-upcoming drt-status-inprogress');
			if (status === 'done') {
				$statusLabel.text('Done');
				$row.addClass('drt-status-done');
			} else if (isFuture) {
				$statusLabel.text('Upcoming');
				$row.addClass('drt-status-upcoming');
			} else if (isActive) {
				$statusLabel.text('In progress');
				$row.addClass('drt-status-inprogress');
			} else {
				$statusLabel.text('Missed');
				$row.addClass('drt-status-missed-final');
			}
		});

		if (activeSlot) {
			document.title = '\u23F3 ' + formatHMS(activeSlot.remaining) + ' left \u00B7 ' + activeSlot.title;
		} else {
			document.title = originalTitle;
		}

		$('.drt-subtask-row').each(function () {
			var $row = $(this);
			var actualStart = $row.attr('data-actual-start');
			var actualEnd = $row.attr('data-actual-end');
			var $timer = $row.find('.drt-subtask-timer');

			if (actualEnd) {
				var d = parseInt($row.attr('data-duration'), 10);
				$timer.text(isNaN(d) ? '—' : formatHMS(d) + ' logged');
			} else if (actualStart) {
				var startMs = mysqlDatetimeToMs(actualStart);
				var elapsed = startMs ? (nowMs - startMs) / 1000 : 0;
				$timer.text(formatHMS(elapsed) + ' running');
			} else {
				$timer.text('—');
			}
		});
	}

	function ajax(action, data, done) {
		data = data || {};
		data.action = action;
		data.nonce = DRT.nonce;
		$.post(DRT.ajaxUrl, data, function (resp) {
			if (resp && resp.success) {
				done(resp.data || {});
			}
		});
	}

	function openPanel(logId) {
		$('.drt-subtask-panel[data-panel-for="' + logId + '"]').show();
	}

	function setStoredStatus($row, status, extra) {
		extra = extra || {};
		$row.data('status', status);
		$row.attr('data-status', status);
		if ('actualStart' in extra) {
			$row.attr('data-actual-start', extra.actualStart || '');
		}
		if ('loggedDuration' in extra) {
			$row.attr('data-logged-duration', extra.loggedDuration == null ? '' : extra.loggedDuration);
			var logId = $row.data('log-id');
			var $durationInput = $('.drt-subtask-panel[data-panel-for="' + logId + '"] .drt-duration-input');
			if (extra.loggedDuration != null && $durationInput.length) {
				$durationInput.val(Math.round((extra.loggedDuration / 60) * 10) / 10);
			}
		}
		$row.find('.drt-btn-done').toggleClass('drt-btn-active-done', status === 'done');
		$row.find('.drt-btn-missed').toggleClass('drt-btn-active-missed', status === 'missed');
	}

	$(document).on('click', '.drt-btn-start', function () {
		var $row = $(this).closest('.drt-row');
		ajax('drt_start_task', { log_id: $row.data('log-id') }, function (data) {
			setStoredStatus($row, $row.data('status'), { actualStart: data.actual_start });
		});
	});

	$(document).on('click', '.drt-btn-done', function () {
		var $row = $(this).closest('.drt-row');
		var logId = $row.data('log-id');

		if ($row.data('status') === 'done') {
			// Already done — just open the panel to review/edit, don't
			// re-run the auto-calc and clobber a manual edit.
			openPanel(logId);
			return;
		}

		ajax('drt_mark_done', { log_id: logId }, function (data) {
			setStoredStatus($row, 'done', { loggedDuration: data.duration_seconds });
			openPanel(logId);
		});
	});

	$(document).on('click', '.drt-btn-missed', function () {
		var $row = $(this).closest('.drt-row');
		ajax('drt_mark_missed', { log_id: $row.data('log-id') }, function () {
			setStoredStatus($row, 'missed', { actualStart: '', loggedDuration: '' });
		});
	});

	var notesTimers = {};
	$(document).on('input', '.drt-notes', function () {
		var $textarea = $(this);
		var $row = $textarea.closest('.drt-row');
		var logId = $row.data('log-id');
		clearTimeout(notesTimers[logId]);
		notesTimers[logId] = setTimeout(function () {
			ajax('drt_save_notes', { log_id: logId, notes: $textarea.val() }, function () {});
		}, 700);
	});

	/* ---------------------- Subtasks panel ---------------------- */

	$(document).on('click', '.drt-btn-toggle-subtasks', function () {
		var $row = $(this).closest('.drt-row');
		var logId = $row.data('log-id');
		$('.drt-subtask-panel[data-panel-for="' + logId + '"]').toggle();
	});

	var durationTimers = {};
	$(document).on('input', '.drt-duration-input', function () {
		var $input = $(this);
		var $panelRow = $input.closest('.drt-subtask-panel');
		var logId = $panelRow.data('panel-for');
		var $mainRow = $('.drt-row[data-log-id="' + logId + '"]');
		var $saved = $panelRow.find('.drt-duration-saved');

		clearTimeout(durationTimers[logId]);
		durationTimers[logId] = setTimeout(function () {
			var minutes = parseFloat($input.val());
			if (isNaN(minutes) || minutes < 0) {
				return;
			}
			ajax('drt_update_duration', { log_id: logId, minutes: minutes }, function (data) {
				$mainRow.attr('data-logged-duration', data.duration_seconds);
				// Editing the time implies the slot is done.
				if ($mainRow.data('status') !== 'done') {
					setStoredStatus($mainRow, 'done', {});
				}
				$saved.stop(true).show().delay(1200).fadeOut();
			});
		}, 600);
	});

	$(document).on('click', '.drt-btn-add-subtask', function () {
		var $btn = $(this);
		var $box = $btn.closest('.drt-subtask-box');
		var $panelRow = $btn.closest('.drt-subtask-panel');
		var logId = $panelRow.data('panel-for');
		var $titleInput = $box.find('.drt-subtask-title-input');
		var $minutesInput = $box.find('.drt-subtask-minutes-input');
		var title = $.trim($titleInput.val());
		var minutes = $.trim($minutesInput.val());
		var billable = $box.find('.drt-subtask-billable-input').is(':checked');

		if (!title) {
			$titleInput.focus();
			return;
		}

		var payload = { log_id: logId, title: title, billable: billable ? 1 : 0 };
		if (minutes !== '') {
			payload.minutes = minutes;
		}

		ajax('drt_add_subtask', payload, function (data) {
			var isCompleted = data.duration_seconds !== null && data.duration_seconds !== undefined && data.duration_seconds !== '';
			var stopBtnStyle = isCompleted ? ' style="display:none;"' : '';

			var $newRow = $(
				'<tr class="drt-subtask-row" data-subtask-id="' + data.id + '" data-actual-start="' + (data.actual_start || '') + '" data-actual-end="' + (data.actual_end || '') + '" data-duration="' + (data.duration_seconds || '') + '">' +
					'<td>' + $('<div>').text(data.title).html() + '</td>' +
					'<td><label class="drt-billable-toggle"><input type="checkbox" class="drt-subtask-billable" ' + (data.billable ? 'checked' : '') + '><span>Billable</span></label></td>' +
					'<td class="drt-subtask-timer">—</td>' +
					'<td><button class="button-link drt-btn-stop-subtask"' + stopBtnStyle + '>Stop</button> <button class="button-link drt-btn-delete-subtask" title="Delete">✕</button></td>' +
				'</tr>'
			);
			$box.find('.drt-subtask-list').append($newRow);
			$titleInput.val('');
			$minutesInput.val('');
			$box.find('.drt-subtask-billable-input').prop('checked', false);

			var $mainRow = $('.drt-row[data-log-id="' + logId + '"]');
			var $countEl = $mainRow.find('.drt-subtask-count');
			$countEl.text((parseInt($countEl.text(), 10) || 0) + 1);
		});
	});

	$(document).on('click', '.drt-btn-stop-subtask', function () {
		var $row = $(this).closest('.drt-subtask-row');
		ajax('drt_stop_subtask', { subtask_id: $row.data('subtask-id') }, function (data) {
			$row.attr('data-actual-end', 'x');
			$row.attr('data-duration', data.duration_seconds);
			$row.find('.drt-btn-stop-subtask').hide();
		});
	});

	$(document).on('change', '.drt-subtask-billable', function () {
		var $row = $(this).closest('.drt-subtask-row');
		var billable = $(this).is(':checked');
		ajax('drt_toggle_subtask_billable', { subtask_id: $row.data('subtask-id'), billable: billable ? 1 : 0 }, function () {});
	});

	$(document).on('click', '.drt-btn-delete-subtask', function () {
		var $row = $(this).closest('.drt-subtask-row');
		var $panel = $row.closest('.drt-subtask-panel');
		var logId = $panel.data('panel-for');
		if (!confirm('Remove this task?')) {
			return;
		}
		ajax('drt_delete_subtask', { subtask_id: $row.data('subtask-id') }, function () {
			$row.remove();
			var $mainRow = $('.drt-row[data-log-id="' + logId + '"]');
			var $countEl = $mainRow.find('.drt-subtask-count');
			$countEl.text(Math.max(0, (parseInt($countEl.text(), 10) || 0) - 1));
		});
	});

	function updateNotifButton() {
		var $btn = $('#drt-enable-notifications');
		if (!$btn.length) {
			return;
		}
		if (!('Notification' in window)) {
			$btn.hide();
			return;
		}
		if (Notification.permission === 'granted') {
			$btn.text('🔔 Notifications on').prop('disabled', true);
		} else if (Notification.permission === 'denied') {
			$btn.text('🔕 Notifications blocked (check browser settings)').prop('disabled', true);
		} else {
			$btn.text('🔔 Enable notifications').prop('disabled', false);
		}
	}

	$(document).on('click', '#drt-enable-notifications', function () {
		if (!('Notification' in window)) {
			return;
		}
		Notification.requestPermission().then(function () {
			updateNotifButton();
		});
	});

	$(function () {
		if ($('#drt-live-clock').length) {
			updateNotifButton();
			tickClock();
			tickRows();
			setInterval(tickClock, 1000);
			setInterval(tickRows, 1000);
		}
	});
})(jQuery);
