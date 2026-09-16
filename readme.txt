=== Daily Routine Tracker ===
Contributors: Mahesh Pandey
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

A private, single-site plugin to run and track a personal hour-by-hour daily routine.

== Description ==

Three admin screens under "Routine Tracker" in the WP admin sidebar:

1. **Today** — shows today's schedule (auto-switches between a weekday and
   weekend routine based on the day of the week). The current time slot is
   highlighted automatically. Click "Start" to time exactly how long you
   actually spend on a slot (e.g. 15 of a scheduled 25 minutes), then
   "Done" to log it — or skip Start and Done just logs the slot's full
   scheduled length. Every slot defaults to **Missed** until you click
   Done; both Done and Missed are permanent toggle buttons you can flip
   any time, even days later. Notes save automatically as you type.

   Each slot also has a **+ Tasks** panel so you can break one time block
   into several individually-timed pieces — e.g. a 25-minute "Client Work"
   slot split into a 10-minute "Rosie — logo tweaks" task and a 15-minute
   "Study" task, each with its own Start/Stop timer and a **Billable**
   checkbox.

2. **Routine Editor** — add, edit, or remove time slots for the weekday and
   weekend routines. Comes pre-seeded with a default routine on activation
   (only if the routine table is empty, so your edits are never overwritten
   by a re-activation).

3. **Reports** — pick Day / Week / Month / Year and an anchor date to see:
   completion rate, tasks done/missed, total tracked time, a category
   breakdown, a day-by-day completion table, a **Billable / Invoicing**
   section (billable vs non-billable task counts and hours, plus the full
   list of logged tasks — handy for totting up what to bill a client at
   the end of the week), and the full slot-level task log with notes.

== Installation ==

1. Zip the `daily-routine-tracker` folder (or upload it as-is via SFTP to
   `wp-content/plugins/`).
2. In WP Admin → Plugins, upload/activate "Daily Routine Tracker".
3. Go to Routine Tracker → Today to start tracking, or Routine Editor to
   customize the default schedule first.

== Notes ==

- Data is stored in three custom tables (`wp_drt_routine`, `wp_drt_logs`,
  `wp_drt_subtasks`), not post meta, so reports stay fast even after years
  of daily logs.
- Each day's rows are generated once, the first time you open "Today" that
  day, from whichever routine (weekday/weekend) applies.
- All times use the WordPress site timezone (Settings → General), not the
  visitor's browser timezone.
- Uninstalling the plugin from wp-admin drops both tables. Back up first if
  you want to keep historical data.
