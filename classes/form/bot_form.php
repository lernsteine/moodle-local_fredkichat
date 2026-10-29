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

namespace local_fredkichat\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use local_fredkichat\bot_helper;

/**
 * Form to configure the design of a FredKI chatbot.
 *
 * @package    local_fredkichat
 * @copyright  2026 lernsteine.com
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bot_form extends \moodleform {
    /**
     * Form definition.
     */
    public function definition() {
        $mform = $this->_form;
        $agents = $this->_customdata['agents'] ?? [];

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);

        $agentoptions = ['' => get_string('chooseagent', 'local_fredkichat')];
        foreach ($agents as $agent) {
            $agentoptions[$agent['id']] = $agent['name'];
        }
        $mform->addElement('select', 'agentid', get_string('agent', 'local_fredkichat'), $agentoptions);
        $mform->addRule('agentid', null, 'required', null, 'client');
        $mform->addHelpButton('agentid', 'agent', 'local_fredkichat');

        $mform->addElement('text', 'name', get_string('displayname', 'local_fredkichat'), ['size' => 50]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addHelpButton('name', 'displayname', 'local_fredkichat');

        $mform->addElement('advcheckbox', 'siteaccess', get_string('siteaccess', 'local_fredkichat'));
        $mform->addHelpButton('siteaccess', 'siteaccess', 'local_fredkichat');

        $mform->addElement('header', 'colorshdr', get_string('colors', 'local_fredkichat'));
        foreach (bot_helper::COLOURS as $field => $default) {
            $mform->addElement('text', $field, get_string($field, 'local_fredkichat'), ['size' => 12, 'placeholder' => $default]);
            $mform->setType($field, PARAM_TEXT);
            $mform->setDefault($field, $default);
        }

        $mform->addElement('header', 'logohdr', get_string('logo', 'local_fredkichat'));
        $mform->addElement(
            'filemanager',
            'logo_filemanager',
            get_string('logo', 'local_fredkichat'),
            null,
            self::logo_options()
        );

        $mform->addElement('header', 'footerhdr', get_string('footer', 'local_fredkichat'));
        $mform->addElement('textarea', 'footertext', get_string('footertext', 'local_fredkichat'), ['rows' => 2, 'cols' => 60]);
        $mform->setType('footertext', PARAM_TEXT);
        $mform->setDefault('footertext', get_string('footertext_default', 'local_fredkichat'));

        foreach (['imprinturl', 'privacyurl', 'feedbackurl'] as $field) {
            $mform->addElement('text', $field, get_string($field, 'local_fredkichat'), ['size' => 60]);
            $mform->setType($field, PARAM_URL);
        }

        $mform->addElement('header', 'externalhdr', get_string('externalembedding', 'local_fredkichat'));
        $mform->addElement('advcheckbox', 'externalenabled', get_string('externalenabled', 'local_fredkichat'));
        $mform->addHelpButton('externalenabled', 'externalenabled', 'local_fredkichat');

        $mform->addElement(
            'textarea',
            'alloweddomains',
            get_string('alloweddomains', 'local_fredkichat'),
            ['rows' => 4, 'cols' => 60]
        );
        $mform->setType('alloweddomains', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('alloweddomains', 'alloweddomains', 'local_fredkichat');

        $mform->addElement(
            'textarea',
            'blockeddomains',
            get_string('blockeddomains', 'local_fredkichat'),
            ['rows' => 4, 'cols' => 60]
        );
        $mform->setType('blockeddomains', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('blockeddomains', 'blockeddomains', 'local_fredkichat');

        $this->add_action_buttons(true);
    }

    /**
     * Options of the logo file manager.
     *
     * @return array
     */
    public static function logo_options(): array {
        return [
            'subdirs' => 0,
            'maxbytes' => 2 * 1024 * 1024,
            'maxfiles' => 1,
            'accepted_types' => ['image'],
        ];
    }

    /**
     * Validation: colour format and one design per FredKI agent.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array Errors.
     */
    public function validation($data, $files) {
        global $DB;
        $errors = parent::validation($data, $files);
        foreach (array_keys(bot_helper::COLOURS) as $field) {
            if (!empty($data[$field]) && !preg_match('/^#[0-9A-Fa-f]{6}$/', trim($data[$field]))) {
                $errors[$field] = get_string('invalidcolor', 'local_fredkichat');
            }
        }
        if (!empty($data['agentid'])) {
            $duplicate = $DB->get_record('local_fredkichat_bots', ['agentid' => (string)$data['agentid']], 'id, name');
            if ($duplicate && (int)$duplicate->id !== (int)($data['id'] ?? 0)) {
                $errors['agentid'] = get_string('agentalreadyconfigured', 'local_fredkichat', format_string($duplicate->name));
            }
        }
        return $errors;
    }
}
