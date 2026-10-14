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

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_mention\observer\database_observer;

defined('MOODLE_INTERNAL') || die();

/**
 * Web service that returns, for a given set of Database records, the subset of
 * records the current user is allowed to approve.
 *
 * This lets the client-side approval-menu filter hide the approve/disapprove
 * menu items for entries the current user is not the assigned reviewer of,
 * without modifying the core mod_data templates.
 *
 * @package    local_mention
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class approval_permissions extends external_api {

    /**
     * Returns the parameters of the external function.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'recordids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Database record id'),
                'List of data_records ids to check'
            ),
        ]);
    }

    /**
     * Returns the structure of the external function result.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'permitted' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Record id the current user may approve')
            ),
        ]);
    }

    /**
     * Determine which of the given records the current user may approve.
     *
     * @param array $recordids List of data_records ids.
     * @return array
     */
    public static function execute(array $recordids = []): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), ['recordids' => $recordids]);
        $recordids = array_values(array_unique(array_filter(array_map('intval', $params['recordids']))));
        if (empty($recordids)) {
            return ['permitted' => []];
        }

        $permitted = [];

        // Fetch all records in one query.
        $records = [];
        $recordsobj = $DB->get_records_sql(
            "SELECT * FROM {data_records} WHERE id IN (" . implode(',', array_fill(0, count($recordids), '?')) . ")",
            $recordids
        );
        foreach ($recordsobj as $record) {
            $records[(int)$record->id] = $record;
        }

        // Group records by their data (activity) to fetch each course module once.
        $datacache = [];
        foreach ($records as $record) {
            if (!isset($datacache[$record->dataid])) {
                $data = $DB->get_record('data', ['id' => (int)$record->dataid]);
                $datacache[$record->dataid] = $data;
            }
        }

        foreach ($records as $record) {
            $data = $datacache[$record->dataid] ?? null;
            if (!$data) {
                continue;
            }
            // Get the course module for this data instance.
            $cm = get_coursemodule_from_instance('data', (int)$data->id, 0, false, MUST_EXIST);
            // Validate login & confirm the module context is accessible.
            $context = \context_module::instance($cm->id);
            self::validate_context($context);
            // A user must hold the approve capability in the module before we
            // apply the reviewer restriction. Users without the capability are
            // simply not permitted (mirrors the core template's gate).
            if (!has_capability('mod/data:approve', $context)) {
                continue;
            }

            if (database_observer::is_reviewer_for_record($cm, $record)) {
                $permitted[] = (int)$record->id;
            }
        }

        return ['permitted' => $permitted];
    }
}