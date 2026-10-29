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
 * Server-side client for the FredKI (MaxKB v2) management API.
 *
 * All requests use the FredKI service account configured in the plugin settings.
 * Credentials and the management token never leave the Moodle server.
 *
 * @package    local_fredkichat
 * @copyright  2026 lernsteine.com
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_client {
    /** @var string Login endpoint of the MaxKB v2 management API. */
    const LOGIN_PATH = '/admin/api/user/login';

    /** @var int Page size used when listing agents. */
    const PAGE_SIZE = 100;

    /** @var int Upper bound of pages read when listing agents. */
    const MAX_PAGES = 20;

    /** @var string Base URL of the FredKI instance. */
    private string $baseurl;

    /** @var string FredKI workspace id. */
    private string $workspace;

    /** @var string Service account user name. */
    private string $username;

    /** @var string Service account password. */
    private string $password;

    /** @var string|null Management token of the current request. */
    private ?string $token = null;

    /**
     * Constructor.
     *
     * @param string $baseurl Base URL of the FredKI instance.
     * @param string $workspace FredKI workspace id.
     * @param string $username Service account user name.
     * @param string $password Service account password.
     */
    public function __construct(string $baseurl, string $workspace, string $username, string $password) {
        $this->baseurl = rtrim($baseurl, '/');
        $this->workspace = $workspace !== '' ? $workspace : 'default';
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * Create a client from the plugin settings.
     *
     * @return self
     * @throws \moodle_exception When the connection is not configured.
     */
    public static function from_config(): self {
        $cfg = get_config('local_fredkichat');
        if (empty($cfg->host) || empty($cfg->username) || empty($cfg->password)) {
            throw new \moodle_exception('missingconfig', 'local_fredkichat');
        }
        return new self(
            (string)$cfg->host,
            (string)($cfg->workspace ?? 'default'),
            (string)$cfg->username,
            (string)$cfg->password
        );
    }

    /**
     * Whether the FredKI connection is configured.
     *
     * @return bool
     */
    public static function is_configured(): bool {
        $cfg = get_config('local_fredkichat');
        return !empty($cfg->host) && !empty($cfg->username) && !empty($cfg->password);
    }

    /**
     * Base URL of the FredKI instance.
     *
     * @return string
     */
    public function get_baseurl(): string {
        return $this->baseurl;
    }

    /**
     * List all agents (applications) visible to the service account.
     *
     * @return array[] List of ['id' => string, 'name' => string].
     */
    public function list_agents(): array {
        return $this->list_application_pages(null);
    }

    /**
     * List the agents of one FredKI folder.
     *
     * @param string $folderid FredKI folder id.
     * @return array[] List of ['id' => string, 'name' => string].
     */
    public function list_agents_in_folder(string $folderid): array {
        return $this->list_application_pages($folderid);
    }

    /**
     * Return the flattened agent folder tree visible to the service account.
     *
     * @return array[] List of ['id' => string, 'name' => string].
     */
    public function list_folders(): array {
        $data = $this->request('GET', '/admin/api/workspace/' . rawurlencode($this->workspace) . '/APPLICATION/folder');
        $result = [];
        $walk = function ($nodes) use (&$walk, &$result): void {
            if (!is_array($nodes)) {
                return;
            }
            foreach ($nodes as $node) {
                if (!is_array($node) || empty($node['id'])) {
                    continue;
                }
                $result[] = ['id' => (string)$node['id'], 'name' => trim((string)($node['name'] ?? ''))];
                if (!empty($node['children'])) {
                    $walk($node['children']);
                }
            }
        };
        $walk(isset($data['id']) ? [$data] : $data);
        return $result;
    }

    /**
     * Return the public access token of an agent.
     *
     * @param string $agentid FredKI agent id.
     * @return array ['token' => string, 'active' => bool]
     */
    public function get_access_token(string $agentid): array {
        $data = $this->request('GET', '/admin/api/workspace/' . rawurlencode($this->workspace) . '/application/' .
            rawurlencode($agentid) . '/access_token');
        return [
            'token' => trim((string)($data['access_token'] ?? '')),
            'active' => !array_key_exists('is_active', $data) || !empty($data['is_active']),
        ];
    }

    /**
     * Read the public greeting (prologue) of an agent. The system prompt is never read.
     *
     * @param string $agentid FredKI agent id.
     * @return string
     */
    public function get_agent_welcome(string $agentid): string {
        $data = $this->request('GET', '/admin/api/workspace/' . rawurlencode($this->workspace) . '/application/' .
            rawurlencode($agentid));
        return self::find_prologue($data);
    }

    /**
     * Find the prologue in simple and workflow agents.
     *
     * @param array $data Agent detail data.
     * @return string
     */
    private static function find_prologue(array $data): string {
        if (isset($data['prologue']) && is_scalar($data['prologue']) && trim((string)$data['prologue']) !== '') {
            return trim((string)$data['prologue']);
        }
        foreach ($data as $value) {
            if (is_array($value)) {
                $found = self::find_prologue($value);
                if ($found !== '') {
                    return $found;
                }
            }
        }
        return '';
    }

    /**
     * Read all pages of the agent list.
     *
     * @param string|null $folderid Optional folder filter.
     * @return array[]
     */
    private function list_application_pages(?string $folderid): array {
        $result = [];
        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            $path = '/admin/api/workspace/' . rawurlencode($this->workspace) . '/application/' . $page . '/' . self::PAGE_SIZE;
            if ($folderid !== null) {
                $path .= '?folder_id=' . rawurlencode($folderid);
            }
            $data = $this->request('GET', $path);
            $records = $data['records'] ?? [];
            if (!is_array($records)) {
                break;
            }
            foreach ($records as $record) {
                // Folder entries are returned alongside agents; only real agents are kept.
                if (
                    !is_array($record) || empty($record['id']) ||
                        (isset($record['resource_type']) && $record['resource_type'] !== 'application')
                ) {
                    continue;
                }
                $result[(string)$record['id']] = [
                    'id' => (string)$record['id'],
                    'name' => (string)($record['name'] ?? $record['id']),
                ];
            }
            $total = (int)($data['total'] ?? 0);
            if (count($records) < self::PAGE_SIZE || ($total > 0 && $page * self::PAGE_SIZE >= $total)) {
                break;
            }
        }
        $result = array_values($result);
        usort($result, static fn($a, $b) => strcasecmp($a['name'], $b['name']));
        return $result;
    }

    /**
     * Log in with the service account, reusing a cached token where possible.
     *
     * @param bool $force Ignore the cached token.
     * @return string
     */
    private function login(bool $force = false): string {
        $cache = \cache::make('local_fredkichat', 'admintoken');
        $key = sha1($this->baseurl . '|' . $this->username);
        if (!$force) {
            if ($this->token !== null) {
                return $this->token;
            }
            $cached = $cache->get($key);
            if (is_string($cached) && $cached !== '') {
                $this->token = $cached;
                return $cached;
            }
        }
        $data = $this->send('POST', self::LOGIN_PATH, ['username' => $this->username, 'password' => $this->password], null);
        $token = $data['token'] ?? null;
        if (!is_string($token) || $token === '') {
            throw new \moodle_exception('loginfailed', 'local_fredkichat');
        }
        $this->token = $token;
        $cache->set($key, $token);
        return $token;
    }

    /**
     * Authenticated request. A rejected cached token triggers exactly one fresh login.
     *
     * @param string $method HTTP method.
     * @param string $path API path.
     * @param array|null $payload JSON payload.
     * @return array Response data.
     */
    private function request(string $method, string $path, ?array $payload = null): array {
        try {
            return $this->send($method, $path, $payload, $this->login());
        } catch (unauthorised_exception $e) {
            return $this->send($method, $path, $payload, $this->login(true));
        }
    }

    /**
     * Send a request and unwrap the MaxKB result envelope.
     *
     * @param string $method HTTP method.
     * @param string $path API path.
     * @param array|null $payload JSON payload.
     * @param string|null $bearer Bearer token or null for unauthenticated requests.
     * @return array Response data.
     */
    private function send(string $method, string $path, ?array $payload, ?string $bearer): array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        $curl = new \curl();
        $headers = ['Accept: application/json', 'Content-Type: application/json'];
        if ($bearer !== null) {
            $headers[] = 'Authorization: Bearer ' . $bearer;
        }
        $curl->setHeader($headers);
        $options = ['CURLOPT_TIMEOUT' => 30, 'CURLOPT_CONNECTTIMEOUT' => 10];
        $url = $this->baseurl . $path;
        if ($method === 'GET') {
            $raw = $curl->get($url, [], $options);
        } else {
            $raw = $curl->post($url, json_encode($payload ?? []), $options);
        }
        if ($curl->get_errno()) {
            debugging('FredKI request failed: ' . $curl->error, DEBUG_DEVELOPER);
            throw new \moodle_exception('connectionfailed', 'local_fredkichat');
        }
        $status = (int)($curl->get_info()['http_code'] ?? 0);
        $data = json_decode((string)$raw, true);
        $code = is_array($data) && isset($data['code']) ? (int)$data['code'] : $status;
        if ($status === 401 || $code === 401) {
            throw new unauthorised_exception();
        }
        if ($status < 200 || $status >= 300 || !is_array($data) || !in_array($code, [0, 200], true)) {
            debugging(
                'FredKI API error on ' . $path . ': HTTP ' . $status . ' ' . substr((string)$raw, 0, 500),
                DEBUG_DEVELOPER
            );
            throw new \moodle_exception('apierror', 'local_fredkichat', '', $status);
        }
        $result = $data['data'] ?? [];
        return is_array($result) ? $result : ['value' => $result];
    }
}
