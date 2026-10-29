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

namespace local_fredkichat;

/**
 * Helper functions for styled FredKI chatbots: access checks, public embeds and rate limits.
 *
 * @package    local_fredkichat
 * @copyright  2026 lernsteine.com
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bot_helper {
    /** @var string[] Colour fields of a chatbot design with their default values. */
    const COLOURS = [
        'headercolor' => '#A59480',
        'accentcolor' => '#9B8975',
        'backgroundcolor' => '#FCFBF9',
        'textcolor' => '#20222A',
        'botmessagecolor' => '#FFFFFF',
        'usermessagecolor' => '#DCEFFA',
        'bordercolor' => '#D6E6F2',
        'hovercolor' => '#E8F3FA',
        'footercolor' => '#6E7F8D',
    ];

    /**
     * URL of the stored logo of a chatbot.
     *
     * @param int $botid Chatbot id.
     * @return \moodle_url|null
     */
    public static function get_logo_url(int $botid): ?\moodle_url {
        $context = \context_system::instance();
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'local_fredkichat', 'logo', $botid, 'itemid, filepath, filename', false);
        if (!$files) {
            return null;
        }
        $file = reset($files);
        return \moodle_url::make_pluginfile_url(
            $context->id,
            'local_fredkichat',
            'logo',
            $botid,
            $file->get_filepath(),
            $file->get_filename()
        );
    }

    /**
     * Whether the current user may use a chatbot inside Moodle.
     *
     * Access is granted to chatbot managers, to every logged-in user when site-wide access is enabled
     * for the chatbot, and otherwise only through a block that has selected this chatbot and that
     * the user can see in an accessible course. Blocks take part by implementing the component
     * callback "local_fredkichat_allows_bot" in their lib.php.
     *
     * @param \stdClass $bot Chatbot record.
     * @param int $blockid Block instance id the chat is opened from, 0 if none.
     * @return bool
     */
    public static function can_use_bot(\stdClass $bot, int $blockid): bool {
        global $DB;
        if (!isloggedin()) {
            return false;
        }
        if (has_capability('local/fredkichat:manage', \context_system::instance())) {
            return true;
        }
        if (!empty($bot->siteaccess) && !isguestuser()) {
            return true;
        }
        if ($blockid <= 0) {
            return false;
        }
        $instance = $DB->get_record('block_instances', ['id' => $blockid]);
        if (!$instance) {
            return false;
        }
        $parent = \context::instance_by_id((int)$instance->parentcontextid, IGNORE_MISSING);
        $coursecontext = $parent ? $parent->get_course_context(false) : false;
        if (!$coursecontext) {
            return false;
        }
        $course = get_course((int)$coursecontext->instanceid);
        if (!can_access_course($course)) {
            return false;
        }
        if (!has_capability('moodle/block:view', \context_block::instance($blockid))) {
            return false;
        }
        return (bool)component_callback(
            'block_' . $instance->blockname,
            'local_fredkichat_allows_bot',
            [$instance, (int)$bot->id],
            false
        );
    }

    /**
     * Require access to a chatbot inside Moodle.
     *
     * @param \stdClass $bot Chatbot record.
     * @param int $blockid Block instance id the chat is opened from, 0 if none.
     * @throws \moodle_exception
     */
    public static function require_bot_access(\stdClass $bot, int $blockid): void {
        if (!self::can_use_bot($bot, $blockid)) {
            throw new \moodle_exception('nobotaccess', 'local_fredkichat');
        }
    }

    /**
     * Enforce the configured message limit for the current user or, for public embeds, client address.
     *
     * @param \stdClass $bot Chatbot record.
     * @param bool $ispublic Whether the request comes from a public embed.
     * @throws \moodle_exception When the limit is exceeded.
     */
    public static function check_rate_limit(\stdClass $bot, bool $ispublic): void {
        global $USER;
        $limit = (int)get_config('local_fredkichat', $ispublic ? 'publicratelimit' : 'userratelimit');
        if ($limit <= 0) {
            return;
        }
        $subject = $ispublic ? 'ip' . sha1((string)getremoteaddr()) : 'u' . (int)$USER->id;
        $key = $subject . '_' . (int)$bot->id;
        $cache = \cache::make('local_fredkichat', 'ratelimit');
        $now = time();
        $entry = $cache->get($key);
        if (!is_array($entry) || ($entry['start'] ?? 0) < $now - HOURSECS) {
            $entry = ['start' => $now, 'count' => 0];
        }
        if ($entry['count'] >= $limit) {
            throw new \moodle_exception('ratelimitexceeded', 'local_fredkichat');
        }
        $entry['count']++;
        $cache->set($key, $entry);
    }

    /**
     * Return the greeting of a chatbot, refreshed from FredKI at most every five minutes.
     *
     * @param \stdClass $bot Chatbot record.
     * @return string
     */
    public static function get_agent_welcome(\stdClass $bot): string {
        global $DB;
        $stored = trim((string)($bot->agentwelcome ?? ''));
        if (empty($bot->agentid) || !admin_client::is_configured()) {
            return $stored;
        }
        $cache = \cache::make('local_fredkichat', 'agentmeta');
        $key = 'welcome_' . sha1((string)$bot->agentid);
        $cached = $cache->get($key);
        if (is_string($cached)) {
            return $cached;
        }
        try {
            $fresh = trim(admin_client::from_config()->get_agent_welcome((string)$bot->agentid));
        } catch (\Throwable $e) {
            // Keep the stored greeting while FredKI is unavailable and do not cache the failure.
            return $stored;
        }
        // An empty greeting in FredKI is a valid value and replaces the stored one.
        $cache->set($key, $fresh);
        if ($fresh !== $stored) {
            $DB->set_field('local_fredkichat_bots', 'agentwelcome', $fresh, ['id' => $bot->id]);
        }
        return $fresh;
    }

    /**
     * Return all chatbots indexed by FredKI agent id. Public API for other FredKI plugins.
     *
     * @return \stdClass[]
     */
    public static function get_bots_by_agentid(): array {
        global $DB;
        $result = [];
        foreach ($DB->get_records('local_fredkichat_bots', null, 'name ASC') as $bot) {
            $agentid = trim((string)($bot->agentid ?? ''));
            if ($agentid !== '') {
                $result[$agentid] = $bot;
            }
        }
        return $result;
    }

    /**
     * Generate a new unique public embed key.
     *
     * @return string
     */
    public static function generate_public_key(): string {
        global $DB;
        do {
            $key = bin2hex(random_bytes(24));
        } while ($DB->record_exists('local_fredkichat_bots', ['publickey' => $key]));
        return $key;
    }

    /**
     * Return the chatbot for a public embed key if external embedding is enabled.
     *
     * @param string $key Public embed key.
     * @return \stdClass|null
     */
    public static function get_public_bot(string $key): ?\stdClass {
        global $DB;
        if ($key === '') {
            return null;
        }
        $bot = $DB->get_record('local_fredkichat_bots', ['publickey' => $key, 'externalenabled' => 1]);
        return $bot ?: null;
    }

    /**
     * Normalise a newline-separated list of domains.
     *
     * @param string $raw Raw input.
     * @return string
     */
    public static function normalise_domains(string $raw): string {
        $domains = [];
        foreach (preg_split('/\R/u', $raw) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $wildcard = str_starts_with($line, '*.');
            $candidate = $wildcard ? substr($line, 2) : $line;
            if (!preg_match('~^[a-z][a-z0-9+.-]*://~i', $candidate)) {
                $candidate = 'https://' . $candidate;
            }
            $host = parse_url($candidate, PHP_URL_HOST);
            if (!$host) {
                continue;
            }
            $host = strtolower(rtrim($host, '.'));
            if ($host !== '') {
                $domains[$wildcard ? '*.' . $host : $host] = true;
            }
        }
        return implode("\n", array_keys($domains));
    }

    /**
     * Whether a host matches an entry of a domain list.
     *
     * @param string $raw Newline-separated domain list.
     * @param string $host Host to test.
     * @return bool
     */
    private static function domain_list_matches(string $raw, string $host): bool {
        foreach (preg_split('/\R/u', $raw) as $entry) {
            $entry = strtolower(trim($entry));
            if ($entry === '') {
                continue;
            }
            if (str_starts_with($entry, '*.')) {
                $base = substr($entry, 2);
                if ($host !== $base && str_ends_with($host, '.' . $base)) {
                    return true;
                }
            } else if ($host === $entry) {
                return true;
            }
        }
        return false;
    }

    /**
     * Host of the embedding page as reported by the browser.
     *
     * This is a convenience restriction for browsers only, not an authentication mechanism.
     *
     * @return string
     */
    public static function request_origin_host(): string {
        foreach (['HTTP_ORIGIN', 'HTTP_REFERER'] as $header) {
            if (!empty($_SERVER[$header])) {
                $host = parse_url((string)$_SERVER[$header], PHP_URL_HOST);
                if ($host) {
                    return strtolower(rtrim($host, '.'));
                }
            }
        }
        return '';
    }

    /**
     * Whether the embedding domain is allowed for a public chatbot.
     *
     * @param \stdClass $bot Chatbot record.
     * @param string|null $requesthost Host to test, defaults to the request origin.
     * @return bool
     */
    public static function is_domain_allowed(\stdClass $bot, ?string $requesthost = null): bool {
        $allowedraw = trim((string)($bot->alloweddomains ?? ''));
        $blockedraw = trim((string)($bot->blockeddomains ?? ''));
        $host = strtolower(rtrim($requesthost ?? self::request_origin_host(), '.'));

        if ($host !== '' && $blockedraw !== '' && self::domain_list_matches($blockedraw, $host)) {
            return false;
        }
        if ($allowedraw === '') {
            return true;
        }
        return $host !== '' && self::domain_list_matches($allowedraw, $host);
    }

    /**
     * Require that the embedding domain is allowed.
     *
     * @param \stdClass $bot Chatbot record.
     * @throws \moodle_exception
     */
    public static function require_public_embed_domain(\stdClass $bot): void {
        if (!self::is_domain_allowed($bot)) {
            throw new \moodle_exception('externaldomainnotallowed', 'local_fredkichat');
        }
    }

    /**
     * Base64url encoding.
     *
     * @param string $value Raw value.
     * @return string
     */
    private static function base64url_encode(string $value): string {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /**
     * Base64url decoding.
     *
     * @param string $value Encoded value.
     * @return string|false
     */
    private static function base64url_decode(string $value): string|false {
        $padding = (4 - strlen($value) % 4) % 4;
        return base64_decode(strtr($value . str_repeat('=', $padding), '-_', '+/'), true);
    }

    /**
     * Secret used to sign public chat tokens.
     *
     * @return string
     */
    private static function signing_secret(): string {
        $secret = (string)get_config('local_fredkichat', 'publicsigningsecret');
        if ($secret === '') {
            $secret = bin2hex(random_bytes(32));
            set_config('publicsigningsecret', $secret, 'local_fredkichat');
        }
        return $secret;
    }

    /**
     * Create a signed, short-lived token for a public chat page.
     *
     * @param \stdClass $bot Chatbot record.
     * @return string
     */
    public static function create_public_token(\stdClass $bot): string {
        $claims = [
            'botid' => (int)$bot->id,
            'nonce' => bin2hex(random_bytes(16)),
            'exp' => time() + (12 * HOURSECS),
        ];
        $payload = self::base64url_encode(json_encode($claims, JSON_UNESCAPED_SLASHES));
        $signature = self::base64url_encode(hash_hmac('sha256', $payload, self::signing_secret(), true));
        return $payload . '.' . $signature;
    }

    /**
     * Verify a public chat token.
     *
     * @param string $token Token.
     * @return array Claims.
     * @throws \moodle_exception
     */
    public static function verify_public_token(string $token): array {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            throw new \moodle_exception('invalidpublictoken', 'local_fredkichat');
        }
        [$payload, $signature] = $parts;
        $expected = self::base64url_encode(hash_hmac('sha256', $payload, self::signing_secret(), true));
        if (!hash_equals($expected, $signature)) {
            throw new \moodle_exception('invalidpublictoken', 'local_fredkichat');
        }
        $decoded = self::base64url_decode($payload);
        $claims = $decoded !== false ? json_decode($decoded, true) : null;
        if (!is_array($claims) || empty($claims['botid']) || empty($claims['nonce']) || empty($claims['exp'])) {
            throw new \moodle_exception('invalidpublictoken', 'local_fredkichat');
        }
        if ((int)$claims['exp'] < time()) {
            throw new \moodle_exception('expiredpublictoken', 'local_fredkichat');
        }
        return $claims;
    }
}
