<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_fredkichat\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\provider as metadata_provider;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\core_userlist_provider;
use core_privacy\local\request\plugin\provider as plugin_provider;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for local_fredkichat.
 *
 * Chat messages are not stored in Moodle. They are sent to the configured FredKI instance.
 * The only personal data stored in Moodle is the user who created a chatbot design.
 *
 * @package    local_fredkichat
 * @copyright  2026 lernsteine.com
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements core_userlist_provider, metadata_provider, plugin_provider {
    /**
     * Describe the stored and transmitted data.
     *
     * @param collection $collection Collection to add to.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_fredkichat_bots', [
            'owneruserid' => 'privacy:metadata:bots:owneruserid',
            'name' => 'privacy:metadata:bots:name',
            'timecreated' => 'privacy:metadata:bots:timecreated',
            'timemodified' => 'privacy:metadata:bots:timemodified',
        ], 'privacy:metadata:bots');
        $collection->add_external_location_link('fredki', [
            'message' => 'privacy:metadata:fredki:message',
        ], 'privacy:metadata:fredki');
        return $collection;
    }

    /**
     * Contexts that hold data of a user.
     *
     * @param int $userid User id.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();
        if ($DB->record_exists('local_fredkichat_bots', ['owneruserid' => $userid])) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    /**
     * Users that hold data in a context.
     *
     * @param userlist $userlist User list to fill.
     */
    public static function get_users_in_context(userlist $userlist): void {
        if ($userlist->get_context()->contextlevel != CONTEXT_SYSTEM) {
            return;
        }
        $userlist->add_from_sql('owneruserid', 'SELECT owneruserid FROM {local_fredkichat_bots} WHERE owneruserid > 0', []);
    }

    /**
     * Export the chatbot designs created by a user.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = (int)$contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_SYSTEM) {
                continue;
            }
            $bots = $DB->get_records('local_fredkichat_bots', ['owneruserid' => $userid], 'id');
            $data = [];
            foreach ($bots as $bot) {
                $data[] = (object)[
                    'name' => format_string($bot->name),
                    'agentid' => $bot->agentid,
                    'timecreated' => transform::datetime($bot->timecreated),
                    'timemodified' => transform::datetime($bot->timemodified),
                ];
            }
            if ($data) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_fredkichat')],
                    (object)['chatbots' => $data]
                );
            }
        }
    }

    /**
     * Remove the creator reference of all chatbots. The designs themselves are site configuration.
     *
     * @param \context $context Context.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if ($context->contextlevel == CONTEXT_SYSTEM) {
            $DB->set_field('local_fredkichat_bots', 'owneruserid', 0);
        }
    }

    /**
     * Remove the creator reference for one user.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel == CONTEXT_SYSTEM) {
                $DB->set_field(
                    'local_fredkichat_bots',
                    'owneruserid',
                    0,
                    ['owneruserid' => (int)$contextlist->get_user()->id]
                );
            }
        }
    }

    /**
     * Remove the creator reference for several users.
     *
     * @param approved_userlist $userlist Approved users.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        if ($userlist->get_context()->contextlevel != CONTEXT_SYSTEM || !$userlist->get_userids()) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $DB->set_field_select('local_fredkichat_bots', 'owneruserid', 0, "owneruserid $insql", $params);
    }
}
