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

namespace local_mention\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Parses the "hide page elements" rules and computes which CSS selectors
 * should be hidden for the current user.
 *
 * Rule format (one per line):
 *   <roles> | <css selector> | <note>
 *
 * - <roles> is a comma separated (Chinese or English) list of role local
 *   names (the "Custom full name" configured when editing a role). The
 *   keyword ALL (case-insensitive) or an empty value hides the element for
 *   every user.
 * - When roles are given, a user matches if they are assigned to any of the
 *   listed roles in ANY context (ANYCONTEXT semantics, mirroring Boost Union
 *   smart menus).
 * - The trailing <note> is only for administration readability and takes no
 *   part in the hiding decision.
 *
 * @package    local_mention
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class element_hider {

    /** Configuration key holding the rule textarea content. */
    const CONFIG_KEY = 'element_hider_rules';

    /** In-process cache of the parsed rules for this user. */
    private static $rules = null;

    /** In-process cache of the role display-name to id map. */
    private static $rolelookup = null;

    /**
     * Return the CSS selectors that should be hidden for the current user.
     *
     * @return string[] List of CSS selectors to hide, already trimmed.
     */
    public static function get_matching_selectors(): array {
        $rules = self::get_rules();
        if (empty($rules)) {
            return [];
        }

        $selectors = [];
        foreach ($rules as $rule) {
            $matches = self::rule_matches($rule);
            if ($matches) {
                $selectors[] = $rule['selector'];
            }
        }

        return array_values(array_unique($selectors));
    }

    /**
     * Build a CSS snippet that hides every selector matching the current user.
     *
     * Injected into <head> so the elements never flash on first render.
     *
     * @return string CSS declarations to apply (empty string when nothing matches).
     */
    public static function get_matching_css(): string {
        $selectors = self::get_matching_selectors();
        if (empty($selectors)) {
            return '';
        }

        $safe = [];
        foreach ($selectors as $selector) {
            $clean = trim($selector);
            // Keep only characters that are valid in a CSS selector, dropping
            // quotes, angle brackets and anything that could break the rule.
            // (Relaxed enough for classes, ids, attributes, combinators.)
            if (!preg_match('/^[A-Za-z0-9_#.\[\]="\'~^$*|:(),\s\->]+$/', $clean)) {
                continue;
            }
            if (strpos($clean, '</style') !== false) {
                continue;
            }
            $safe[] = $clean;
        }
        if (empty($safe)) {
            return '';
        }

        return implode(',', $safe) . '{ display: none !important; }';
    }

    /**
     * Parse and cache the configured rules for the lifetime of the request.
     *
     * @return array[] List of normalised rules, each with keys: roles, selector, notes.
     */
    public static function get_rules(): array {
        if (self::$rules === null) {
            $raw = (string) get_config('local_mention', self::CONFIG_KEY);
            self::$rules = self::parse($raw);
        }
        return self::$rules;
    }

    /**
     * Map of user supplied role name (lowercased) to role id lookup.
     *
     * The display name comes from role_get_name(), which yields the effective
     * "Custom full name" shown on the role editing page (and falls back to the
     * translated default full name when no custom full name is configured).
     * Note the role table has no "localname" column - role_get_name() derives
     * the display name at runtime from the name/shortname fields. The shortname
     * is also mapped as an extra fall-back so rules keep working even when no
     * custom full name has ever been set.
     *
     * @return array<string,int> normalised display name (lowercased) => role id.
     */
    private static function get_all_roles_by_localname(): array {
        global $DB;
        if (self::$rolelookup !== null) {
            return self::$rolelookup;
        }

        // role_get_name() only needs ->name and ->shortname when invoked without
        // a course context, so a lean row is sufficient.
        $roles = $DB->get_records('role', null, '', 'id,shortname,name');
        $map = [];
        foreach ($roles as $role) {
            $displayname = trim((string) role_get_name($role));
            $shortname = trim((string) $role->shortname);

            $normalised = self::normalise($displayname);
            if ($normalised !== '') {
                $map[$normalised] = (int) $role->id;
            }
            if ($shortname !== '' && self::normalise($shortname) !== $normalised) {
                $map[self::normalise($shortname)] = (int) $role->id;
            }
        }

        self::$rolelookup = $map;
        return $map;
    }

    /**
     * Determine whether a single parsed rule applies to the current user.
     *
     * @param array $rule A parsed rule: keys 'roles' (list), 'selector'.
     * @return bool
     */
    private static function rule_matches(array $rule): bool {
        global $USER;

        if (empty($rule['roles'])) {
            // No roles restriction -> apply to everybody.
            return true;
        }

        if (empty($rule['selector'])) {
            return false;
        }

        if (!isloggedin() || isguestuser() || empty($USER->id)) {
            return false;
        }

        return self::user_has_any_role((int) $USER->id, $rule['roles']);
    }

    /**
     * Returns true if the user holds any of the given role local names in ANY
     * context. This intentionally uses role assignments directly (not the
     * capability inheritance chain) to match Boost Union's ANYCONTEXT logic.
     *
     * @param int $userid
     * @param string[] $rolenames Role local names to test (as supplied).
     * @return bool
     */
    private static function user_has_any_role(int $userid, array $rolenames): bool {
        global $DB;

        $bynamelookup = self::get_all_roles_by_localname();

        $roleids = [];
        foreach ($rolenames as $name) {
            $normalised = self::normalise($name);
            if ($normalised !== '' && isset($bynamelookup[$normalised])) {
                $roleids[$bynamelookup[$normalised]] = true;
            }
        }
        $roleids = array_keys($roleids);
        if (empty($roleids)) {
            return false;
        }

        [$insql, $inparam] = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED, 'rl');
        $params = array_merge(['uid' => $userid], $inparam);
        return $DB->count_records_sql(
            "SELECT COUNT(1) FROM {role_assignments}
              WHERE userid = :uid AND roleid $insql",
            $params
        ) > 0;
    }

    /**
     * Parse the raw configuration text into normalised rules.
     *
     * @param string $raw
     * @return array[]
     */
    private static function parse(string $raw): array {
        if (trim($raw) === '') {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/', $raw);
        $rules = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $rule = self::parse_line($line);
            if ($rule !== null && $rule['selector'] !== '') {
                $rules[] = $rule;
            }
        }
        return $rules;
    }

    /**
     * Parse a single rule line.
     *
     * @param string $line
     * @return array|null Normalised rule keys: roles (array), selector, notes.
     */
    private static function parse_line(string $line): ?array {
        // Split on the pipe separator into at most three parts.
        $parts = explode('|', $line);
        $rolespart = isset($parts[0]) ? trim($parts[0]) : '';
        $selector  = isset($parts[1]) ? trim($parts[1]) : '';
        $notes     = isset($parts[2]) ? trim($parts[2]) : '';

        // "ALL" (case-insensitive) or an empty roles part means every user.
        if (strcasecmp($rolespart, 'ALL') === 0 || $rolespart === '') {
            $roles = [];
        } else {
            // Split by both Chinese and English commas.
            $rawroles = preg_split('/[,，]/', $rolespart);
            $roles = [];
            foreach ($rawroles as $name) {
                $name = trim($name);
                if ($name !== '') {
                    $roles[] = $name;
                }
            }
        }

        return [
            'roles' => $roles,
            'selector' => $selector,
            'notes' => $notes,
        ];
    }

    /**
     * Normalise a role name for lookup: collapse whitespace and lowercase.
     *
     * @param string $value
     * @return string
     */
    private static function normalise(string $value): string {
        return mb_strtolower(trim((string) $value), 'UTF-8');
    }
}