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
 * Preview of a styled chatbot for chatbot managers.
 *
 * @package    local_fredkichat
 * @copyright  2026 lernsteine.com
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$botid = required_param('bot', PARAM_INT);

require_login();
$context = context_system::instance();
require_capability('local/fredkichat:manage', $context);
$bot = $DB->get_record('local_fredkichat_bots', ['id' => $botid], '*', MUST_EXIST);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/fredkichat/preview.php', ['bot' => $botid]));
$PAGE->set_title(get_string('preview', 'local_fredkichat') . ': ' . format_string($bot->name));
$PAGE->set_heading(get_string('preview', 'local_fredkichat'));

// Load the same launcher script that is used inside courses.
$PAGE->requires->js(new moodle_url('/local/fredkichat/launcher.php', ['bot' => $botid]));

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($bot->name));
echo html_writer::tag('p', get_string('preview_desc', 'local_fredkichat'));
echo $OUTPUT->footer();
