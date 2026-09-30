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

namespace local_mention\external;

use context_system;
use external_api;
use external_function_parameters;
use external_value;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * Web service to log a database record view (aggregated view counting).
 *
 * One row is kept per (recordid, userid) pair. viewcount is incremented only
 * when the current view falls outside the configured deduplication window,
 * which prevents rapid page refreshes from inflating the counter.
 *
 * @package    local_mention
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class log_view extends external_api {

    /**
     * Returns the function parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'recordid' => new external_value(PARAM_INT, 'Database record (data_records) id to register a view for'),
        ]);
    }

    /**
     * Records a view against an aggregated counter for the current user.
     *
     * @param int $recordid The data_records id of the viewed entry.
     * @return bool true if recorded (or already recorded within the dedup window), false otherwise.
     */
    public static function execute(int $recordid): bool {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), ['recordid' => $recordid]);

        $recordid = (int)$params['recordid'];
        if ($recordid <= 0) {
            return false;
        }

        self::validate_context(context_system::instance());
        require_login();

        // Respect the admin setting.
        $enable = get_config('local_mention', 'enablerecordview');
        if ($enable === false || (bool)$enable === false) {
            return false;
        }

        // Only count views from logged-in, non-guest users. userid 0 is reserved
        // for migrated/system seed counters and must never be overwritten here.
        if (!isloggedin() || isguestuser()) {
            return false;
        }
        $userid = (int)$USER->id;
        if ($userid <= 0) {
            return false;
        }

        $now = time();
        $dedupwindow = (int)get_config('local_mention', 'viewdedupwindow');
        if ($dedupwindow <= 0) {
            $dedupwindow = 300;
        }

        $record = $DB->get_record('local_mention_entry_views',
            ['recordid' => $recordid, 'userid' => $userid]);

        if ($record) {
            // Still inside the deduplication window: no-op.
            if ($now - (int)$record->lastviewed < $dedupwindow) {
                return true;
            }
            $DB->update_record('local_mention_entry_views', [
                'id' => (int)$record->id,
                'viewcount' => (int)$record->viewcount + 1,
                'lastviewed' => $now,
            ]);
            return true;
        }

        $DB->insert_record('local_mention_entry_views', [
            'recordid' => $recordid,
            'userid' => $userid,
            'viewcount' => 1,
            'seedcount' => 0,
            'firstviewed' => $now,
            'lastviewed' => $now,
        ]);
        return true;
    }

    /**
     * Returns the function result.
     *
     * @return external_value
     */
    public static function execute_returns(): external_value {
        return new external_value(PARAM_BOOL, 'Whether the view was recorded');
    }
}