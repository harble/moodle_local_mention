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

## Notes

- This phase is plugin-internal only: no external module code is modified.
- Adapter/event integration for specific activity modules is the next phase.
