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
 * JavaScript launcher that adds the floating chat button to a page.
 *
 * @package    local_fredkichat
 * @copyright  2026 lernsteine.com
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// This script performs its own access checks: public embeds are checked by key and domain, internal
// use through bot_helper::can_use_bot(). A login redirect would break the JavaScript response.
// phpcs:ignore moodle.Files.RequireLogin.Missing
require_once(__DIR__ . '/../../config.php');

use local_fredkichat\bot_helper;

/**
 * Stop with a JavaScript warning instead of an HTML error page.
 *
 * @param int $status HTTP status.
 * @param string $stringid Language string id.
 */
function local_fredkichat_launcher_fail(int $status, string $stringid): void {
    http_response_code($status);
    header('Content-Type: application/javascript; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo 'console.warn(' . json_encode('FredKI Chat: ' . get_string($stringid, 'local_fredkichat')) . ');';
    die();
}

$PAGE->set_context(context_system::instance());

$publickey = optional_param('key', '', PARAM_ALPHANUM);
if ($publickey !== '') {
    $bot = bot_helper::get_public_bot($publickey);
    if (!$bot) {
        local_fredkichat_launcher_fail(404, 'publicembednotavailable');
    }
    if (!bot_helper::is_domain_allowed($bot)) {
        local_fredkichat_launcher_fail(403, 'externaldomainnotallowed');
    }
    $embedurl = new moodle_url('/local/fredkichat/embed.php', ['key' => $publickey]);
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Vary: Referer, Origin');
} else {
    $botid = required_param('bot', PARAM_INT);
    $blockid = optional_param('block', 0, PARAM_INT);
    $bot = $DB->get_record('local_fredkichat_bots', ['id' => $botid]);
    if (!$bot || !bot_helper::can_use_bot($bot, $blockid)) {
        local_fredkichat_launcher_fail(403, 'nobotaccess');
    }
    $embedurl = new moodle_url('/local/fredkichat/embed.php', ['bot' => $botid, 'block' => $blockid]);
    header('Cache-Control: private, no-store');
}

header('Content-Type: application/javascript; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$logo = bot_helper::get_logo_url((int)$bot->id);
$name = format_string($bot->name);
$config = [
    'id' => 'fredkichat-launcher-' . (int)$bot->id,
    'embedUrl' => $embedurl->out(false),
    'logoUrl' => $logo ? $logo->out(false) : '',
    'name' => $name,
    'accent' => preg_match('/^#[0-9A-Fa-f]{6}$/', (string)$bot->accentcolor) ? $bot->accentcolor : '#9B8975',
    'strings' => [
        'open' => get_string('launcheropen', 'local_fredkichat', $name),
        'close' => get_string('launcherclose', 'local_fredkichat'),
        'maximize' => get_string('launchermaximize', 'local_fredkichat'),
        'minimize' => get_string('launcherminimize', 'local_fredkichat'),
    ],
];
$json = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS |
    JSON_HEX_QUOT);

$script = <<<'JS'
(function(cfg) {
    'use strict';
    if (document.getElementById(cfg.id)) {
        return;
    }

    const root = document.createElement('div');
    root.id = cfg.id;
    root.className = 'fredkichat-launcher-root';

    const style = document.createElement('style');
    style.textContent = `
        #${cfg.id} .fredkichat-launcher-button {
            position: fixed;
            right: 24px;
            bottom: 24px;
            width: 68px;
            height: 68px;
            border: 0;
            border-radius: 50%;
            padding: 0;
            background: ${cfg.accent};
            box-shadow: 0 8px 28px rgba(0,0,0,.22);
            cursor: pointer;
            overflow: hidden;
            z-index: 2147483000;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform .18s ease, box-shadow .18s ease;
        }
        #${cfg.id} .fredkichat-launcher-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 32px rgba(0,0,0,.26);
        }
        #${cfg.id} .fredkichat-launcher-button img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        #${cfg.id} .fredkichat-launcher-fallback {
            color: #fff;
            font: 700 28px/1 -apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif;
        }
        #${cfg.id} .fredkichat-launcher-panel {
            position: fixed;
            right: 24px;
            bottom: 104px;
            width: 460px;
            height: min(680px, calc(100vh - 132px));
            max-height: calc(100vh - 132px);
            border-radius: 18px;
            background: #fff;
            box-shadow: 0 16px 48px rgba(0,0,0,.24);
            overflow: hidden;
            z-index: 2147482999;
            opacity: 0;
            transform: translateY(12px) scale(.985);
            pointer-events: none;
            transition: opacity .18s ease, transform .18s ease;
        }
        #${cfg.id}.is-open .fredkichat-launcher-panel {
            opacity: 1;
            transform: translateY(0) scale(1);
            pointer-events: auto;
        }
        #${cfg.id}.is-open .fredkichat-launcher-button {
            opacity: 0;
            pointer-events: none;
        }
        #${cfg.id} .fredkichat-launcher-frame {
            width: 100%;
            height: 100%;
            border: 0;
            display: block;
            background: #fff;
        }
        #${cfg.id} .fredkichat-launcher-close,
        #${cfg.id} .fredkichat-launcher-maximize {
            position: absolute;
            top: 12px;
            width: 38px;
            height: 38px;
            border: 0;
            border-radius: 50%;
            background: rgba(255,255,255,.88);
            color: #333;
            cursor: pointer;
            z-index: 3;
            box-shadow: 0 2px 10px rgba(0,0,0,.12);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        #${cfg.id} .fredkichat-launcher-close {
            right: 12px;
            font: 400 28px/38px Arial,sans-serif;
        }
        #${cfg.id} .fredkichat-launcher-maximize {
            right: 58px;
        }
        #${cfg.id} .fredkichat-launcher-maximize svg {
            width: 20px;
            height: 20px;
            display: block;
        }
        #${cfg.id} .fredkichat-launcher-panel.is-maximized {
            top: auto;
            left: auto;
            right: 16px;
            bottom: 16px;
            width: min(920px, calc(100vw - 32px));
            height: min(1360px, calc(100vh - 32px));
            max-height: calc(100vh - 32px);
            border-radius: 18px;
        }
        @media (max-width: 600px) {
            #${cfg.id} .fredkichat-launcher-button {
                right: max(16px, env(safe-area-inset-right));
                bottom: max(16px, env(safe-area-inset-bottom));
                width: 62px;
                height: 62px;
            }

            /*
             * Mobile browsers (especially Chrome/Samsung Internet on Android) can
             * report 100vh as the *largest* possible viewport, including the area
             * behind the browser bars. A bottom-positioned panel with a 100vh-based
             * height can therefore start above the actually visible viewport and
             * clip the header/close button. Stretching between explicit top/bottom
             * edges avoids that problem and also respects display cutouts.
             */
            #${cfg.id} .fredkichat-launcher-panel,
            #${cfg.id} .fredkichat-launcher-panel.is-maximized {
                top: max(8px, env(safe-area-inset-top));
                right: max(8px, env(safe-area-inset-right));
                bottom: max(8px, env(safe-area-inset-bottom));
                left: max(8px, env(safe-area-inset-left));
                width: auto;
                height: auto;
                max-height: none;
                border-radius: 16px;
            }

            #${cfg.id} .fredkichat-launcher-close,
            #${cfg.id} .fredkichat-launcher-maximize {
                top: 10px;
            }
        }
    `;

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'fredkichat-launcher-button';
    button.setAttribute('aria-label', cfg.strings.open);
    button.setAttribute('title', cfg.name);
    if (cfg.logoUrl) {
        const img = document.createElement('img');
        img.src = cfg.logoUrl;
        img.alt = '';
        button.appendChild(img);
    } else {
        const fallback = document.createElement('span');
        fallback.className = 'fredkichat-launcher-fallback';
        fallback.textContent = 'F';
        button.appendChild(fallback);
    }

    const panel = document.createElement('div');
    panel.className = 'fredkichat-launcher-panel';
    panel.setAttribute('role', 'dialog');
    panel.setAttribute('aria-label', cfg.name);

    const maximize = document.createElement('button');
    maximize.type = 'button';
    maximize.className = 'fredkichat-launcher-maximize';
    maximize.setAttribute('aria-label', cfg.strings.maximize);
    maximize.setAttribute('title', cfg.strings.maximize);
    maximize.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8.5 3H3v5.5h2V6.4l4.8 4.8 1.4-1.4L6.' +
        '4 5H8.5V3Zm7 0v2h2.1l-4.8 4.8 1.4 1.4L19 6.4v2.1h2V3h-5.5ZM9.8 12.8 5 17.6v-2.1H3V21h5.5v-' +
        '2H6.4l4.8-4.8-1.4-1.4Zm4.4 0-1.4 1.4 4.8 4.8h-2.1v2H21v-5.5h-2v2.1l-4.8-4.8Z" fill="curren' +
        'tColor"/></svg>';

    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'fredkichat-launcher-close';
    close.setAttribute('aria-label', cfg.strings.close);
    close.setAttribute('title', cfg.strings.close);
    close.textContent = '×';

    const frame = document.createElement('iframe');
    frame.className = 'fredkichat-launcher-frame';
    frame.src = cfg.embedUrl;
    frame.title = cfg.name;
    frame.loading = 'lazy';
    frame.allow = 'microphone';

    panel.appendChild(maximize);
    panel.appendChild(close);
    panel.appendChild(frame);
    root.appendChild(style);
    root.appendChild(button);
    root.appendChild(panel);
    document.body.appendChild(root);

    const openChat = () => {
        root.classList.add('is-open');
        close.focus();
    };
    const closeChat = () => {
        root.classList.remove('is-open');
        panel.classList.remove('is-maximized');
        maximize.setAttribute('aria-label', cfg.strings.maximize);
        maximize.setAttribute('title', cfg.strings.maximize);
        button.focus();
    };
    const toggleMaximize = () => {
        const maximized = panel.classList.toggle('is-maximized');
        const label = maximized ? cfg.strings.minimize : cfg.strings.maximize;
        maximize.setAttribute('aria-label', label);
        maximize.setAttribute('title', label);
    };

    button.addEventListener('click', openChat);
    maximize.addEventListener('click', toggleMaximize);
    close.addEventListener('click', closeChat);
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && root.classList.contains('is-open')) {
            closeChat();
        }
    });
})
JS;

echo $script . '(' . $json . ');' . "\n";
