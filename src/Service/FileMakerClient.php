<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class FileMakerClient
{
    private bool $enabled = true;
    private ?string $token = null;
    private string $base;
    private int $retries = 2; // Anzahl Retries bei 5xx/429
    private ?CacheInterface $cache = null;
    private ?SessionInterface $session = null;
    private string $cacheKey = 'fm_token';
    private string $sessionKey = 'fm_token';
    // Request-local memoization to avoid duplicate identical FM calls in the
    // same request/process. Keyed by method + params.
    private array $memo = [];

    // Simple instrumentation counters (incremented per method call).
    private array $counters = [
        'find' => ['calls' => 0, 'cache_hits' => 0],
        'list' => ['calls' => 0, 'cache_hits' => 0],
        'getRecord' => ['calls' => 0, 'cache_hits' => 0],
    ];

    /**
     * Constructor accepts nullable FM connection settings. If values are not
     * provided via DI we attempt to read them from environment variables.
     * When required settings are missing the client is disabled (non-fatal)
     * and returns empty responses for read operations to avoid hard 500s.
     */
    public function __construct(?string $host = null, private ?string $db = null, private ?string $user = null, private ?string $pass = null, ?CacheInterface $cache = null, ?SessionInterface $session = null)
    {
        $this->cache = $cache;
        $this->session = $session;

        // If DI did not provide scalar values, try environment fallbacks.
        $host = $host ?? (getenv('FM_HOST') ?: ($_ENV['FM_HOST'] ?? null));
        $this->db = $this->db ?? (getenv('FM_DB') ?: ($_ENV['FM_DB'] ?? null));
        $this->user = $this->user ?? (getenv('FM_USER') ?: ($_ENV['FM_USER'] ?? null));
        $this->pass = $this->pass ?? (getenv('FM_PASS') ?: ($_ENV['FM_PASS'] ?? null));

        if (null === $host || null === $this->db || null === $this->user || null === $this->pass) {
            // Missing configuration -> disable the client but don't throw during container boot.
            $this->enabled = false;
            try {
                @file_put_contents(__DIR__.'/../../var/log/fm-missing-env.log', date('c')." FileMakerClient disabled: missing FM env vars\n", FILE_APPEND | LOCK_EX);
            } catch (\Throwable $e) {
                // ignore logging failures
            }
            // Keep base empty to avoid accidental URL building
            $this->base = '';
            return;
        }

        $h = rtrim($host, '/');
        if (false === stripos($h, '/fmi/')) {
            $h .= '/fmi/data/vLatest';
        }
        $this->base = $h;
    }

    private function url(string $path): string
    {
        $db = rawurlencode($this->db);

        return strtr($this->base.$path, ['{db}' => $db]);
    }

    private function layoutUrl(string $layout, string $suffix = ''): string
    {
        $db = rawurlencode($this->db);
        $lay = rawurlencode($layout);

        return "{$this->base}/databases/{$db}/layouts/{$lay}{$suffix}";
    }

    private function httpRequest(string $method, string $url, array $options = []): array
    {
        if (!$this->enabled) {
            throw new \RuntimeException('FileMaker client is disabled: missing FM_* configuration');
        }
        $attempt = 0;

        start:
        $attempt++;

        $ch = curl_init();

        // Basis-Header
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: CHEERCITY-Symfony-FMClient/1.0',
        ];

        // Bearer nur setzen, wenn kein Basic-Auth übergeben ist
        if ($this->token && !isset($options['auth_basic'])) {
            $headers[] = 'Authorization: Bearer '.$this->token;
        }

        // Zusätzliche Header mergen (optional)
        if (!empty($options['headers']) && is_array($options['headers'])) {
            $headers = array_values(array_unique(array_merge($headers, $options['headers'])));
        }

        // DEBUG: persist computed headers (redact sensitive values)
        try {
            $dbgHeaders = [];
            foreach ($headers as $h) {
                if (0 === stripos($h, 'authorization:')) {
                    // redact token/credentials
                    $parts = explode(':', $h, 2);
                    $scheme = trim($parts[0]);
                    $dbgHeaders[] = $scheme.': REDACTED';
                } else {
                    $dbgHeaders[] = $h;
                }
            }
            // also note if auth_basic is used (passwords are not logged)
            if (isset($options['auth_basic'])) {
                $dbgHeaders[] = 'X-FM-DBG-Auth-Basic: present';
            }
            $legacyPath = __DIR__.'/../../var/log/legacy-debug-fm.log';
            @file_put_contents($legacyPath, date('c')." FM REQUEST HEADERS: URL={$url} HEADERS=".json_encode($dbgHeaders).PHP_EOL, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {
            // ignore logging failures
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true, // wichtig!
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        ]);

        if (isset($options['auth_basic'])) {
            curl_setopt($ch, CURLOPT_USERPWD, $options['auth_basic'][0].':'.$options['auth_basic'][1]);
        }

        if (isset($options['json'])) {
            $json = json_encode($options['json'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        }

        // DEBUG: persist request payload (temporary) to help diagnose FM API errors
        try {
            if (defined('JSON_PRETTY_PRINT')) {
                $dbgJson = json_encode($options['json'] ?? new \stdClass(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            } else {
                $dbgJson = json_encode($options['json'] ?? new \stdClass(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            $legacyPath = __DIR__.'/../../var/log/legacy-debug-fm.log';
            @file_put_contents($legacyPath, date('c')." FM REQUEST: METHOD={$method} URL={$url} PAYLOAD=".$dbgJson.PHP_EOL, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {
            // ignore logging failures
        }

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            throw new \RuntimeException("cURL error: {$curlErr} (URL: {$url})");
        }
        if (false === $response || null === $response || '' === $response) {
            throw new \RuntimeException("Empty response from FileMaker (HTTP {$httpCode}) at {$url}");
        }

        $data = json_decode($response, true);

        // DEBUG: persist raw response for inspection
        try {
            $legacyPath = __DIR__.'/../../var/log/legacy-debug-fm.log';
            @file_put_contents($legacyPath, date('c')." FM RESPONSE: HTTP={$httpCode} BODY=".substr($response, 0, 4000).PHP_EOL, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {
            // ignore
        }
        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new \RuntimeException("Invalid JSON (HTTP {$httpCode}) at {$url}: ".substr($response, 0, 800));
        }

        // Transiente Fehler: 429/5xx -> Retry mit einfachem Backoff
        if (in_array($httpCode, [429, 500, 502, 503, 504], true) && $attempt <= $this->retries + 1) {
            usleep(200000 * $attempt); // 0.2s, 0.4s, ...
            goto start;
        }

        return ['data' => $data, 'status_code' => $httpCode];
    }

    public function ensureToken(): void
    {
        if (!$this->enabled) {
            // Client disabled due to missing FM_* config — do nothing.
            return;
        }
        // If we already have a token in memory, nothing to do
        if ($this->token) {
            return;
        }

        // Prefer shared cache (application/service account) when available
        if ($this->cache) {
            try {
                $token = $this->cache->get($this->cacheKey, function (ItemInterface $item) {
                    // Ask FM for a token and store it with a TTL
                    $res = $this->httpRequest('POST', $this->url('/databases/{db}/sessions'), [
                        'auth_basic' => [$this->user, $this->pass],
                        'json' => (object) [],
                    ]);
                    $data = $res['data'];
                    $code = (string) ($data['messages'][0]['code'] ?? '');
                    if ('0' !== $code) {
                        throw new \RuntimeException('FM auth failed: '.json_encode($data));
                    }
                    $tok = $data['response']['token'] ?? null;
                    if (!$tok) {
                        throw new \RuntimeException('No token in auth response: '.json_encode($data));
                    }
                    // TTL: 30 minutes (adjust as needed)
                    $item->expiresAfter(1800);
                    return $tok;
                });

                if (!empty($token) && is_string($token)) {
                    $this->token = $token;
                    return;
                }
            } catch (\Throwable $e) {
                // Cache or auth via cache failed – fallback to session/direct auth
            }
        }

        // Session fallback (per-request/user) when available
        if ($this->session && $this->session->has($this->sessionKey)) {
            $stored = $this->session->get($this->sessionKey);
            if (!empty($stored) && is_string($stored)) {
                $this->token = $stored;
                return;
            }
        }

        // Last resort: direct auth
        $result = $this->httpRequest('POST', $this->url('/databases/{db}/sessions'), [
            'auth_basic' => [$this->user, $this->pass],
            'json' => (object) [], // leeres Objekt für FM
        ]);

        $data = $result['data'];
        $code = (string) ($data['messages'][0]['code'] ?? '');
        if ('0' !== $code) {
            throw new \RuntimeException('FM auth failed: '.json_encode($data));
        }

        $this->token = $data['response']['token'] ?? null;
        if (!$this->token) {
            throw new \RuntimeException('No token in auth response: '.json_encode($data));
        }

        // Persist token to session (best-effort)
        if ($this->session) {
            try {
                $this->session->set($this->sessionKey, $this->token);
            } catch (\Throwable $e) {
                // ignore session write failures
            }
        }

        // Persist token to cache (best-effort)
        if ($this->cache) {
            try {
                $tok = $this->token;
                $this->cache->get($this->cacheKey, function (ItemInterface $item) use ($tok) {
                    $item->expiresAfter(1800);
                    return $tok;
                });
            } catch (\Throwable $e) {
                // ignore cache write failures
            }
        }
    }

    public function logout(): void
    {
        if (!$this->token) {
            return;
        }
        try {
            $this->httpRequest('DELETE', $this->url("/databases/{db}/sessions/{$this->token}"));
        } catch (\Throwable) {
            // bewusst schlucken – Logout soll nie hart failen
        } finally {
            // Remove token from memory and from session when available
            $this->token = null;
            if ($this->session && $this->session->has($this->sessionKey)) {
                try {
                    $this->session->remove($this->sessionKey);
                } catch (\Throwable) {
                    // ignore session removal failures
                }
            }
            if ($this->cache) {
                try {
                    $this->cache->delete($this->cacheKey);
                } catch (\Throwable) {
                    // ignore cache failures
                }
            }
        }
    }

    public function __destruct()
    {
        // Optional automatischer Logout (kann man auch weglassen, wenn man Token wiederverwenden will)
        // $this->logout();
    }

    /**
     * Einfacher Find-Wrapper.
     * $query: assoziatives Array (einzelner Request) ODER Array von Arrays (Compound Find)
     * $opts:  z.B. ['sort' => [['fieldName'=>'Name','sortOrder'=>'ascend']], 'limit'=>50, 'offset'=>1].
     */
    public function find(string $layout, array $query = [], array $opts = []): array
    {
        $this->counters['find']['calls']++;

        // If the client was disabled (no FM config), return empty FM response shape
        if (!$this->enabled) {
            return ['response' => ['data' => []], 'messages' => [['code' => '401']]];
        }

        $this->ensureToken();

        // Memoization key for identical find requests within the same request
        $mkey = 'find:'.md5($layout.'|'.json_encode($query, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).'|'.json_encode($opts, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
        if (array_key_exists($mkey, $this->memo)) {
            $this->counters['find']['cache_hits']++;
            return $this->memo[$mkey];
        }

        // compound vs. single
        $body = [];
        if ([] !== $query) {
            $body['query'] = isset($query[0]) && is_array($query[0]) ? $query : [$query];
        }
        if ($opts) {
            $body = array_replace($body, $opts);
        }

        $jsonBody = [] === $body ? (object) [] : $body;

        // FileMaker Data API expects the _find action on the layout path (not under /records).
        // Correct endpoint: /databases/{db}/layouts/{layout}/_find
    $result = $this->httpRequest('POST', $this->layoutUrl($layout, '/_find'), ['json' => $jsonBody]);
        $data = $result['data'];
        $code = (string) ($data['messages'][0]['code'] ?? '');

        // 952 = Token invalid/expired -> invalidate shared cache, reset token + Retry
        if ('952' === $code) {
            // remove cached token so subsequent ensureToken() will re-auth
            if ($this->cache) {
                try {
                    $this->cache->delete($this->cacheKey);
                } catch (\Throwable) {
                    // ignore cache failures
                }
            }

            $this->token = null;

            return $this->find($layout, $query, $opts);
        }

        // 401 = No records match -> leere Ergebnismenge zurückgeben (konform zur FM-API)
        if ('401' === $code) {
            return ['response' => ['data' => []], 'messages' => $data['messages']];
        }

        if ('0' !== $code) {
            throw new \RuntimeException('FM find failed: '.json_encode($data));
        }

        // Store in request-local memo and return
        $this->memo[$mkey] = $data;
        return $data; // vollständige Response
    }

    // Bonus: einfache Convenience-Methoden
    public function list(string $layout, int $limit = 100, int $offset = 1, array $sort = []): array
    {
        $this->counters['list']['calls']++;

        if (!$this->enabled) {
            return ['response' => ['data' => []], 'messages' => [['code' => '401']]];
        }

        $this->ensureToken();

        $params = ['_limit' => $limit, '_offset' => $offset];
        if ($sort) {
            // TODO: Implement sort for GET requests if needed
        }
        $queryString = http_build_query($params);
        $url = $this->layoutUrl($layout, '/records').'?'.$queryString;

        $mkey = 'list:'.md5($url);
        if (array_key_exists($mkey, $this->memo)) {
            $this->counters['list']['cache_hits']++;
            return $this->memo[$mkey];
        }

        $result = $this->httpRequest('GET', $url);
        $data = $result['data'];

        // 952 = Token invalid/expired -> invalidate shared cache, Token reset + Retry
        if (($data['messages'][0]['code'] ?? null) === '952') {
            if ($this->cache) {
                try {
                    $this->cache->delete($this->cacheKey);
                } catch (\Throwable) {
                    // ignore cache failures
                }
            }

            $this->token = null;

            return $this->list($layout, $limit, $offset, $sort);
        }

        // 401 = No records match -> leere Ergebnismenge zurückgeben
        if (($data['messages'][0]['code'] ?? null) === '401') {
            return ['response' => ['data' => []], 'messages' => $data['messages']];
        }

        if (($data['messages'][0]['code'] ?? null) !== '0') {
            throw new \RuntimeException('FM list failed: '.json_encode($data));
        }

        $this->memo[$mkey] = $data;
        return $data; // vollständige Response
    }

    /**
     * GET a single record by FileMaker recordId.
     * Returns the full FileMaker response array.
     */
    public function getRecord(string $layout, string $recordId): array
    {
        $this->counters['getRecord']['calls']++;

        if (!$this->enabled) {
            return ['response' => ['data' => []], 'messages' => [['code' => '401']]];
        }

        $this->ensureToken();

        $url = $this->layoutUrl($layout, '/records/'.rawurlencode((string) $recordId));

        $mkey = 'getRecord:'.md5($url);
        if (array_key_exists($mkey, $this->memo)) {
            $this->counters['getRecord']['cache_hits']++;
            return $this->memo[$mkey];
        }

        $result = $this->httpRequest('GET', $url);
        $data = $result['data'];

        // 952 -> token expired -> invalidate cache and retry
        if (($data['messages'][0]['code'] ?? null) === '952') {
            if ($this->cache) {
                try {
                    $this->cache->delete($this->cacheKey);
                } catch (\Throwable) {
                    // ignore cache failures
                }
            }

            $this->token = null;

            return $this->getRecord($layout, $recordId);
        }

        // 401 -> no record (return empty response shape)
        if (($data['messages'][0]['code'] ?? null) === '401') {
            return ['response' => ['data' => []], 'messages' => $data['messages']];
        }

        if (($data['messages'][0]['code'] ?? null) !== '0') {
            throw new \RuntimeException('FM getRecord failed: '.json_encode($data));
        }

        $this->memo[$mkey] = $data;
        return $data;
    }

    public function create(string $layout, array $fieldData): array
    {
        if (!$this->enabled) {
            throw new \RuntimeException('FileMaker client is disabled: cannot create records');
        }
        $this->ensureToken();
        $res = $this->httpRequest('POST', $this->layoutUrl($layout, '/records'), [
            'json' => ['fieldData' => $fieldData],
        ]);
        $data = $res['data'];
        $code = (string) ($data['messages'][0]['code'] ?? '');
        if ('0' !== $code) {
            throw new \RuntimeException('FM create failed: '.json_encode($data));
        }

        return $data;
    }

    /**
     * Expose instrumentation counters for tests / perf scripts.
     * Returns an array like ['find'=>['calls'=>N,'cache_hits'=>M], ...]
     */
    public function getCounters(): array
    {
        return $this->counters;
    }

    /**
     * Reset request-local memo and counters (useful between perf runs).
     */
    public function resetInstrumentation(): void
    {
        $this->memo = [];
        $this->counters = [
            'find' => ['calls' => 0, 'cache_hits' => 0],
            'list' => ['calls' => 0, 'cache_hits' => 0],
            'getRecord' => ['calls' => 0, 'cache_hits' => 0],
        ];
    }
}

