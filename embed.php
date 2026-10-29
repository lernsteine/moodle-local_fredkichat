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
 * Chat page shown inside the launcher iframe.
 *
 * @package    local_fredkichat
 * @copyright  2026 lernsteine.com
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_fredkichat\bot_helper;

$publickey = optional_param('key', '', PARAM_ALPHANUM);
$ispublic = $publickey !== '';
$blockid = 0;
if ($ispublic) {
    $bot = bot_helper::get_public_bot($publickey);
    if (!$bot) {
        throw new moodle_exception('publicembednotavailable', 'local_fredkichat');
    }
    bot_helper::require_public_embed_domain($bot);
} else {
    $botid = required_param('bot', PARAM_INT);
    $blockid = optional_param('block', 0, PARAM_INT);
    require_login(null, false);
    $bot = $DB->get_record('local_fredkichat_bots', ['id' => $botid], '*', MUST_EXIST);
    bot_helper::require_bot_access($bot, $blockid);
}
$botid = (int)$bot->id;

$context = context_system::instance();
$PAGE->set_context($context);
$urlparams = $ispublic ? ['key' => $publickey] : ['bot' => $botid, 'block' => $blockid];
$PAGE->set_url(new moodle_url('/local/fredkichat/embed.php', $urlparams));
$PAGE->set_pagelayout('embedded');
$PAGE->set_title(format_string($bot->name));
$PAGE->set_heading(format_string($bot->name));
if ($ispublic) {
    // This page is intentionally embeddable on external websites when the administrator enabled it
    // for this chatbot. The override only applies to this request.
    $CFG->allowframembedding = true;
}

$PAGE->requires->js_call_amd('local_fredkichat/chat', 'init', [[
    'botid' => $botid,
    'blockid' => $blockid,
    'sesskey' => $ispublic ? '' : sesskey(),
    'publictoken' => $ispublic ? bot_helper::create_public_token($bot) : '',
    'endpoint' => (new moodle_url('/local/fredkichat/stream.php'))->out(false),
    'storagekey' => 'local_fredkichat_threads_' . $botid,
    'strings' => [
        'newchat' => get_string('newchat', 'local_fredkichat'),
        'deletechat' => get_string('deletechat', 'local_fredkichat'),
        'error' => get_string('error', 'local_fredkichat'),
    ],
]]);

// Split bullet lines of the FredKI prologue into clickable starter questions.
$agentwelcome = bot_helper::get_agent_welcome($bot);
$introlines = [];
$suggestions = [];
foreach (preg_split('/\R/u', $agentwelcome) as $line) {
    if (preg_match('/^\s*[-*•]\s+(.+?)\s*$/u', $line, $matches)) {
        $suggestions[] = ['text' => trim($matches[1])];
    } else {
        $introlines[] = $line;
    }
}
$welcomeintro = trim(implode("\n", $introlines));

$logo = bot_helper::get_logo_url($botid);
$data = [
    'botid' => $botid,
    'name' => format_string($bot->name),
    'logo' => $logo ? $logo->out(false) : '',
    'haslogo' => (bool)$logo,
    'footertext' => format_string($bot->footertext ?: get_string('footertext_default', 'local_fredkichat')),
    'imprinturl' => $bot->imprinturl,
    'privacyurl' => $bot->privacyurl,
    'feedbackurl' => $bot->feedbackurl,
    'hasimprint' => !empty($bot->imprinturl),
    'hasprivacy' => !empty($bot->privacyurl),
    'hasfeedback' => !empty($bot->feedbackurl),
    'welcometext' => $welcomeintro !== ''
        ? format_text($welcomeintro, FORMAT_PLAIN, ['context' => $context, 'filter' => false])
        : s(get_string('welcometext', 'local_fredkichat')),
    'welcomesuggestions' => $suggestions,
    'haswelcomesuggestions' => !empty($suggestions),
];
foreach (bot_helper::COLOURS as $field => $default) {
    $value = (string)($bot->$field ?? '');
    $data[$field] = preg_match('/^#[0-9A-Fa-f]{6}$/', $value) ? $value : $default;
}

$pageheader = $OUTPUT->header();
if ($ispublic) {
    header_remove('X-Frame-Options');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    // Do not leak the public embed key to links opened from the chat.
    header('Referrer-Policy: no-referrer');
}
echo $pageheader;
echo $OUTPUT->render_from_template('local_fredkichat/chat', $data);
echo $OUTPUT->footer();
