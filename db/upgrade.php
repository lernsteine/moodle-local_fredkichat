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

/**
 * Upgrade steps of local_fredkichat.
 *
 * @package    local_fredkichat
 * @copyright  2026 lernsteine.com
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the plugin.
 *
 * @param int $oldversion Version before the upgrade.
 * @return bool
 */
function xmldb_local_fredkichat_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026091504) {
        $table = new xmldb_table('local_fredkichat_bots');
        $field = new xmldb_field('agentid', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'courseid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $tt = new xmldb_table('local_fredkichat_template');
        if (!$dbman->table_exists($tt)) {
            $tt->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $tt->add_field('headercolor', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, '#A59480');
            $tt->add_field('accentcolor', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, '#9B8975');
            $tt->add_field('backgroundcolor', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, '#FCFBF9');
            $tt->add_field('textcolor', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, '#20222A');
            $tt->add_field('footertext', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $tt->add_field('imprinturl', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $tt->add_field('privacyurl', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $tt->add_field('feedbackurl', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $tt->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $tt->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $dbman->create_table($tt);
        }
        upgrade_plugin_savepoint(true, 2026091504, 'local', 'fredkichat');
    }

    if ($oldversion < 2026091505) {
        $table = new xmldb_table('local_fredkichat_bots');
        $owner = new xmldb_field('owneruserid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'agentid');
        if (!$dbman->field_exists($table, $owner)) {
            $dbman->add_field($table, $owner);
        }
        $visibility = new xmldb_field('visibility', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'private', 'owneruserid');
        if (!$dbman->field_exists($table, $visibility)) {
            $dbman->add_field($table, $visibility);
        }

        // Migrate the former global design to existing bots so their appearance is preserved.
        $template = $DB->get_record('local_fredkichat_template', ['id' => 1]);
        if ($template) {
            $bots = $DB->get_records('local_fredkichat_bots');
            foreach ($bots as $bot) {
                $bot->courseid = 0;
                $bot->headercolor = $template->headercolor;
                $bot->accentcolor = $template->accentcolor;
                $bot->backgroundcolor = $template->backgroundcolor;
                $bot->textcolor = $template->textcolor;
                $bot->footertext = $template->footertext;
                $bot->imprinturl = $template->imprinturl;
                $bot->privacyurl = $template->privacyurl;
                $bot->feedbackurl = $template->feedbackurl;
                $DB->update_record('local_fredkichat_bots', $bot);
            }

            // Copy the former template logo to each existing bot once.
            $context = context_system::instance();
            $fs = get_file_storage();
            $templatefiles = $fs->get_area_files($context->id, 'local_fredkichat', 'template_logo', 1, 'id', false);
            if ($templatefiles) {
                $source = reset($templatefiles);
                foreach ($bots as $bot) {
                    if (!$fs->get_area_files($context->id, 'local_fredkichat', 'logo', $bot->id, 'id', false)) {
                        $filerecord = [
                            'contextid' => $context->id,
                            'component' => 'local_fredkichat',
                            'filearea' => 'logo',
                            'itemid' => $bot->id,
                            'filepath' => $source->get_filepath(),
                            'filename' => $source->get_filename(),
                        ];
                        $fs->create_file_from_storedfile($filerecord, $source);
                    }
                }
            }
        } else {
            $DB->set_field('local_fredkichat_bots', 'courseid', 0);
        }

        upgrade_plugin_savepoint(true, 2026091505, 'local', 'fredkichat');
    }

    if ($oldversion < 2026091506) {
        $table = new xmldb_table('local_fredkichat_bots');
        $field = new xmldb_field('agentwelcome', XMLDB_TYPE_TEXT, null, null, null, null, null, 'name');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026091506, 'local', 'fredkichat');
    }

    if ($oldversion < 2026091813) {
        $table = new xmldb_table('local_fredkichat_bots');
        $fields = [
            new xmldb_field('botmessagecolor', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, '#FFFFFF', 'textcolor'),
            new xmldb_field('usermessagecolor', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, '#DCEFFA', 'botmessagecolor'),
            new xmldb_field('bordercolor', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, '#D6E6F2', 'usermessagecolor'),
            new xmldb_field('hovercolor', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, '#E8F3FA', 'bordercolor'),
            new xmldb_field('footercolor', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, '#6E7F8D', 'hovercolor'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        upgrade_plugin_savepoint(true, 2026091813, 'local', 'fredkichat');
    }

    if ($oldversion < 2026092402) {
        $table = new xmldb_table('local_fredkichat_bots');

        $externalenabled = new xmldb_field(
            'externalenabled',
            XMLDB_TYPE_INTEGER,
            '1',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'feedbackurl'
        );
        if (!$dbman->field_exists($table, $externalenabled)) {
            $dbman->add_field($table, $externalenabled);
        }

        $publickey = new xmldb_field('publickey', XMLDB_TYPE_CHAR, '64', null, null, null, null, 'externalenabled');
        if (!$dbman->field_exists($table, $publickey)) {
            $dbman->add_field($table, $publickey);
        }

        $alloweddomains = new xmldb_field('alloweddomains', XMLDB_TYPE_TEXT, null, null, null, null, null, 'publickey');
        if (!$dbman->field_exists($table, $alloweddomains)) {
            $dbman->add_field($table, $alloweddomains);
        }

        $publickeyindex = new xmldb_index('publickey_uix', XMLDB_INDEX_UNIQUE, ['publickey']);
        if (!$dbman->index_exists($table, $publickeyindex)) {
            $dbman->add_index($table, $publickeyindex);
        }

        $sessiontable = new xmldb_table('local_fredkichat_public');
        if (!$dbman->table_exists($sessiontable)) {
            $sessiontable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $sessiontable->add_field('sessionkey', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
            $sessiontable->add_field('botid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $sessiontable->add_field('threadid', XMLDB_TYPE_CHAR, '128', null, XMLDB_NOTNULL, null, null);
            $sessiontable->add_field('bearer', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $sessiontable->add_field('chatid', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $sessiontable->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $sessiontable->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $sessiontable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $sessiontable->add_index('sessionkey_uix', XMLDB_INDEX_UNIQUE, ['sessionkey']);
            $sessiontable->add_index('botid_ix', XMLDB_INDEX_NOTUNIQUE, ['botid']);
            $dbman->create_table($sessiontable);
        }

        if (!get_config('local_fredkichat', 'publicsigningsecret')) {
            set_config('publicsigningsecret', bin2hex(random_bytes(32)), 'local_fredkichat');
        }

        upgrade_plugin_savepoint(true, 2026092402, 'local', 'fredkichat');
    }

    if ($oldversion < 2026092403) {
        $table = new xmldb_table('local_fredkichat_bots');
        $blockeddomains = new xmldb_field('blockeddomains', XMLDB_TYPE_TEXT, null, null, null, null, null, 'alloweddomains');
        if (!$dbman->field_exists($table, $blockeddomains)) {
            $dbman->add_field($table, $blockeddomains);
        }
        upgrade_plugin_savepoint(true, 2026092403, 'local', 'fredkichat');
    }

    if ($oldversion < 2026102800) {
        $table = new xmldb_table('local_fredkichat_bots');

        // Chatbots are only usable through course blocks unless site-wide access is enabled explicitly.
        $siteaccess = new xmldb_field('siteaccess', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'token');
        if (!$dbman->field_exists($table, $siteaccess)) {
            $dbman->add_field($table, $siteaccess);
        }

        // Remove fields and the template table that were deprecated during development.
        foreach (['courseid', 'visibility'] as $fieldname) {
            $field = new xmldb_field($fieldname);
            if ($dbman->field_exists($table, $field)) {
                $dbman->drop_field($table, $field);
            }
        }
        $templatetable = new xmldb_table('local_fredkichat_template');
        if ($dbman->table_exists($templatetable)) {
            $dbman->drop_table($templatetable);
        }
        get_file_storage()->delete_area_files(context_system::instance()->id, 'local_fredkichat', 'template_logo');

        $agentindex = new xmldb_index('agentid_ix', XMLDB_INDEX_NOTUNIQUE, ['agentid']);
        if (!$dbman->index_exists($table, $agentindex)) {
            $dbman->add_index($table, $agentindex);
        }

        $publictable = new xmldb_table('local_fredkichat_public');
        $botindex = new xmldb_index('botid_ix', XMLDB_INDEX_NOTUNIQUE, ['botid']);
        if ($dbman->index_exists($publictable, $botindex)) {
            $dbman->drop_index($publictable, $botindex);
        }
        $botkey = new xmldb_key('botid_fk', XMLDB_KEY_FOREIGN, ['botid'], 'local_fredkichat_bots', ['id']);
        $dbman->add_key($publictable, $botkey);
        $timeindex = new xmldb_index('timemodified_ix', XMLDB_INDEX_NOTUNIQUE, ['timemodified']);
        if (!$dbman->index_exists($publictable, $timeindex)) {
            $dbman->add_index($publictable, $timeindex);
        }

        // The login path is fixed by the MaxKB v2 API.
        unset_config('loginpath', 'local_fredkichat');

        upgrade_plugin_savepoint(true, 2026102800, 'local', 'fredkichat');
    }

    return true;
}
