<?php
// This file is part of Moodle - http://moodle.org/

$string['pluginname'] = 'Mention service';
$string['privacy:metadata'] = 'The local_mention plugin stores mention notifications.';
$string['messageprovider:mentions'] = 'Mention notifications';
$string['mentionnotificationsubject'] = '{$a->author} mentioned you: {$a->item}';
$string['mentionnotificationdefaultitem'] = 'a post';
$string['mentionnotificationfullmessage'] = '{$a->author} mentioned you in {$a->item}. Open: {$a->url}';
$string['mentionnotificationfullmessagehtml'] = '{$a->author} mentioned you in {$a->link}.';
$string['mentionnotificationsmall'] = '{$a} mentioned you';
$string['unresolvedmentionsconfirmtitle'] = 'Some @mentions cannot be recognized as valid mentions';
$string['unresolvedmentionsconfirmbody'] = '{$a}\n\nChoose OK to return and re-select these mentions from the suggestion list.\nChoose Cancel to ignore them and continue submitting.';
$string['unresolvedmentionsconfirmmoresuffix'] = ' and {$a} more';
$string['datareviewsubject'] = '{$a->dataname} review: {$a->submitter} submitted new content';
$string['datareviewcontent'] = 'User {$a->submitter} submitted pending content in "{$a->dataname}". View: {$a->url}';
$string['datareviewremindersubject'] = '{$a->dataname} review reminder #{$a->seq}';
$string['datareviewremindercontent'] = '[Reminder #{$a->seq}] User {$a->submitter} submitted content in "{$a->dataname}" {$a->elapseddays} days ago. Please review: {$a->url}';
$string['sensitive_keywords'] = 'Sensitive keywords';
$string['sensitive_keywords_desc'] = 'Enter one keyword per line (case-insensitive). When Database entry content contains these keywords, a warning will be appended to the review notification.';
$string['sensitive_warning'] = "\n\n" . '⚠️ Caution! Entry contains sensitive word(s): {$a}';
$string['auto_approve_databases'] = 'Auto content approval';
$string['auto_approve_databases_desc'] = 'Select Database activities that should be auto-approved (multiple selection allowed). When the entry content of a selected activity contains no sensitive words, the entry will be automatically approved without human intervention.';