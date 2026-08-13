<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * local_recertification.php
 *
 * @package   local_kopere_recert
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['activecycleexists'] = 'This user already has a pending, processing, or active recertification cycle in this course.';
$string['activitycompletedat'] = 'Previous completion';
$string['additionalcondition'] = 'Additional condition {$a}';
$string['allcourses'] = 'All courses';
$string['automaticcyclename'] = 'Recertification {$a}';
$string['automaticcyclereason'] = 'Automatic recertification according to the policy configured in the course.';
$string['body'] = 'Message';
$string['bulkkopere_recert'] = 'Bulk recertification';
$string['bulkqueued'] = 'The selected users were queued independently.';
$string['bulkqueuedsummary'] = 'Queue processing completed. Queued: {$a->success}. Failed or skipped: {$a->failed}.';
$string['certificatereferenceunavailable'] = 'The selected certificate component is not installed or does not provide the reference date API for recertification.';
$string['cleanupbuilderhelp'] = 'The tables below are discovered from the component\'s install.xml. The activity\'s main table and tables without userid/user_id are not offered. The user condition is always required.';
$string['cleanupconfigjson'] = 'Cleanup configuration (JSON)';
$string['cleanupdata'] = 'Delete/reset data';
$string['cleanupdefinition'] = 'Cleanup table {$a}';
$string['cmid'] = 'CMID';
$string['completedat'] = 'Completed';
$string['component'] = 'Component';
$string['componentalreadyconfigured'] = 'This component already has a global task.';
$string['componentnotinstalled'] = 'Component not installed';
$string['componentrepresentedbysubplugin'] = 'This component is already represented by a recerttask subplugin.';
$string['configuration'] = 'Configuration';
$string['configurednotices'] = 'Configured notifications';
$string['copyfiles'] = 'Copy files';
$string['courseconfiguration'] = 'Recertification configuration';
$string['courseidrequired'] = 'A course must be specified to view another user\'s history.';
$string['createhistory'] = 'Create history';
$string['cycle'] = 'Cycle';
$string['disabled'] = 'Disabled';
$string['edittask'] = 'Edit task';
$string['enabled'] = 'Enabled';
$string['event_kopere_recert_completed'] = 'Recertification completed';
$string['event_kopere_recert_completed_description'] = 'Cycle {$a->cycleid} was completed by user {$a->userid} in course {$a->courseid}.';
$string['event_kopere_recert_created'] = 'Recertification cycle created';
$string['event_kopere_recert_created_description'] = 'Cycle {$a->cycleid} was created for user {$a->userid} in course {$a->courseid}.';
$string['event_kopere_recert_failed'] = 'Recertification failed';
$string['event_kopere_recert_failed_description'] = 'Cycle {$a->cycleid} failed for user {$a->userid} in course {$a->courseid}.';
$string['event_kopere_recert_started'] = 'Recertification started';
$string['event_kopere_recert_started_description'] = 'Cycle {$a->cycleid} was started for user {$a->userid} in course {$a->courseid}.';
$string['eventtype'] = 'Event';
$string['execution_skipped_cycle_status'] = 'Execution was skipped because the cycle is already in status: {$a}.';
$string['executionplan'] = 'Execution plan';
$string['filebuilderhelp'] = 'Generic file rules require File API itemid = :userid. If the component relates files to the user through another table or entity, use a specialized subplugin.';
$string['fileconfigjson'] = 'File copy configuration (JSON)';
$string['filedefinition'] = 'File source {$a}';
$string['filename'] = 'Filename';
$string['filepath'] = 'File path';
$string['filescopiedcount'] = '{$a} files copied.';
$string['filter'] = 'Filter';
$string['fixedday'] = 'Fixed day';
$string['fixedmonth'] = 'Fixed month';
$string['forumreplyhaschildren'] = 'A forum reply cannot be safely removed because it has child replies that must be preserved.';
$string['generic'] = 'Generic';
$string['history'] = 'Recertification history';
$string['historyfiles'] = 'Historical files';
$string['historyrecordswouldbecreated'] = 'history records would be created.';
$string['historytemplate'] = 'History Mustache template';
$string['installedactivities'] = 'Installed activities';
$string['intervaldays'] = 'Interval in days';
$string['intervalmustbepositive'] = 'The interval must be greater than zero days for this trigger.';
$string['invalidcleanupconfig'] = 'Invalid cleanup configuration.';
$string['invalidcycle'] = 'The selected cycle does not belong to this course.';
$string['invalidfileconfig'] = 'Invalid file configuration.';
$string['invalidjson'] = 'Invalid JSON.';
$string['invalidselfreference'] = 'Invalid reference type for manual recertification.';
$string['kopere_recertlocked'] = 'A recertification for this user and course is already being processed.';
$string['kopere_recertpendingnav'] = 'Recertification in progress';
$string['kopere_recertqueued'] = 'The recertification was queued for isolated processing.';
$string['kopere_recertrequired'] = 'Recertification required';
$string['kopere_recertstatusmessage'] = 'Your previous certification is no longer considered current. Complete the course requirements again to finish this recertification.';
$string['kopereemailrecommendation'] = 'To further customize recertification emails, you can optionally install message_kopereemail.';
$string['logicalblockmessage'] = 'Your previous certification is no longer considered valid as a current certification. Complete the course requirements again to finish this recertification.';
$string['missingcertificatereference'] = 'A certificate activity must be selected as the reference.';
$string['newtask'] = 'New task';
$string['noeligiblecleanuptables'] = 'This component does not have a table eligible for generic cleanup.';
$string['nohistory'] = 'No recertification history was found.';
$string['nosimulationreport'] = 'The simulation report is no longer available.';
$string['notconfigured'] = 'Not configured';
$string['notice_available'] = 'Recertification available';
$string['notice_completed'] = 'Recertification completed';
$string['notice_created'] = 'Recertification created';
$string['notice_due'] = 'Recertification due';
$string['notice_expired'] = 'Recertification expired';
$string['notice_started'] = 'Recertification started';
$string['notice_warning'] = 'Expiration warning';
$string['notices'] = 'Notifications';
$string['notification_body_expiration_warning'] = 'Your current certification for course {{course.fullname}} is approaching its recertification date.';
$string['notification_body_kopere_recert_available'] = 'A new recertification for course {{course.fullname}} is available.';
$string['notification_body_kopere_recert_completed'] = 'Your recertification for course {{course.fullname}} has been completed.';
$string['notification_body_kopere_recert_created'] = 'A recertification cycle was created for course {{course.fullname}}.';
$string['notification_body_kopere_recert_due'] = 'The recertification for course {{course.fullname}} has reached its due date.';
$string['notification_body_kopere_recert_expired'] = 'Your certification for course {{course.fullname}} has expired and a new recertification is required.';
$string['notification_body_kopere_recert_started'] = 'Your recertification for course {{course.fullname}} has started. Complete the required activities again.';
$string['notification_subject_expiration_warning'] = 'Certification expiration warning: {$a}';
$string['notification_subject_kopere_recert_available'] = 'Recertification available: {$a}';
$string['notification_subject_kopere_recert_completed'] = 'Recertification completed: {$a}';
$string['notification_subject_kopere_recert_created'] = 'Recertification created: {$a}';
$string['notification_subject_kopere_recert_due'] = 'Recertification due: {$a}';
$string['notification_subject_kopere_recert_expired'] = 'Certification expired: {$a}';
$string['notification_subject_kopere_recert_started'] = 'Recertification started: {$a}';
$string['offsetdays'] = 'Days before expiration';
$string['origin'] = 'Origin';
$string['pluginname'] = 'Recertification';
$string['privacy:metadata:local_kopere_recert_cycle'] = 'Stores recertification cycles.';
$string['privacy:metadata:local_kopere_recert_cycle:courseid'] = 'Course being recertified.';
$string['privacy:metadata:local_kopere_recert_cycle:createdby'] = 'User who created or requested the cycle.';
$string['privacy:metadata:local_kopere_recert_cycle:reason'] = 'Reason for recertification.';
$string['privacy:metadata:local_kopere_recert_cycle:userid'] = 'User who owns the cycle.';
$string['privacy:metadata:local_kopere_recert_file'] = 'Stores metadata for files copied to the history.';
$string['privacy:metadata:local_kopere_recert_file:userid'] = 'User who owns the copied historical file.';
$string['privacy:metadata:local_kopere_recert_history'] = 'Stores permanent historical snapshots.';
$string['privacy:metadata:local_kopere_recert_history:datajson'] = 'Structured historical data provided by the task.';
$string['privacy:metadata:local_kopere_recert_history:html'] = 'Historical data rendered by an activity or system task.';
$string['privacy:metadata:local_kopere_recert_history:userid'] = 'User who owns the historical snapshot.';
$string['privacy:metadata:local_kopere_recert_log'] = 'Stores recertification execution logs.';
$string['privacy:metadata:local_kopere_recert_log:message'] = 'Technical execution message without secrets.';
$string['privacy:metadata:local_kopere_recert_notice_log'] = 'Stores tracking information for notifications already sent.';
$string['privacy:metadata:local_kopere_recert_notice_log:userid'] = 'User who received the notification.';
$string['reason'] = 'Reason';
$string['reasonrequired'] = 'The reason for recertification is required.';
$string['recertifyselected'] = 'Recertify selected';
$string['recordsaffectedcount'] = '{$a} records affected.';
$string['referencecmid'] = 'Reference certificate activity';
$string['resetcompetencies'] = 'Reset course competencies';
$string['resetcompetencies_desc'] = 'When enabled, the competencies task may reset only this user\'s competency status in this course. Competency definitions are never deleted.';
$string['selfafterdays'] = 'Student can request after this number of days from enrolment';
$string['selfafterdaysinvalid'] = 'The number of days for manual recertification cannot be negative.';
$string['selfavailablein'] = 'New recertification available in {$a} days.';
$string['selfenabled'] = 'Allow manual recertification by the student';
$string['selfkopere_recertnotavailable'] = 'A new recertification will be available in {$a}.';
$string['selfnotavailable'] = 'A new recertification is not yet available.';
$string['selfreference_certificate'] = 'From the issue date of the selected certificate';
$string['selfreference_completion'] = 'From the current course completion';
$string['selfreference_enrolment'] = 'From the enrolment date';
$string['selfreference_lastkopere_recert'] = 'From the last completed recertification cycle';
$string['selfreferencetype'] = 'Manual recertification reference';
$string['settings'] = 'Settings';
$string['showkopereemailrecommendation'] = 'Show Kopere Email recommendation';
$string['showkopereemailrecommendation_desc'] = 'When message_kopereemail is not installed, shows a discreet recommendation in the plugin settings.';
$string['simulate'] = 'Simulate recertification';
$string['simulation'] = 'Recertification simulation';
$string['simulationcompleted'] = 'SIMULATION COMPLETED';
$string['simulationdetails'] = 'Details before changes';
$string['simulationrollback'] = 'Controlled simulation rollback.';
$string['simulationrollbackdone'] = 'Rollback completed. No simulation information was saved.';
$string['sourcecomponent'] = 'Source component';
$string['sourcecontextid'] = 'Source context ID';
$string['sourcefilearea'] = 'Source file area';
$string['sourceitemid'] = 'Source item ID';
$string['sqlechomultiplecolumns'] = 'sqlecho must return exactly one column.';
$string['sqlechomultiplerows'] = 'sqlecho must return zero or one row.';
$string['sqltemplatehelp'] = 'Mustache can use {{#sqlecho}}...{{/sqlecho}} and {{#sqltable}}...{{/sqltable}}. SQL is read-only and values must use the parameters :userid, :courseid, :cmid, :instanceid, :contextid, :cycleid, or :kopere_recertid.';
$string['startedat'] = 'Started';
$string['startkopere_recert'] = 'Start recertification';
$string['structureddata'] = 'Structured snapshot data';
$string['subject'] = 'Subject';
$string['subplugin'] = 'Subplugin';
$string['subpluginmissing'] = 'The configured subplugin is no longer installed';
$string['subplugintype_recerttask'] = 'Recertification task';
$string['subplugintype_recerttask_plural'] = '';
$string['supportedplugins'] = 'Supported plugins';
$string['systemcomponents'] = 'System components';
$string['table'] = 'Table';
$string['task_scan'] = 'Check recertifications and queue expired users';
$string['tasks'] = 'Tasks';
$string['trigger_certificate'] = 'After certificate issuance';
$string['trigger_completion'] = 'After course completion';
$string['trigger_enrolment'] = 'After enrolment';
$string['trigger_fixeddate'] = 'Fixed annual date';
$string['trigger_lastkopere_recert'] = 'After the last recertification';
$string['triggertype'] = 'Recertification trigger';
$string['type'] = 'Type';
$string['usercolumn'] = 'User column';
$string['usernotenrolled'] = 'The selected user does not have an active enrolment in this course.';
$string['viewhistory'] = 'View recertification history';
