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
 * Chat client for the public FredKI (MaxKB v2) chat API of one agent.
 *
 * @package    local_fredkichat
 * @copyright  2026 lernsteine.com
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class client {
    /** @var string Base URL of the FredKI instance. */
    private string $host;

    /** @var string Public access token of the agent. */
    private string $accesstoken;

    /**
     * Constructor.
     *
     * @param string $host Base URL of the FredKI instance.
     * @param string $accesstoken Public access token of the agent.
     */
    public function __construct(string $host, string $accesstoken) {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        $this->host = rtrim($host, '/');
        $this->accesstoken = trim($accesstoken);
    }

    /**
     * Open an anonymous FredKI chat session.
     *
     * @return array ['bearer' => string, 'chatid' => string]
     */
    public function open_session(): array {
        $auth = $this->json_request('POST', '/chat/api/auth/anonymous', ['access_token' => $this->accesstoken], null);
        $bearer = is_string($auth) ? $auth : (string)($auth['token'] ?? '');
        if ($bearer === '') {
            throw new \moodle_exception('connectionfailed', 'local_fredkichat');
        }
        $open = $this->json_request('GET', '/chat/api/open', null, $bearer);
        $chatid = is_string($open) ? $open : (string)($open['chat_id'] ?? '');
        if ($chatid === '') {
            throw new \moodle_exception('connectionfailed', 'local_fredkichat');
        }
        return ['bearer' => $bearer, 'chatid' => $chatid];
    }

    /**
     * Stream a FredKI answer and forward only visible answer chunks.
     *
     * Reasoning and other internal fields of the upstream stream are intentionally ignored.
     *
     * @param string $bearer Anonymous chat bearer token.
     * @param string $chatid FredKI chat id.
     * @param string $message User message.
     * @param callable $onchunk Called with each visible content chunk.
     * @param callable $ondone Called with the record id and usage information at the end.
     */
    public function stream_message(string $bearer, string $chatid, string $message, callable $onchunk, callable $ondone): void {
        $payload = json_encode([
            'message' => $message,
            'stream' => true,
            're_chat' => false,
            'image_list' => [],
            'document_list' => [],
            'audio_list' => [],
            'video_list' => [],
            'other_list' => [],
            'form_data' => new \stdClass(),
        ]);

        $buffer = '';
        $recordid = '';
        $usage = [];
        $aborted = false;
        $writer = function ($ch, $chunk) use (&$buffer, &$recordid, &$usage, &$aborted, $onchunk) {
            if (connection_aborted()) {
                // Returning 0 aborts the upstream transfer when the browser has gone away.
                $aborted = true;
                return 0;
            }
            $buffer .= $chunk;
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = rtrim(substr($buffer, 0, $pos), "\r");
                $buffer = substr($buffer, $pos + 1);
                if (strpos($line, 'data:') !== 0) {
                    continue;
                }
                $json = trim(substr($line, 5));
                if ($json === '' || $json === '[DONE]') {
                    continue;
                }
                $event = json_decode($json, true);
                if (!is_array($event) || (($event['operate'] ?? false) !== true)) {
                    continue;
                }
                if (!empty($event['chat_record_id'])) {
                    $recordid = (string)$event['chat_record_id'];
                }
                if (!empty($event['usage']) && is_array($event['usage'])) {
                    $usage = $event['usage'];
                }
                $content = (string)($event['content'] ?? '');
                if ($content !== '') {
                    $onchunk($content, $event);
                }
            }
            return strlen($chunk);
        };

        // Moodle's curl class keeps proxy settings and the HTTP security blocklist in effect.
        $curl = new \curl();
        $curl->setHeader([
            'Content-Type: application/json',
            'Accept: text/event-stream, */*',
            'Authorization: Bearer ' . $bearer,
        ]);
        $curl->post($this->host . '/chat/api/chat_message/' . rawurlencode($chatid), $payload, [
            'CURLOPT_RETURNTRANSFER' => false,
            'CURLOPT_WRITEFUNCTION' => $writer,
            'CURLOPT_CONNECTTIMEOUT' => 15,
            'CURLOPT_TIMEOUT' => 180,
            'CURLOPT_BUFFERSIZE' => 1024,
            'CURLOPT_TCP_NODELAY' => true,
            'CURLOPT_HTTP_VERSION' => CURL_HTTP_VERSION_1_1,
        ]);

        if ($aborted) {
            return;
        }
        if ($curl->get_errno()) {
            debugging('FredKI stream failed: ' . $curl->error, DEBUG_DEVELOPER);
            throw new \moodle_exception('connectionfailed', 'local_fredkichat');
        }
        $status = (int)($curl->get_info()['http_code'] ?? 0);
        if ($status < 200 || $status >= 300) {
            throw new \moodle_exception('apierror', 'local_fredkichat', '', $status);
        }
        $ondone($recordid, $usage);
    }

    /**
     * Send a JSON request and unwrap the MaxKB result envelope.
     *
     * @param string $method HTTP method.
     * @param string $path API path.
     * @param array|null $payload JSON payload.
     * @param string|null $bearer Bearer token.
     * @return mixed The data part of the response.
     */
    private function json_request(string $method, string $path, ?array $payload, ?string $bearer) {
        $curl = new \curl();
        $headers = ['Content-Type: application/json', 'Accept: application/json'];
        if ($bearer !== null) {
            $headers[] = 'Authorization: Bearer ' . $bearer;
        }
        $curl->setHeader($headers);
        $options = ['CURLOPT_TIMEOUT' => 30, 'CURLOPT_CONNECTTIMEOUT' => 10];
        if ($method === 'GET') {
            $raw = $curl->get($this->host . $path, [], $options);
        } else {
            $raw = $curl->post($this->host . $path, json_encode($payload ?? []), $options);
        }
        if ($curl->get_errno()) {
            debugging('FredKI request failed: ' . $curl->error, DEBUG_DEVELOPER);
            throw new \moodle_exception('connectionfailed', 'local_fredkichat');
        }
        $data = json_decode((string)$raw, true);
        $status = (int)($curl->get_info()['http_code'] ?? 0);
        if ($status < 200 || $status >= 300 || !is_array($data) || (isset($data['code']) && (int)$data['code'] !== 200)) {
            debugging('FredKI chat API error on ' . $path . ': HTTP ' . $status, DEBUG_DEVELOPER);
            throw new \moodle_exception('apierror', 'local_fredkichat', '', $status);
        }
        return $data['data'] ?? null;
    }
}
