<?php
// This file is part of Moodle - http://moodle.org/

$string['pluginname'] = 'Share service';
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
$string['reminder_interval'] = 'Review reminder interval';
$string['reminder_interval_desc'] = 'The interval between periodic review reminders. Default: 7 days.';
$string['max_notifications'] = 'Maximum review notifications';
$string['max_notifications_desc'] = 'The maximum number of notifications (including the initial notification) sent for a single pending entry. Default: 4.';
$string['mention:manage'] = 'Manage Mention service settings';

// TinyMCE image compression settings
$string['enableimagecompress'] = 'Enable image compression';
$string['enableimagecompress_desc'] = 'Enable automatic front-end image compression when uploading, dragging, or pasting images in the TinyMCE editor.';
$string['imagemaxwidth'] = 'Maximum image width';
$string['imagemaxwidth_desc'] = 'Images wider than this value will be proportionally scaled down. Default: 1080 pixels.';
$string['imagequality'] = 'Image compression quality';
$string['imagequality_desc'] = 'JPEG/WebP compression quality (0.0 to 1.0). Higher values produce better quality but larger files. PNG is not affected by this setting. Default: 0.82.';
$string['cdn_domains'] = 'CDN domains (whitelist)';
$string['cdn_domains_desc'] = 'Images hosted on these domains will be kept as remote references and will NOT be localized/downloaded. Enter one domain per line, e.g. cdn.example.com. Leave empty to localize all external images.';
$string['enabletagfilter'] = 'Enable Database tag filtering';
$string['enabletagfilter_desc'] = 'Filter tag options on Database activity add/edit entry pages based on the mod/data:approve capability (show/hide tags containing "draft").';
$string['enableratinglabel'] = 'Enable rating label replacement';
$string['enableratinglabel_desc'] = 'Replace the rating aggregate label on Database record view pages with the configured text.';
$string['ratingchineselabel'] = 'Chinese rating label';
$string['ratingchineselabel_desc'] = 'The replacement text used for the rating label in Chinese-language environments. Default: 我来评分：';
$string['ratingenglishlabel'] = 'English rating label';
$string['ratingenglishlabel_desc'] = 'The replacement text used for the rating label in non-Chinese language environments. Default: Rating:';
$string['enabledecorative'] = 'Enable "decorative" image default';
$string['enabledecorative_desc'] = 'Automatically check the "This image is decorative only" checkbox in the TinyMCE image dialog so users do not need to enter alt text.';
$string['enablehiderating'] = 'Enable hiding course rating widget';
$string['enablehiderating_desc'] = 'Hide the tool_courserating widget on courses that contain exactly one Database activity.';