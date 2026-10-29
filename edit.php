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
 * Create or edit the design of a FredKI chatbot.
 *
 * @package    local_fredkichat
 * @copyright  2026 lernsteine.com
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/filelib.php');

use local_fredkichat\admin_client;
use local_fredkichat\bot_helper;
use local_fredkichat\form\bot_form;

$id = optional_param('id', 0, PARAM_INT);

admin_externalpage_setup('local_fredkichat_manage');
$context = context_system::instance();
require_capability('local/fredkichat:manage', $context);
$bot = $id ? $DB->get_record('local_fredkichat_bots', ['id' => $id], '*', MUST_EXIST) : null;

$PAGE->set_url(new moodle_url('/local/fredkichat/edit.php', ['id' => $id]));
$PAGE->set_title(get_string($id ? 'editbot' : 'newbot', 'local_fredkichat'));
$PAGE->set_heading($PAGE->title);

$agents = [];
$agenterror = '';
$adminclient = null;
try {
    $adminclient = admin_client::from_config();
    $agents = $adminclient->list_agents();
    // One Moodle design per FredKI agent: hide agents that already have a design.
    $used = bot_helper::get_bots_by_agentid();
    $agents = array_values(array_filter($agents, static function (array $agent) use ($used, $bot): bool {
        return !isset($used[$agent['id']]) || ($bot && (int)$used[$agent['id']]->id === (int)$bot->id);
    }));
} catch (moodle_exception $e) {
    $agenterror = $e->getMessage();
}

// Keep the current agent selectable while FredKI is temporarily unavailable.
if ($bot && !empty($bot->agentid) && !in_array((string)$bot->agentid, array_column($agents, 'id'), true)) {
    $agents[] = [
        'id' => (string)$bot->agentid,
        'name' => $bot->name . ' (' . get_string('currentselection', 'local_fredkichat') . ')',
    ];
}

$draftid = file_get_submitted_draft_itemid('logo_filemanager');
file_prepare_draft_area(
    $draftid,
    $context->id,
    'local_fredkichat',
    'logo',
    $bot ? $bot->id : null,
    bot_form::logo_options()
);

$defaults = $bot ? clone($bot) : (object)array_merge(bot_helper::COLOURS, [
    'id' => 0,
    'name' => '',
    'agentid' => '',
    'siteaccess' => 0,
    'footertext' => get_string('footertext_default', 'local_fredkichat'),
    'externalenabled' => 0,
]);
$defaults->logo_filemanager = $draftid;

$mform = new bot_form(null, ['agents' => $agents]);
$mform->set_data($defaults);

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/fredkichat/index.php'));
}

if ($data = $mform->get_data()) {
    $agentid = (string)($data->agentid ?? '');
    $selected = null;
    foreach ($agents as $agent) {
        if ($agent['id'] === $agentid) {
            $selected = $agent;
            break;
        }
    }
    if (!$selected) {
        throw new moodle_exception('invalidagent', 'local_fredkichat');
    }

    // Resolve the public access token of the agent. Keep the stored one if FredKI is unavailable.
    $token = ($bot && (string)$bot->agentid === $agentid) ? (string)$bot->token : '';
    $agentwelcome = ($bot && (string)$bot->agentid === $agentid) ? (string)($bot->agentwelcome ?? '') : '';
    if ($adminclient) {
        try {
            $access = $adminclient->get_access_token($agentid);
            if ($access['token'] === '' || !$access['active']) {
                throw new moodle_exception('agenttokenmissing', 'local_fredkichat');
            }
            $token = $access['token'];
            $agentwelcome = $adminclient->get_agent_welcome($agentid) ?: $agentwelcome;
        } catch (local_fredkichat\unauthorised_exception $e) {
            throw $e;
        } catch (moodle_exception $e) {
            if ($e->errorcode === 'agenttokenmissing' || $token === '') {
                throw new moodle_exception('agenttokenmissing', 'local_fredkichat');
            }
        }
    }
    if ($token === '') {
        throw new moodle_exception('agenttokenmissing', 'local_fredkichat');
    }

    $name = trim($data->name);
    $record = (object)[
        'agentid' => $agentid,
        'name' => $name !== '' ? $name : $selected['name'],
        'agentwelcome' => $agentwelcome,
        'token' => $token,
        'siteaccess' => empty($data->siteaccess) ? 0 : 1,
        'footertext' => trim($data->footertext ?? ''),
        'imprinturl' => trim($data->imprinturl ?? ''),
        'privacyurl' => trim($data->privacyurl ?? ''),
        'feedbackurl' => trim($data->feedbackurl ?? ''),
        'externalenabled' => empty($data->externalenabled) ? 0 : 1,
        'alloweddomains' => bot_helper::normalise_domains((string)($data->alloweddomains ?? '')),
        'blockeddomains' => bot_helper::normalise_domains((string)($data->blockeddomains ?? '')),
        'timemodified' => time(),
    ];
    foreach (bot_helper::COLOURS as $field => $default) {
        $value = strtoupper(trim((string)($data->$field ?? '')));
        $record->$field = $value !== '' ? $value : $default;
    }
    // Keep the public key while external embedding is disabled so re-enabling keeps old embed codes working.
    if ($bot && !empty($bot->publickey)) {
        $record->publickey = (string)$bot->publickey;
    } else if ($record->externalenabled) {
        $record->publickey = bot_helper::generate_public_key();
    }

    if ($bot) {
        $record->id = (int)$bot->id;
        $DB->update_record('local_fredkichat_bots', $record);
        $botid = $record->id;
    } else {
        $record->owneruserid = $USER->id;
        $record->timecreated = time();
        $botid = $DB->insert_record('local_fredkichat_bots', $record);
    }
    cache::make('local_fredkichat', 'agentmeta')->delete('welcome_' . sha1($agentid));

    file_save_draft_area_files(
        $data->logo_filemanager,
        $context->id,
        'local_fredkichat',
        'logo',
        $botid,
        bot_form::logo_options()
    );

    redirect(new moodle_url('/local/fredkichat/edit.php', ['id' => $botid]), get_string('changessaved'));
}

echo $OUTPUT->header();
if ($agenterror !== '') {
    echo $OUTPUT->notification($agenterror, 'notifyproblem');
} else if (!$agents) {
    echo $OUTPUT->notification(get_string('noagents', 'local_fredkichat'), 'notifywarning');
}
echo html_writer::tag('p', get_string('botform_desc', 'local_fredkichat'));
$mform->display();

if ($bot) {
    echo html_writer::start_div('card mt-4');
    echo html_writer::start_div('card-body');
    echo html_writer::tag('h3', get_string('embedcode', 'local_fredkichat'));
    if (!empty($bot->siteaccess)) {
        $launcherurl = new moodle_url('/local/fredkichat/launcher.php', ['bot' => $bot->id]);
        $code = '<script async defer src="' . $launcherurl->out(false) . '"></script>';
        echo html_writer::tag('p', get_string('embedcode_desc', 'local_fredkichat'));
        echo html_writer::tag('textarea', s($code), ['class' => 'form-control', 'rows' => 3, 'readonly' => 'readonly']);
    } else {
        echo html_writer::tag('p', get_string('embedcode_blockonly', 'local_fredkichat'));
    }
    echo html_writer::end_div();
    echo html_writer::end_div();

    echo html_writer::start_div('card mt-4');
    echo html_writer::start_div('card-body');
    echo html_writer::tag('h3', get_string('externalembedcode', 'local_fredkichat'));
    if (!empty($bot->externalenabled) && !empty($bot->publickey)) {
        $publiclauncher = new moodle_url('/local/fredkichat/launcher.php', ['key' => $bot->publickey]);
        $publiccode = '<script async defer src="' . $publiclauncher->out(false) . '"></script>';
        echo html_writer::tag('p', get_string('externalembedcode_desc', 'local_fredkichat'));
        echo html_writer::tag('textarea', s($publiccode), ['class' => 'form-control', 'rows' => 3, 'readonly' => 'readonly']);
        if (!empty($bot->alloweddomains)) {
            echo html_writer::tag(
                'p',
                get_string('allowedcurrently', 'local_fredkichat', nl2br(s($bot->alloweddomains))),
                ['class' => 'mt-3']
            );
        } else {
            echo html_writer::tag('p', get_string('allowedall', 'local_fredkichat'), ['class' => 'mt-3 text-warning']);
        }
        if (!empty($bot->blockeddomains)) {
            echo html_writer::tag(
                'p',
                get_string('blockedcurrently', 'local_fredkichat', nl2br(s($bot->blockeddomains))),
                ['class' => 'mt-2 text-danger']
            );
        }
        $regenerateurl = new moodle_url('/local/fredkichat/regenerate.php', ['id' => $bot->id, 'sesskey' => sesskey()]);
        echo html_writer::link(
            $regenerateurl,
            get_string('regeneratepublickey', 'local_fredkichat'),
            ['class' => 'btn btn-secondary mt-2']
        );
    } else {
        echo html_writer::tag('p', get_string('externaldisablednotice', 'local_fredkichat'));
    }
    echo html_writer::end_div();
    echo html_writer::end_div();
}
echo $OUTPUT->footer();
