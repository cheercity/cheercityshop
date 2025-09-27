<?php
namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class FileMakerClient
{
    private ?string $token = null;
    private string $base; // fertige Base-URL wie https://host/fmi/data/vLatest

    public function __construct(
        private HttpClientInterface $http,
        string $host,
        private string $db,
        private string $user,
        private string $pass
    ) {
        $h = rtrim($host, '/');
        // Wenn bereits /fmi/... enthalten ist, nichts anhängen; sonst Standard anhängen
        if (stripos($h, '/fmi/') === false) {
            $h .= '/fmi/data/vLatest';
        }
        $this->base = $h;
    }

    private function url(string $path): string
    {
        // Datenbank/Layout sicher encodieren
        $db = rawurlencode($this->db);
        // $path erwartet Platzhalter {db} / {layout} wenn nötig
        return strtr($this->base . $path, [
            '{db}'     => $db,
        ]);
    }

    private function layoutUrl(string $layout, string $suffix = ''): string
    {
        $db = rawurlencode($this->db);
        $lay = rawurlencode($layout);
        return "{$this->base}/databases/{$db}/layouts/{$lay}{$suffix}";
    }

    private function ensureToken(): void
    {
        if ($this->token) {
            return;
        }

        $res = $this->http->request('POST', $this->url("/databases/{db}/sessions"), [
            'auth_basic' => [$this->user, $this->pass],
            'headers'    => [
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'json' => (object)[], // leeres JSON-Objekt
        ]);

        $data = $this->safeToArray($res, 'FM login');
        if (($data['messages'][0]['code'] ?? null) !== '0') {
            throw new \RuntimeException('FM login failed: ' . json_encode($data));
        }

        $this->token = $data['response']['token'] ?? null;
        if (!$this->token) {
            throw new \RuntimeException('FM token missing');
        }
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    public function find(string $layout, array $query, array $opts = []): array
    {
        $this->ensureToken();

        $res = $this->http->request('POST', $this->layoutUrl($layout, '/_find'), [
            'headers' => [
                'Authorization' => "Bearer {$this->token}",
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
            ],
            // Top-Level-Merge: query + optionale Keys wie limit/offset/sort
            'json' => ['query' => [$query]] + $opts,
        ]);

        // Bei 401 (abgelaufenes Token): einmal refreshen und retry
        if ($res->getStatusCode() === 401) {
            $this->token = null;
            $this->ensureToken();
            $res = $this->http->request('POST', $this->layoutUrl($layout, '/_find'), [
                'headers' => [
                    'Authorization' => "Bearer {$this->token}",
                    'Accept'        => 'application/json',
                    'Content-Type'  => 'application/json',
                ],
                'json' => ['query' => [$query]] + $opts,
            ]);
        }

        $data = $this->safeToArray($res, 'FM find', ['layout' => $layout, 'query' => $query, 'opts' => $opts]);

        // 952 = Token invalid → einmal erneuern und retry
        if (($data['messages'][0]['code'] ?? null) === '952') {
            $this->token = null;
            return $this->find($layout, $query, $opts);
        }

        if (($data['messages'][0]['code'] ?? null) !== '0') {
            throw new \RuntimeException('FM find failed: ' . json_encode($data));
        }

        return $data['response']['data'] ?? [];
    }

    // ----------------- helpers -----------------

    private function safeToArray($res, string $ctx, array $extra = []): array
    {
        try {
            return $res->toArray(false);
        } catch (\Throwable $e) {
            $raw    = $this->safeGet(fn() => $res->getContent(false), 'raw');
            $status = $this->safeGet(fn() => (string)$res->getStatusCode(), 'status');
            $hdrs   = $this->safeGet(fn() => $res->getHeaders(false), 'headers');

            $logPath = dirname(__DIR__, 2) . '/var/log/fm_debug.log';
            @file_put_contents(
                $logPath,
                sprintf(
                    "[%s] %s parse error: %s\nSTATUS: %s\nHEADERS: %s\nCTX: %s\nRAW: %s\n\n",
                    date('c'),
                    $ctx,
                    $e->getMessage(),
                    $status,
                    json_encode($hdrs),
                    json_encode($extra),
                    is_string($raw) ? $raw : json_encode($raw)
                ),
                FILE_APPEND
            );
            throw new \RuntimeException($ctx . ' response parse error: ' . $e->getMessage());
        }
    }

    private function safeGet(callable $fn, string $what)
    {
        try { return $fn(); } catch (\Throwable $e) { return "unable to get {$what}: ".$e->getMessage(); }
    }
}