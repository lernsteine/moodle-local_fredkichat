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
 * Regenerate the public embed key of a chatbot.
 *
 * @package    local_fredkichat
 * @copyright  2026 lernsteine.com
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_fredkichat\bot_helper;

$id = required_param('id', PARAM_INT);

admin_externalpage_setup('local_fredkichat_manage');
require_capability('local/fredkichat:manage', context_system::instance());
require_sesskey();

$bot = $DB->get_record('local_fredkichat_bots', ['id' => $id], '*', MUST_EXIST);
$bot->publickey = bot_helper::generate_public_key();
$bot->timemodified = time();
$DB->update_record('local_fredkichat_bots', $bot);
$DB->delete_records('local_fredkichat_public', ['botid' => $id]);

redirect(new moodle_url('/local/fredkichat/edit.php', ['id' => $id]), get_string('publickeyregenerated', 'local_fredkichat'));
