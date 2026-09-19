=== Daily Routine Tracker ===
Contributors: Mahesh Pandey
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.7.0
License: GPLv2 or later

A private, single-site plugin to run and track a personal hour-by-hour daily routine.

== Description ==

Three admin screens under "Routine Tracker" in the WP admin sidebar:

1. **Day View** (was "Today") — shows any single day's schedule: today by
   default, or navigate with Prev/Next, the date picker, or a direct
   `&date=YYYY-MM-DD` link. The current time slot is highlighted
   automatically when viewing today; live timers, the tab-title countdown,
   and desktop notifications only run on today's view. Click "Start" to
   time exactly how long you actually spend on a slot (e.g. 15 of a
   scheduled 25 minutes), then "Done" to log it — or skip Start and Done
   just logs the slot's full scheduled length. Every slot defaults to
   **Missed** until you click Done; both Done and Missed are permanent
   toggle buttons you can flip any time, even days later. Notes save
   automatically as you type.

   Use **+ Add a task for this day** at the bottom to schedule a one-off
   task for that specific date only (e.g. "Dentist appointment" tomorrow,
   or a deadline crunch task next Tuesday) — it's tagged "one-off" in the
   list and has its own Remove link, and it never touches your recurring
   weekday/weekend template.

   Each slot also has a **+ Tasks** panel so you can break one time block
   into several individually-timed pieces — e.g. a 25-minute "Client Work"
   slot split into a 10-minute "Rosie — logo tweaks" task and a 15-minute
   "Study" task, each with its own Start/Stop timer and a **Billable**
   checkbox. The panel also has an editable "Logged time" field for the
   slot itself, in case the auto-computed value needs correcting.

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
- Nothing is deleted automatically — logs accumulate indefinitely (~18-20
  rows/day), which MySQL handles fine for years of use. The Reports page's
  on-screen tables cap at 200 rows for large ranges (with a CSV export link
  for the full set), and there's a manual "Delete logs older than [date]"
  tool at the bottom of Reports if you want to trim old history yourself.
- To move everything to a new site: on the old site, Reports → Data,
  Storage & Backup → "Export All Data (JSON)". On the fresh new site
  (right after activating the plugin there, before adding any data of its
  own), use the Import field in the same section. Import always inserts
  as new rows — it's for a clean destination, not merging into a site
  that already has its own logs.
- Auto Done slots (Routine Editor) only complete once their scheduled
  start time actually arrives, live if you have Day View open on that day,
  or on the next page load otherwise — never ahead of time.
