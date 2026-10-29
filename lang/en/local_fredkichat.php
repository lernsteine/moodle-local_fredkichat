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
 * English strings for local_fredkichat.
 *
 * @package    local_fredkichat
 * @category   string
 * @copyright  2026 lernsteine.com
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['accentcolor'] = 'Accent colour';
$string['actions'] = 'Actions';
$string['agent'] = 'FredKI agent';
$string['agent_help'] = 'The list is loaded from FredKI and only contains agents the configured FredKI service account can access. Each agent can have one Moodle design.';
$string['agentalreadyconfigured'] = 'This FredKI agent already has a Moodle design: “{$a}”.';
$string['agenttokenmissing'] = 'No active public access token was found for the selected FredKI agent. Enable public access for the agent in FredKI.';
$string['allowedall'] = 'Note: no domain restriction is configured. The embed code can be used on any website.';
$string['allowedcurrently'] = 'Allowed domains:<br>{$a}';
$string['alloweddomains'] = 'Allowed domains';
$string['alloweddomains_help'] = 'Optional: one domain per line, e.g. www.example.com or *.example.com. If left empty, the public embed code works on any domain. The check relies on information sent by the browser and is not a security boundary.';
$string['apierror'] = 'FredKI returned an error (HTTP {$a}).';
$string['backgroundcolor'] = 'Background colour';
$string['blockedcurrently'] = 'Blocked domains:<br>{$a}';
$string['blockeddomains'] = 'Blocked domains';
$string['blockeddomains_help'] = 'Optional: one domain per line, e.g. unwanted.example or *.unwanted.example. Blocked domains always take precedence over allowed domains.';
$string['bordercolor'] = 'Borders / separators';
$string['botform_desc'] = 'Choose a FredKI agent and configure the appearance of its chat window.';
$string['botmessagecolor'] = 'Bot messages';
$string['cachedef_admintoken'] = 'FredKI service account token';
$string['cachedef_agentmeta'] = 'FredKI agent greetings';
$string['cachedef_ratelimit'] = 'FredKI chat message counters';
$string['chathistory'] = 'Chat history';
$string['chatplaceholder'] = 'Type your question …';
$string['chooseagent'] = 'Select an agent';
$string['colors'] = 'Colours';
$string['confirmdelete'] = 'Do you really want to delete the chatbot design “{$a}”?';
$string['connection'] = 'FredKI connection';
$string['connection_desc'] = 'FredKI is a hosted AI service based on MaxKB. A FredKI service account is required. The credentials are only used on the Moodle server and are never sent to browsers. If FredKI runs in a private network, its host may need to be removed from the "cURL blocked hosts list" in the HTTP security settings.';
$string['connectionfailed'] = 'The connection to FredKI failed. Please try again later.';
$string['credentials_desc'] = 'User name of the FredKI service account. Only agents this account can access are offered.';
$string['currentselection'] = 'current selection';
$string['delete'] = 'Delete';
$string['deletebot'] = 'Delete chatbot design';
$string['deletechat'] = 'Delete chat';
$string['disabled'] = 'Off';
$string['displayname'] = 'Display name';
$string['displayname_help'] = 'Optional. Leave empty to use the FredKI agent name.';
$string['edit'] = 'Edit';
$string['editbot'] = 'Edit chatbot design';
$string['embedcode'] = 'Embed code for Moodle pages';
$string['embedcode_blockonly'] = 'This chatbot can only be used through course blocks such as “FredKI Flex Agent”. Enable “Available to all logged-in users” to get an embed code for other Moodle pages.';
$string['embedcode_desc'] = 'Paste this code into the HTML view of a Moodle text area. The permission to use trusted content may be required.';
$string['enabled'] = 'On';
$string['error'] = 'The answer could not be loaded.';
$string['expiredpublictoken'] = 'The chat session has expired. Please reload the page.';
$string['externaldisablednotice'] = 'External embedding is disabled for this chatbot. Enable “Allow external embedding” above and save.';
$string['externaldomainnotallowed'] = 'This website is not allowed to embed this chatbot.';
$string['externalembedcode'] = 'External embed code (e.g. WordPress)';
$string['externalembedcode_desc'] = 'Paste this script into a website, e.g. a WordPress “Custom HTML” block. The chat appears as a floating button. Visitors do not need a Moodle account.';
$string['externalembedding'] = 'External embedding';
$string['externalenabled'] = 'Allow external embedding';
$string['externalenabled_help'] = 'Allows anonymous visitors of other websites to use this chatbot. Messages are limited per visitor address by the message limit in the plugin settings. FredKI credentials stay on the Moodle server.';
$string['externalstatus'] = 'External';
$string['feedback'] = 'Feedback';
$string['feedbackurl'] = 'Feedback URL';
$string['footer'] = 'Footer';
$string['footercolor'] = 'Footer text';
$string['footertext'] = 'Notice text';
$string['footertext_default'] = 'AI-generated answers may contain errors.';
$string['fredkichat:manage'] = 'Manage FredKI chatbot designs';
$string['headercolor'] = 'Header colour';
$string['host'] = 'FredKI URL';
$string['host_desc'] = 'Base URL of the FredKI instance, without trailing slash.';
$string['hovercolor'] = 'Hover / active selection';
$string['imprint'] = 'Imprint';
$string['imprinturl'] = 'Imprint URL';
$string['invalidagent'] = 'The selected FredKI agent is not available.';
$string['invalidcolor'] = 'Please enter a colour in #RRGGBB format.';
$string['invalidmessage'] = 'The message is empty or too long.';
$string['invalidpublictoken'] = 'The chat session is invalid.';
$string['launcherclose'] = 'Close chat';
$string['launchermaximize'] = 'Enlarge chat';
$string['launcherminimize'] = 'Reduce chat';
$string['launcheropen'] = 'Open {$a}';
$string['limits'] = 'Message limits';
$string['limits_desc'] = 'Every message is answered by FredKI and counts towards your FredKI usage. The limits protect against misuse.';
$string['loginfailed'] = 'The login to FredKI failed. Please check the service account in the plugin settings.';
$string['logo'] = 'Logo / avatar';
$string['managebots'] = 'FredKI chatbot designs';
$string['managebots_desc'] = 'Give FredKI agents a Moodle design. Teachers select the agents for their courses with the “FredKI Flex Agent” block.';
$string['missingconfig'] = 'The FredKI connection is not configured yet.';
$string['name'] = 'Name';
$string['newbot'] = 'Create chatbot design';
$string['newchat'] = 'New chat';
$string['noagents'] = 'No FredKI agents could be loaded. Please check the FredKI connection in the plugin settings.';
$string['nobotaccess'] = 'You do not have access to this chatbot.';
$string['owner'] = 'Created by';
$string['password'] = 'FredKI password';
$string['pluginname'] = 'FredKI Chat';
$string['preview'] = 'Preview';
$string['preview_desc'] = 'The chat appears as a floating button in the bottom-right corner.';
$string['privacy'] = 'Privacy';
$string['privacy:metadata:bots'] = 'Chatbot designs configured by managers.';
$string['privacy:metadata:bots:name'] = 'Name of the chatbot design.';
$string['privacy:metadata:bots:owneruserid'] = 'The user who created the chatbot design.';
$string['privacy:metadata:bots:timecreated'] = 'Time the chatbot design was created.';
$string['privacy:metadata:bots:timemodified'] = 'Time the chatbot design was last changed.';
$string['privacy:metadata:fredki'] = 'Chat messages are sent to the configured FredKI instance to be answered. They are not stored in Moodle. No Moodle user identity is transmitted.';
$string['privacy:metadata:fredki:message'] = 'The message the user types into the chat.';
$string['privacyurl'] = 'Privacy notice URL';
$string['publicembednotavailable'] = 'External embedding is not available for this chatbot.';
$string['publickeyregenerated'] = 'The public embed key was regenerated. Previously distributed external embed codes no longer work.';
$string['publicratelimit'] = 'Messages per hour per external visitor';
$string['publicratelimit_desc'] = 'Maximum number of messages per hour and chatbot from one network address in external embeds. 0 means unlimited.';
$string['ratelimitexceeded'] = 'The message limit has been reached. Please try again later.';
$string['regeneratepublickey'] = 'Regenerate public embed key';
$string['send'] = 'Send';
$string['siteaccess'] = 'Available to all logged-in users';
$string['siteaccess_help'] = 'If enabled, every logged-in user can use this chatbot anywhere in Moodle, e.g. via the embed code. If disabled, the chatbot can only be used in courses whose “FredKI Flex Agent” block has selected it.';
$string['starterquestions'] = 'Example questions';
$string['statusline'] = 'Your AI assistant';
$string['stopanswer'] = 'Stop response';
$string['tablelabel'] = 'Table';
$string['task_cleanuppublicsessions'] = 'Remove expired FredKI sessions of external embeds';
$string['textcolor'] = 'Text colour';
$string['thinking'] = 'FredKI is thinking …';
$string['togglehistory'] = 'Open or close chat history';
$string['usermessagecolor'] = 'User messages';
$string['username'] = 'FredKI user name';
$string['userratelimit'] = 'Messages per hour per Moodle user';
$string['userratelimit_desc'] = 'Maximum number of messages per hour and chatbot for one logged-in user. 0 means unlimited.';
$string['welcometext'] = 'Hello! How can I help you?';
$string['workspace'] = 'FredKI workspace';
$string['workspace_desc'] = 'Workspace id in FredKI. Use “default” unless your FredKI provider tells you otherwise.';
