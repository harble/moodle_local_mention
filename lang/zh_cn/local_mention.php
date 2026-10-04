<?php
// This file is part of Moodle - http://moodle.org/

$string['pluginname'] = '分享服务';
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

// TinyMCE 图片压缩设置
$string['enableimagecompress'] = '启用图片压缩';
$string['enableimagecompress_desc'] = '在 TinyMCE 编辑器中上传、拖拽或粘贴图片时，自动在前端压缩图片，减少上传文件大小。';
$string['imagemaxwidth'] = '图片最大宽度';
$string['imagemaxwidth_desc'] = '超过此宽度的图片将被按比例缩小。默认值：1080 像素。';
$string['imagequality'] = '图片压缩质量';
$string['imagequality_desc'] = 'JPEG/WebP 压缩质量（0.0 到 1.0）。值越高质量越好但文件越大。PNG 不受此设置影响。默认值：0.82。';
$string['cdn_domains'] = 'CDN 域名（白名单）';
$string['cdn_domains_desc'] = '位于这些域名下的图片将保留远程引用，不进行本地化下载。每行填写一个域名，例如 cdn.example.com。留空表示所有外部图片都进行本地化。';
$string['enabletagfilter'] = '启用 Database 标签过滤';
$string['enabletagfilter_desc'] = '在 Database 活动新增/编辑条目页面，根据是否拥有 mod/data:approve 权限过滤标签候选项（显示/隐藏包含 "draft" 的标签）。';
$string['enableratinglabel'] = '启用评分标签文本替换';
$string['enableratinglabel_desc'] = '在 Database 记录查看页面，将评分汇总标签替换为配置的文字。';
$string['ratingchineselabel'] = '中文评分标签文字';
$string['ratingchineselabel_desc'] = '中文环境下使用的评分标签替换文字。默认值：我来评分：';
$string['ratingenglishlabel'] = '英文评分标签文字';
$string['ratingenglishlabel_desc'] = '非中文环境下使用的评分标签替换文字。默认值：Rating:';
$string['enabledecorative'] = '启用"仅用于装饰"图片默认勾选';
$string['enabledecorative_desc'] = '在 TinyMCE 图片对话框中自动勾选"此图像仅用于装饰"复选框，使插入图片时无需填写替代文本（Alt text）。';
$string['enablehiderating'] = '启用隐藏课程评分组件';
$string['enablehiderating_desc'] = '在恰好包含一个 Database 活动的课程上隐藏 tool_courserating 评分组件。';
$string['enablesectiondisplay'] = '启用 section 显示控制';
$string['enablesectiondisplay_desc'] = '控制课程中第一个名称为配置值的 section 的显示方式，可折叠显示（按用户记住）或完全隐藏。';
$string['sectiondisplayname'] = 'section 名称';
$string['sectiondisplayname_desc'] = '要控制的课程中第一个 section 的名称。默认值：愿心加油站';
$string['sectiondisplaymode'] = 'section 显示模式';
$string['sectiondisplaymode_desc'] = '在课程页面如何显示匹配的 section。';
$string['sectiondisplaymode_fold'] = '折叠显示（记住）';
$string['sectiondisplaymode_hide'] = '不显示';

// 浏览计数设置
$string['enablerecordview'] = '启用 Database 条目浏览计数';
$string['enablerecordview_desc'] = '在原生 Database 单条查看页面上记录聚合浏览计数。';
$string['viewdedupwindow'] = '浏览去重窗口';
$string['viewdedupwindow_desc'] = '同一用户在此时间窗口内重复查看同一条目只计数一次。默认：5 分钟。';
$string['enablerecordviewdisplay'] = '显示条目浏览计数';
$string['enablerecordviewdisplay_desc'] = '在原生 Database 单条查看页面上显示聚合浏览计数。';
$string['viewcount'] = '{$a} 次浏览';
$string['entryviewcounttarget'] = '浏览计数插入位置（CSS 选择器）';
$string['entryviewcounttarget_desc'] = '指定浏览计数徽章插入到哪个元素之后（例如，包含"最后编辑: ##timemodified##"的那一行）。留空则插入到记录容器顶部。';
$string['entryviewdateformat'] = '条目视图时间格式';
$string['entryviewdateformat_desc'] = '原生 Database 单条查看页面上 ##timeadded## / ##timemodified## 时间标签使用的 PHP strftime 格式。留空则保持默认。示例：%Y/%m/%d %H:%M 会显示为 2026/09/30 14:30。';