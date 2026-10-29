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
 * Admin settings of local_fredkichat.
 *
 * @package    local_fredkichat
 * @copyright  2026 lernsteine.com
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $page = new admin_settingpage('local_fredkichat_settings', get_string('pluginname', 'local_fredkichat'));

    $page->add(new admin_setting_heading(
        'local_fredkichat/connection',
        get_string('connection', 'local_fredkichat'),
        get_string('connection_desc', 'local_fredkichat')
    ));
    $page->add(new admin_setting_configtext(
        'local_fredkichat/host',
        get_string('host', 'local_fredkichat'),
        get_string('host_desc', 'local_fredkichat'),
        'https://fredki.lernsteine.com',
        PARAM_URL
    ));
    $page->add(new admin_setting_configtext(
        'local_fredkichat/workspace',
        get_string('workspace', 'local_fredkichat'),
        get_string('workspace_desc', 'local_fredkichat'),
        'default',
        PARAM_ALPHANUMEXT
    ));
    $page->add(new admin_setting_configtext(
        'local_fredkichat/username',
        get_string('username', 'local_fredkichat'),
        get_string('credentials_desc', 'local_fredkichat'),
        '',
        PARAM_RAW_TRIMMED
    ));
    $page->add(new admin_setting_configpasswordunmask(
        'local_fredkichat/password',
        get_string('password', 'local_fredkichat'),
        '',
        ''
    ));

    $page->add(new admin_setting_heading(
        'local_fredkichat/limits',
        get_string('limits', 'local_fredkichat'),
        get_string('limits_desc', 'local_fredkichat')
    ));
    $page->add(new admin_setting_configtext(
        'local_fredkichat/userratelimit',
        get_string('userratelimit', 'local_fredkichat'),
        get_string('userratelimit_desc', 'local_fredkichat'),
        120,
        PARAM_INT
    ));
    $page->add(new admin_setting_configtext(
        'local_fredkichat/publicratelimit',
        get_string('publicratelimit', 'local_fredkichat'),
        get_string('publicratelimit_desc', 'local_fredkichat'),
        30,
        PARAM_INT
    ));

    $ADMIN->add('localplugins', $page);
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_fredkichat_manage',
        get_string('managebots', 'local_fredkichat'),
        new moodle_url('/local/fredkichat/index.php'),
        'local/fredkichat:manage'
    ));
}
