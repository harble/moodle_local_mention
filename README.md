# local_mention

MVP mention infrastructure plugin for Moodle local scope.

## What is included

- Mention storage table: `local_mention`
- Mention message provider: `local_mention/mentions`
- Internal callback entrypoint: `local_mention_content_saved(array $payload)`
- Mention parser and sync services
- User search webservice: `local_mention_search_users` (AJAX enabled, max 10)
- Basic textarea autocomplete AMD module: `local_mention/mention_autocomplete`

## Expected payload for callback

```php
$payload = [
    'component' => 'mod_hsuforum',
    'itemtype' => 'post',
    'itemid' => 123,
    'contextid' => $contextid,
    'courseid' => $courseid,
    'authorid' => $authorid,
    'content' => $messagehtml,
    'format' => FORMAT_HTML,
    'subject' => $subject,
    'url' => $url,
];

component_callback('local_mention', 'content_saved', [$payload]);
```

## AMD usage (textarea MVP)

```javascript
require(['local_mention/mention_autocomplete'], function(MentionAutocomplete) {
    MentionAutocomplete.init({
        selector: 'textarea[name="message"]',
        contextid: 123,
        courseid: 45
    });
});
```

## AMD build

From Moodle root, build this plugin AMD module:

```bash
npx grunt amd --root=local/mention
```

If `npx` is unavailable, run Moodle's local Grunt binary:

```bash
node node_modules/grunt/bin/grunt amd --root=local/mention
```

After successful build, the compiled file is generated under:

- `local/mention/amd/build/mention_autocomplete.min.js`

## Install steps (directly executable)

### 1) Copy plugin into local directory

Plugin path should be:

- `moodle/local/mention`

### 2) Run Moodle upgrade (CLI)

From Moodle root:

```bash
php admin/cli/upgrade.php --non-interactive
```

### 3) Purge caches (CLI)

```bash
php admin/cli/purge_caches.php
```

### 4) Build AMD for this plugin

```bash
npx grunt amd --root=local/mention
```

## Validation steps (directly executable)

### A) Check plugin registration

- Visit Site administration > Notifications and ensure no upgrade errors.
- In Site administration > Plugins > Message outputs, verify provider `local_mention/mentions` exists.

### B) Verify AJAX search API

- Open any page with a textarea you wire via AMD init.
- Type `@` followed by at least one character.
- Confirm dropdown returns up to 10 enrolled users in context.

### C) Verify mention persistence and notify flow

1. Trigger callback from integration side:

```php
component_callback('local_mention', 'content_saved', [[
    'component' => 'mod_hsuforum',
    'itemtype' => 'post',
    'itemid' => 123,
    'contextid' => 456,
    'courseid' => 45,
    'authorid' => 7,
    'content' => 'Hello @student1',
    'format' => FORMAT_HTML,
    'subject' => 'Test mention',
    'url' => new moodle_url('/mod/hsuforum/discuss.php', ['d' => 10]),
]]);
```

2. Confirm records are created in `local_mention` and status changes from `new` to `sent`/`failed`.
3. Confirm mentioned user receives the `mentions` message via configured message output.

## Windows note

If command `php` is not recognized, use the full PHP path, e.g.:

```powershell
"C:\\path\\to\\php.exe" admin/cli/upgrade.php --non-interactive
"C:\\path\\to\\php.exe" admin/cli/purge_caches.php
```

## Notes

- This phase is plugin-internal only: no external module code is modified.
- Adapter/event integration for specific activity modules is the next phase.
