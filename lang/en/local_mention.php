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
$string['datareviewsubject'] = 'Database review: {$a} submitted new content';
$string['datareviewcontent'] = 'User {$a->submitter} submitted pending Database content. View: {$a->url}';
$string['datareviewremindersubject'] = 'Database review reminder #{$a}';
$string['datareviewremindercontent'] = '[Reminder #{$a->seq}] User {$a->submitter} submitted Database content {$a->elapseddays} days ago. Please review: {$a->url}';