# local_kopere_recert

Recertification engine for Moodle focused on preserving auditable history before any user data is reset.

## Safety model

The central entity is `local_recert_cycle`. Every archived activity, copied file, execution log and notification is tied to a cycle.

A real kopere_recert runs per user/course under a Moodle Lock and a Delegated Transaction in this order:

1. Build the execution plan using the real course section/module order.
2. Create every enabled history snapshot.
3. Validate/copy every enabled file archive through the File API.
4. Only after all preservation phases succeed, clean activity data in course order.
5. Clean enabled system data after activities.
6. Mark the cycle active and commit.
7. Trigger business events and notifications only after commit.

Any exception before commit rolls the individual kopere_recert back. Failure state/logging is written after rollback so the diagnostic survives without accepting a partially reset cycle.

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

The presence of a specialized provider does **not** automatically enable destructive processing. The administrator must explicitly create/configure the corresponding global task and enable History, Files and/or Cleanup. A specialized provider reserves its represented component, so a duplicate generic task cannot be created for it.

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

Historical files are copied to `local_kopere_recert/historyfiles` with `itemid = historyid`. Source metadata is preserved in `local_recert_file`. File access is checked in `local_kopere_recert_pluginfile()` against ownership/history capabilities.

## Mustache SQL helpers

Generic history templates support:

- `{{#sqlecho}} SELECT ... {{/sqlecho}}`
- `{{#sqltable}} SELECT ... {{/sqltable}}`

The SQL validator accepts read-only `SELECT` or safe `WITH ... SELECT`, rejects multiple statements and destructive/state-changing constructs, and only exposes bound parameters:

`:userid`, `:courseid`, `:cmid`, `:instanceid`, `:contextid`, `:cycleid`, `:kopere_recertid`.

Use Moodle table syntax such as `{tablename}`. User/course/module values are never concatenated into SQL by the engine.

## Course scheduling

Course configuration is separate from global data tasks. Supported references are:

- enrolment date
- fixed annual date
- course completion
- certificate/reference provider
- last completed kopere_recert cycle

Future automatic cycles use the internal `scheduled` status so warning notices can be deduplicated before the due date without prematurely marking the learner's current certification invalid. At the due date the cycle becomes pending and is queued as a per-user Ad-hoc Task.

Manual self-kopere_recert supports enrolment, completion, last kopere_recert, or certificate reference plus a minimum number of days. Administrative and bulk kopere_recert require a reason. Bulk operations enqueue independent user cycles/tasks.

Certificate-trigger support is exposed through `local_kopere_recert\course\reference_date_provider_interface`. No certificate-specific provider is bundled in this first package; a certificate component is selectable only when an installed `recerttask_*` provider implements that interface, preventing silent fallback to an incorrect date.

## Logical kopere_recert state

`pending`, `processing`, and `active` cycles mark the user as requiring kopere_recert. The plugin does not block course/module access; it displays the kopere_recert state and relies on the reset completion/grade state so the learner can complete the required activities again. A new `core\event\course_completed` event completes the active cycle.

## Notifications

Notices are unlimited rows per course/event (`local_recert_notice`) with a unique cycle/notice log preventing duplicate delivery. Delivery uses Moodle `message_send()` providers, so `message_kopereemail` can process messages when installed; it is optional.

## Backup, privacy and files

Course configuration/notices participate in course backup. Cycles/history/files are included only when user data is included. Global data tasks are installation-level and are not backed up with a course.

The Privacy API exports/deletes owned cycles, history, copied files, logs and notification logs. `createdby` is handled separately so deleting an administrator's privacy data does not delete another learner's history.

## Tests

The package includes PHPUnit coverage for cycle numbering/date rules, component uniqueness, plan ordering, generic cleanup protection, SQL helpers/validator, rollback paths, locks, notification deduplication, simulation rollback, cycle completion, and provider contracts.

See `VALIDATION.md` for checks performed while building this package and the commands that still need a real Moodle checkout/runtime.

## Optional Moodle MCP integration (ChatGPT)

When [local_mcp](https://github.com/EduardoKrausME/moodle-local_mcp)
is installed, it discovers the optional provider
`local_kopere_recert\mcp\\provider` in `classes/mcp/provider.php`.
The integration adds no hard dependency, no new database fields and no change
to existing scheduled tasks, subplugins or Moodle UI. Refresh the ChatGPT
MCP connection after updating the plugin.

The following tools are registered:

| MCP tool | Scope | Behavior |
| --- | --- | --- |
| `kopere_recert_list_my_cycles` | READ | List your own cycles and obtain a concurrency `state_hash`. |
| `kopere_recert_list_course_cycles` | READ | Inspect one learner's cycles with `viewallhistory` permission. |
| `kopere_recert_get_my_history` | READ | Read your own archived cycle history. |
| `kopere_recert_get_cycle_history` | READ | Read an authorized learner's archived cycle history. |
| `kopere_recert_get_course_config` | READ | Review course trigger and self-service settings. |
| `kopere_recert_get_execution_plan` | READ | Inspect the real course-ordered archival/file/cleanup plan. |
| `kopere_recert_simulate` | WRITE | Confirm a native rollback-only recertification simulation for one learner. |
| `kopere_recert_queue` | WRITE (destructive) | Confirm a manual learner or self-service recertification cycle. |
| `kopere_recert_queue_bulk` | WRITE (destructive) | Confirm independent recertification requests for at most ten learners. |

### Permissions and privacy

All READ tools require the MCP READ scope and their corresponding **course**
capabilities. The `viewownhistory` tools always use the connected Moodle
user ID, and validate cycle ownership before disclosing history. Other
learners' histories require `local/kopere_recert:viewallhistory`.
Course settings require `local/kopere_recert:manage`; the execution plan
requires `local/kopere_recert:simulate`. READ never archives, resets,
schedules, notifies or invokes the AI provider. History pagination is capped
at 40 entries per response; archived HTML and structured data are returned
as bounded previews rather than full user-data dumps.

All WRITE tools require the MCP WRITE scope, a one-time confirmation token and
their existing Moodle permissions. `kopere_recert_queue` distinguishes the
learner's `recertifyself` capability from the manager's `recertify`
capability and honors self-service eligibility/date rules.
`kopere_recert_queue_bulk` requires `bulkrecertify`, checks each learner's
enrollment and limits each confirmed request to ten distinct IDs.
`kopere_recert_simulate` requires `simulate` and returns a rollback-only
report; it is a WRITE tool because it invokes the real providers in an
uncommitted transaction.

### Safe execution workflow

1. Inspect `kopere_recert_get_course_config` and
   `kopere_recert_get_execution_plan` to understand which global
   archival and cleanup providers are configured for the course.
2. For a learner, call `kopere_recert_list_my_cycles` (self) or
   `kopere_recert_list_course_cycles` (authorized manager) to obtain
   their current `state_hash`. For a bulk request, obtain a separate
   `state_hash` for each learner.
3. Run `kopere_recert_simulate` and examine the rollback-only
   inspection before queuing a destructive real recertification.
4. Call `kopere_recert_queue` with `courseid`, `userid`,
   `reason` and `expected_state_hash`, or
   `kopere_recert_queue_bulk` with explicit `targets`.
   A preview identifies the course, learners, reason and side effects;
   nothing is queued before confirmation.
5. After confirmation, the existing `recertification\manager`
   enforces the per-user lock, rejects an already active cycle, logs
   creation and enqueues a Moodle ad-hoc task. The actual execution
   occurs **later in Moodle cron**, inside its normal archive-first
   transaction. Do not interpret a queued result as a completed reset.
   Follow up using the cycle-history READ tools.

The write tools recheck the expected cycle-state hash on execution.
A manually requested cycle may supersede a scheduled future cycle, as
already permitted by the native manager. Archive preservation precedes
all cleanup; failed execution retains the original transactional
rollback and failure logging. The MCP integration does not offer
direct SQL cleanup, instant execution of a pending cycle, changes to
global destructive task definitions or unrestricted access to
historical files.
