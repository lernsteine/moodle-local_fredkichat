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
 * List of styled FredKI chatbots.
 *
 * @package    local_fredkichat
 * @copyright  2026 lernsteine.com
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_fredkichat\bot_helper;

admin_externalpage_setup('local_fredkichat_manage');
require_capability('local/fredkichat:manage', context_system::instance());

$PAGE->set_url(new moodle_url('/local/fredkichat/index.php'));
$PAGE->set_title(get_string('managebots', 'local_fredkichat'));
$PAGE->set_heading(get_string('managebots', 'local_fredkichat'));

$bots = $DB->get_records('local_fredkichat_bots', null, 'name ASC');

echo $OUTPUT->header();
echo html_writer::tag('p', get_string('managebots_desc', 'local_fredkichat'));
echo html_writer::div(
    html_writer::link(
        new moodle_url('/local/fredkichat/edit.php'),
        get_string('newbot', 'local_fredkichat'),
        ['class' => 'btn btn-primary']
    ),
    'mb-3'
);

if ($bots) {
    $userids = array_filter(array_map(static fn($bot) => (int)$bot->owneruserid, $bots));
    $owners = $userids ? $DB->get_records_list('user', 'id', array_unique($userids), '', implode(
        ',',
        \core_user\fields::for_name()->including('id')->get_required_fields()
    )) : [];

    $table = new html_table();
    $table->head = [
        get_string('logo', 'local_fredkichat'),
        get_string('name', 'local_fredkichat'),
        get_string('agent', 'local_fredkichat'),
        get_string('owner', 'local_fredkichat'),
        get_string('siteaccess', 'local_fredkichat'),
        get_string('externalstatus', 'local_fredkichat'),
        get_string('actions', 'local_fredkichat'),
    ];
    foreach ($bots as $bot) {
        $owner = isset($owners[$bot->owneruserid]) ? fullname($owners[$bot->owneruserid]) : '';
        $actions = [
            html_writer::link(
                new moodle_url('/local/fredkichat/edit.php', ['id' => $bot->id]),
                get_string('edit', 'local_fredkichat')
            ),
            html_writer::link(
                new moodle_url('/local/fredkichat/preview.php', ['bot' => $bot->id]),
                get_string('preview', 'local_fredkichat'),
                ['target' => '_blank']
            ),
            html_writer::link(
                new moodle_url('/local/fredkichat/delete.php', ['id' => $bot->id]),
                get_string('delete', 'local_fredkichat')
            ),
        ];
        $logourl = bot_helper::get_logo_url((int)$bot->id);
        $logohtml = $logourl
            ? html_writer::empty_tag('img', ['src' => $logourl->out(false), 'alt' => '', 'class' => 'fredkichat-admin-logo',
                'width' => 48, 'height' => 48])
            : html_writer::span('F', 'fredkichat-admin-logo fredkichat-admin-logo-fallback');
        $table->data[] = [
            $logohtml,
            format_string($bot->name),
            s($bot->agentid ?? ''),
            s($owner),
            get_string(!empty($bot->siteaccess) ? 'enabled' : 'disabled', 'local_fredkichat'),
            get_string(!empty($bot->externalenabled) ? 'enabled' : 'disabled', 'local_fredkichat'),
            implode(' | ', $actions),
        ];
    }
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
