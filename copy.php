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
 * Copy a chatbot design, including its logo. The copy opens for editing.
 *
 * @package    local_fredkichat
 * @copyright  2026 lernsteine.com
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$id = required_param('id', PARAM_INT);

admin_externalpage_setup('local_fredkichat_manage');
$context = context_system::instance();
require_capability('local/fredkichat:manage', $context);
require_sesskey();

$bot = $DB->get_record('local_fredkichat_bots', ['id' => $id], '*', MUST_EXIST);

$copy = clone($bot);
unset($copy->id);
$copy->name = core_text::substr(get_string('copyname', 'local_fredkichat', $bot->name), 0, 255);
$copy->owneruserid = $USER->id;
// A copy is never embedded on external websites until it is enabled on purpose, and gets its own embed key.
$copy->externalenabled = 0;
$copy->publickey = null;
$copy->timecreated = time();
$copy->timemodified = time();
$newid = $DB->insert_record('local_fredkichat_bots', $copy);

$fs = get_file_storage();
foreach ($fs->get_area_files($context->id, 'local_fredkichat', 'logo', $bot->id, 'itemid, filepath, filename', false) as $file) {
    $fs->create_file_from_storedfile(['itemid' => $newid], $file);
}

redirect(
    new moodle_url('/local/fredkichat/edit.php', ['id' => $newid]),
    get_string('copied', 'local_fredkichat'),
    null,
    \core\output\notification::NOTIFY_SUCCESS
);
