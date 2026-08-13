# local_kopere_recertification

Recertification engine for Moodle focused on preserving auditable history before any user data is reset.

## Compatibility

- Moodle 4.5 through 5.1 (`$plugin->supported = [405, 501]`)
- PHP versions supported by the corresponding Moodle release
- Plugin directory: `local/kopere_recertification`

## Safety model

The central entity is `local_recert_cycle`. Every archived activity, copied file, execution log and notification is tied to a cycle.

A real kopere_recertification runs per user/course under a Moodle Lock and a Delegated Transaction in this order:

1. Build the execution plan using the real course section/module order.
2. Create every enabled history snapshot.
3. Validate/copy every enabled file archive through the File API.
4. Only after all preservation phases succeed, clean activity data in course order.
5. Clean enabled system data after activities.
6. Mark the cycle active and commit.
7. Trigger business events and notifications only after commit.

Any exception before commit rolls the individual kopere_recertification back. Failure state/logging is written after rollback so the diagnostic survives without accepting a partially reset cycle.

Simulation calls the same history, file-selection and cleanup providers inside a transaction and always rolls back. File copy simulation resolves and validates the exact source selection without creating physical file-pool side effects. Business messages/events are not emitted by the simulation flow.

## Global tasks

Data handling rules are global. A component can have only one global task (`local_recert_task.component` is unique).

Bundled specialized providers:

- `recerttask_quiz` → `mod_quiz`
- `recerttask_forum` → `mod_forum`
- `recerttask_grades` → `core_grades`
- `recerttask_activitycompletion` → `core_activitycompletion`
- `recerttask_coursecompletion` → `core_coursecompletion`
- `recerttask_competency` → `core_competency`
- `recerttask_enrolment` → `core_enrolment`

Installing a specialized provider does **not** automatically enable destructive processing. The administrator must explicitly create/configure the corresponding global task and enable History, Files and/or Cleanup. A specialized provider reserves its represented component, so a duplicate generic task cannot be created for it.

Generic cleanup is intentionally limited to activity components, tables declared by that module's `db/install.xml`, tables containing `userid` or `user_id`, and equality conditions built from approved bound placeholders. The module's primary instance table is never eligible.

## History

`local_recert_history` stores normalized snapshot fields independently from the current activity:

- component
- cmid
- instanceid
- activity name/type
- previous completion time
- previous grade
- HTML snapshot
- optional structured JSON

Historical files are copied to `local_kopere_recertification/historyfiles` with `itemid = historyid`. Source metadata is preserved in `local_recert_file`. File access is checked in `local_kopere_recertification_pluginfile()` against ownership/history capabilities.

## Mustache SQL helpers

Generic history templates support:

- `{{#sqlecho}} SELECT ... {{/sqlecho}}`
- `{{#sqltable}} SELECT ... {{/sqltable}}`

The SQL validator accepts read-only `SELECT` or safe `WITH ... SELECT`, rejects multiple statements and destructive/state-changing constructs, and only exposes bound parameters:

`:userid`, `:courseid`, `:cmid`, `:instanceid`, `:contextid`, `:cycleid`, `:kopere_recertificationid`.

Use Moodle table syntax such as `{tablename}`. User/course/module values are never concatenated into SQL by the engine.

## Course scheduling

Course configuration is separate from global data tasks. Supported references are:

- enrolment date
- fixed annual date
- course completion
- certificate/reference provider
- last completed kopere_recertification cycle

Future automatic cycles use the internal `scheduled` status so warning notices can be deduplicated before the due date without prematurely marking the learner's current certification invalid. At the due date the cycle becomes pending and is queued as a per-user Ad-hoc Task.

Manual self-kopere_recertification supports enrolment, completion, last kopere_recertification, or certificate reference plus a minimum number of days. Administrative and bulk kopere_recertification require a reason. Bulk operations enqueue independent user cycles/tasks.

Certificate-trigger support is exposed through `local_kopere_recertification\course\reference_date_provider_interface`. No certificate-specific provider is bundled in this first package; a certificate component is selectable only when an installed `recerttask_*` provider implements that interface, preventing silent fallback to an incorrect date.

## Logical kopere_recertification state

`pending`, `processing`, and `active` cycles mark the user as requiring kopere_recertification. The plugin does not block course/module access; it displays the kopere_recertification state and relies on the reset completion/grade state so the learner can complete the required activities again. A new `core\event\course_completed` event completes the active cycle.

## Notifications

Notices are unlimited rows per course/event (`local_recert_notice`) with a unique cycle/notice log preventing duplicate delivery. Delivery uses Moodle `message_send()` providers, so `message_kopereemail` can process messages when installed; it is optional.

## Backup, privacy and files

Course configuration/notices participate in course backup. Cycles/history/files are included only when user data is included. Global data tasks are installation-level and are not backed up with a course.

The Privacy API exports/deletes owned cycles, history, copied files, logs and notification logs. `createdby` is handled separately so deleting an administrator's privacy data does not delete another learner's history.

## Tests

The package includes PHPUnit coverage for cycle numbering/date rules, component uniqueness, plan ordering, generic cleanup protection, SQL helpers/validator, rollback paths, locks, notification deduplication, simulation rollback, cycle completion, and provider contracts.

See `VALIDATION.md` for checks performed while building this package and the commands that still need a real Moodle checkout/runtime.
