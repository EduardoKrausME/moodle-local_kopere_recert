# Changelog

This changelog documents only the two latest **published** releases of `local_kopere_recert`, verified on October 24, 2026. Each entry is based on the published Git tag compared with its immediately preceding published release tag. The October 20–21 `Mustache`, `Langs Order` and `-core_text` commits were made after `1.9.17` and are intentionally excluded.

## [1.9.17] - 2026-10-19

### Optional Moodle MCP integration
- Added automatic discovery through `local_kopere_recert\mcp\provider` when `local_mcp` is installed, with no hard plugin dependency or new database fields.
- Added six READ tools: `kopere_recert_list_my_cycles`, `kopere_recert_list_course_cycles`, `kopere_recert_get_my_history`, `kopere_recert_get_cycle_history`, `kopere_recert_get_course_config` and `kopere_recert_get_execution_plan`. These provide authorized access to recertification cycles, bounded history previews, course settings and the configured archive/file/cleanup execution plan without changing learner records.
- Added three confirmation-gated WRITE tools: `kopere_recert_simulate` (native rollback-only simulation), `kopere_recert_queue` (one learner or authorized self-service request) and `kopere_recert_queue_bulk` (up to ten distinct learners).
- Queued confirmed real recertification requests through the existing manager and Moodle ad-hoc tasks rather than performing an immediate reset. Successful queueing is not completion; execution occurs later through Moodle cron.

### Permissions and operational safeguards
- Required the appropriate MCP READ/WRITE scope and native Moodle course capabilities. Own-history access is limited to the connected learner, while viewing other learners requires `local/kopere_recert:viewallhistory`.
- Kept self-service and administrative recertification permissions separate; validated target enrolment and self-service eligibility before queuing actions.
- Added per-learner `expected_state_hash` checks, action previews and explicit confirmation before simulation or queueing, with bounded history pagination and output.
- Preserved the native cycle locking, existing scheduled-cycle replacement rules, archive-before-cleanup ordering, transaction rollback and failure logging. Bulk requests enqueue independent tasks and may report partial success.
- Marked simulation as a WRITE operation because it invokes real providers inside a rollback-only transaction; it does not persist a cycle or send notifications.

### Administration and documentation
- Added a dismissible Moodle MCP integration notice for site administrators, implemented with Mustache, translated English/Brazilian Portuguese text, styles and a session-protected user preference.
- Added integration instructions to `README.md` and regression tests for the MCP tools.
- Updated the historical release workflow with Git-ref-scoped concurrency controls.
- Updated `version.php` metadata to internal version `2026101900` and release `1.9.17`.

**Full comparison:** [1.9.16...1.9.17](https://github.com/EduardoKrausME/moodle-local_kopere_recert/compare/1.9.16...1.9.17)

## [1.9.16] - 2026-10-15

### CI compatibility and assets
- Updated the PostgreSQL CI service from version 16 to 17.
- Updated the MariaDB CI service from version 10 to 11.4, including the environment variables and `healthcheck.sh --connect --innodb_initialized` readiness check.
- Replaced the previous `pix/icon.svg` plugin icon with a new `pix/icon.png` asset.

### Release metadata
- Updated `version.php` from internal version `2026101200`, release `1.9.15`, to internal version `2026101500`, release `1.9.16`.
- No application logic files changed between the published tags.

**Full comparison:** [1.9.15...1.9.16](https://github.com/EduardoKrausME/moodle-local_kopere_recert/compare/1.9.15...1.9.16)
