<?php
// This file is part of Moodle - http://moodle.org/

namespace local_mention\local\service;

defined('MOODLE_INTERNAL') || die();

class parser {
    public static function extract_userids(string $content): array {
        if (trim($content) === '') {
            return [];
        }

        $matches = [];
        preg_match_all('/data-mention-userid\s*=\s*["\'](\d+)["\']/u', $content, $matches);
        if (empty($matches[1])) {
            return [];
        }

        $userids = [];
        foreach ($matches[1] as $userid) {
            $userid = (int)$userid;
            if ($userid > 0) {
                $userids[$userid] = $userid;
            }
        }

        return array_values($userids);
    }

    public static function extract_usernames(string $content): array {
        if (trim($content) === '') {
            return [];
        }

        $matches = [];
        preg_match_all('/(^|[\s\(\[\{,;:>])@([A-Za-z0-9._-]{2,100})/u', $content, $matches);
        if (empty($matches[2])) {
            return [];
        }

        $usernames = [];
        foreach ($matches[2] as $username) {
            $username = trim((string)$username);
            if ($username === '') {
                continue;
            }
            $key = \core_text::strtolower($username);
            if (!array_key_exists($key, $usernames)) {
                $usernames[$key] = $username;
            }
        }

        return array_values($usernames);
    }
}
