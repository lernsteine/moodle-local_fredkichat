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
 * Server-sent events endpoint that relays a streamed FredKI answer.
 *
 * @package    local_fredkichat
 * @copyright  2026 lernsteine.com
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Output must reach the browser immediately.
define('NO_OUTPUT_BUFFERING', true);

require_once(__DIR__ . '/../../config.php');

use local_fredkichat\bot_helper;
use local_fredkichat\client;

$PAGE->set_context(context_system::instance());

$publictoken = optional_param('publictoken', '', PARAM_RAW_TRIMMED);
$ispublic = $publictoken !== '';
$botid = required_param('botid', PARAM_INT);
$blockid = optional_param('blockid', 0, PARAM_INT);
$message = required_param('message', PARAM_RAW_TRIMMED);
$threadid = required_param('threadid', PARAM_ALPHANUMEXT);
if ($message === '' || $threadid === '' || core_text::strlen($message) > 8000) {
    throw new moodle_exception('invalidmessage', 'local_fredkichat');
}

if ($ispublic) {
    $claims = bot_helper::verify_public_token($publictoken);
    if ((int)$claims['botid'] !== $botid) {
        throw new moodle_exception('invalidpublictoken', 'local_fredkichat');
    }
    $bot = $DB->get_record('local_fredkichat_bots', ['id' => $botid, 'externalenabled' => 1], '*', MUST_EXIST);
} else {
    require_login(null, false);
    require_sesskey();
    $bot = $DB->get_record('local_fredkichat_bots', ['id' => $botid], '*', MUST_EXIST);
    bot_helper::require_bot_access($bot, $blockid);
}

// Stop the upstream FredKI transfer when the browser closes the request.
ignore_user_abort(false);

$emit = static function (array $payload, bool $pad = true): void {
    echo 'data: ' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
    if ($pad) {
        // Some FastCGI and proxy stacks coalesce small writes. An ignored SSE comment forces a flush.
        echo ':pad ' . str_repeat(' ', 8192) . "\n\n";
    }
    flush();
};

$state = null;
$setuperror = '';
$publicsessionkey = '';
try {
    bot_helper::check_rate_limit($bot, $ispublic);
    $client = new client((string)get_config('local_fredkichat', 'host'), (string)$bot->token);

    if ($ispublic) {
        // Public embeds have no Moodle session. Only the FredKI session state is kept, keyed by the
        // signed embed nonce and the browser thread id. No chat messages are stored.
        $publicsessionkey = hash('sha256', $claims['nonce'] . '|' . $threadid . '|' . $botid);
        $saved = $DB->get_record('local_fredkichat_public', ['sessionkey' => $publicsessionkey]);
        if ($saved) {
            $state = ['bearer' => $saved->bearer, 'chatid' => $saved->chatid];
        } else {
            $state = $client->open_session();
            $record = (object)[
                'sessionkey' => $publicsessionkey,
                'botid' => $botid,
                'threadid' => $threadid,
                'bearer' => $state['bearer'],
                'chatid' => $state['chatid'],
                'timecreated' => time(),
                'timemodified' => time(),
            ];
            try {
                $DB->insert_record('local_fredkichat_public', $record);
            } catch (dml_write_exception $e) {
                // A concurrent first request for the same thread inserted the record already.
                $saved = $DB->get_record('local_fredkichat_public', ['sessionkey' => $publicsessionkey], '*', MUST_EXIST);
                $state = ['bearer' => $saved->bearer, 'chatid' => $saved->chatid];
            }
        }
    } else {
        $sessionstate = $SESSION->local_fredkichat ?? [];
        if (!is_array($sessionstate)) {
            $sessionstate = [];
        }
        $state = $sessionstate[$botid][$threadid] ?? null;
        if (!is_array($state) || empty($state['bearer']) || empty($state['chatid'])) {
            $state = $client->open_session();
            $sessionstate[$botid][$threadid] = $state;
            $SESSION->local_fredkichat = $sessionstate;
        }
    }
} catch (Throwable $e) {
    $setuperror = $e instanceof moodle_exception ? $e->getMessage() : get_string('error', 'local_fredkichat');
}

// Persist the session and release Moodle's session lock before the long-running response.
\core\session\manager::write_close();

header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate, no-transform');
header('X-Accel-Buffering: no');
header_remove('Content-Length');
if (function_exists('apache_setenv')) {
    // Disable mod_deflate for this response, it would buffer the stream.
    apache_setenv('no-gzip', '1');
}

echo ':open ' . str_repeat(' ', 16384) . "\n\n";
flush();

if (!empty($setuperror)) {
    $emit(['type' => 'error', 'error' => $setuperror]);
    die();
}

$emit(['type' => 'status', 'status' => 'thinking']);

try {
    // FredKI emits very small fragments. Relay them in small natural packets so that not every token
    // carries its own flush padding.
    $relaybuffer = '';
    $client->stream_message(
        $state['bearer'],
        $state['chatid'],
        $message,
        static function (string $content) use ($emit, &$relaybuffer): void {
            $relaybuffer .= $content;
            if (strlen($relaybuffer) >= 16 || strpos($content, "\n") !== false || preg_match('/[.!?;:]\s*$/u', $content)) {
                $emit(['type' => 'chunk', 'content' => $relaybuffer]);
                $relaybuffer = '';
            }
        },
        static function (string $recordid) use ($emit, $state, &$relaybuffer): void {
            if ($relaybuffer !== '') {
                $emit(['type' => 'chunk', 'content' => $relaybuffer]);
                $relaybuffer = '';
            }
            $emit(['type' => 'done', 'chatid' => $state['chatid'], 'recordid' => $recordid]);
        }
    );
    if ($ispublic) {
        $DB->set_field('local_fredkichat_public', 'timemodified', time(), ['sessionkey' => $publicsessionkey]);
    }
} catch (Throwable $e) {
    $errormessage = $e instanceof moodle_exception ? $e->getMessage() : get_string('error', 'local_fredkichat');
    $emit(['type' => 'error', 'error' => $errormessage]);
}
