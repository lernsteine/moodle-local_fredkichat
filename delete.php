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
 * Delete a styled FredKI chatbot.
 *
 * @package    local_fredkichat
 * @copyright  2026 lernsteine.com
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$id = required_param('id', PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

admin_externalpage_setup('local_fredkichat_manage');
$context = context_system::instance();
require_capability('local/fredkichat:manage', $context);
$bot = $DB->get_record('local_fredkichat_bots', ['id' => $id], '*', MUST_EXIST);

$PAGE->set_url(new moodle_url('/local/fredkichat/delete.php', ['id' => $id]));
$PAGE->set_title(get_string('deletebot', 'local_fredkichat'));
$PAGE->set_heading(get_string('deletebot', 'local_fredkichat'));

if ($confirm) {
    require_sesskey();
    get_file_storage()->delete_area_files($context->id, 'local_fredkichat', 'logo', $id);
    $DB->delete_records('local_fredkichat_public', ['botid' => $id]);
    $DB->delete_records('local_fredkichat_bots', ['id' => $id]);
    redirect(new moodle_url('/local/fredkichat/index.php'));
}

echo $OUTPUT->header();
echo $OUTPUT->confirm(
    get_string('confirmdelete', 'local_fredkichat', format_string($bot->name)),
    new moodle_url('/local/fredkichat/delete.php', ['id' => $id, 'confirm' => 1, 'sesskey' => sesskey()]),
    new moodle_url('/local/fredkichat/index.php')
);
echo $OUTPUT->footer();
