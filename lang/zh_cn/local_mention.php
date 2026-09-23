<?php
// This file is part of Moodle - http://moodle.org/

$string['pluginname'] = '提及服务';
$string['privacy:metadata'] = 'local_mention 插件会存储提及通知。';
$string['messageprovider:mentions'] = '提及通知';
$string['mentionnotificationsubject'] = '{$a->author} 提及了你：{$a->item}';
$string['mentionnotificationdefaultitem'] = '一条帖子';
$string['mentionnotificationfullmessage'] = '{$a->author} 在 {$a->item} 中提及了你。打开：{$a->url}';
$string['mentionnotificationfullmessagehtml'] = '{$a->author} 在 {$a->link} 中提及了你。';
$string['mentionnotificationsmall'] = '{$a} 提及了你';
$string['unresolvedmentionsconfirmtitle'] = '检测到以下@提及无法识别为有效提及';
$string['unresolvedmentionsconfirmbody'] = '{$a}\n\n选择“确定”：返回编辑器，重新从下拉列表选择提及。\n选择“取消”：忽略这些@，继续提交。';
$string['unresolvedmentionsconfirmmoresuffix'] = ' 等另外 {$a} 个';
$string['datareviewsubject'] = '{$a->dataname}待审核：{$a->submitter} 提交了新内容';
$string['datareviewcontent'] = '用户 {$a->submitter} 在{$a->dataname}中提交了待审核的内容，请查看：{$a->url}';
$string['datareviewremindersubject'] = '{$a->dataname}待审核第{$a->seq}次提醒';
$string['datareviewremindercontent'] = '【第{$a->seq}次提醒】用户 {$a->submitter} 在{$a->dataname}中提交的内容已待审核 {$a->elapseddays} 天，请尽快处理：{$a->url}';
$string['sensitive_keywords'] = '敏感词关键字';
$string['sensitive_keywords_desc'] = '每行输入一个敏感词，不区分大小写。当 Database 活动条目内容包含设置的敏感词时，审核通知中会自动附加敏感词警告提示。';
$string['sensitive_warning'] = "\n\n" . '⚠️ 请留意！条目中包含敏感词：{$a}';
$string['auto_approve_databases'] = '自动内容审批';
$string['auto_approve_databases_desc'] = '选择需要自动内容审批的 Database 活动（可多选）。当选中活动的条目内容不包含敏感词时，系统会自动完成审核，无需人工介入。';
$string['reminder_interval'] = '审核提醒间隔';
$string['reminder_interval_desc'] = '周期性审核提醒的间隔时间。默认：7 天。';
$string['max_notifications'] = '最大审核提醒次数';
$string['max_notifications_desc'] = '单个待审核条目最多发送的通知次数（含初始通知）。默认：4 次。';
$string['mention:manage'] = '管理提及服务设置';