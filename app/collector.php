<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

final class KerioApiClient
{
    private string $url;
    private string $username;
    private string $password;
    private string $application;
    private bool $verifyTls;
    private $curlHandle;

    public function __construct(array $settings)
    {
        $this->url = (string)($settings['url'] ?? '');
        $this->username = (string)($settings['username'] ?? '');
        $this->password = (string)($settings['password'] ?? '');
        $this->application = (string)($settings['application'] ?? 'KerioReport');
        $this->verifyTls = (bool)($settings['verify_tls'] ?? true);

        if ($this->url === '' || $this->username === '' || $this->password === '') {
            throw new RuntimeException('Kerio URL, username and password must be configured.');
        }
        if (!extension_loaded('curl')) {
            throw new RuntimeException('PHP cURL extension is required.');
        }
        $this->curlHandle = curl_init($this->url);
        curl_setopt($this->curlHandle, CURLOPT_COOKIEFILE, '');
    }

    private function request(string $method, array $params, ?string $token = null): array
    {
        $payload = json_encode([
            'jsonrpc' => '2.0',
            'id' => random_int(1, PHP_INT_MAX),
            'method' => $method,
            'params' => $params,
        ], JSON_THROW_ON_ERROR);

        $headers = ['Content-Type: application/json'];
        if ($token !== null) {
            $headers[] = 'X-Token: ' . $token;
        }

        $ch = $this->curlHandle;
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_SSL_VERIFYPEER => $this->verifyTls,
            CURLOPT_SSL_VERIFYHOST => $this->verifyTls ? 2 : 0,
        ]);

        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        if ($body === false) {
            throw new RuntimeException('Kerio connection failed: ' . $error);
        }
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Kerio returned invalid JSON (HTTP ' . $status . ').');
        }
        if (isset($decoded['error'])) {
            $message = (string)($decoded['error']['message'] ?? 'Unknown JSON-RPC error');
            throw new RuntimeException('Kerio ' . $method . ' failed: ' . $message);
        }
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException('Kerio returned HTTP ' . $status . '.');
        }
        return $decoded;
    }

    public function getLogBatch(string $logName, int $fromLine, int $count): array
    {
        if (!in_array($logName, ['connection', 'http'], true)) {
            throw new InvalidArgumentException('Unsupported Kerio log type.');
        }
        $login = $this->request('Session.login', [
            'userName' => $this->username,
            'password' => $this->password,
            'application' => [
                'name' => $this->application,
                'vendor' => 'Kerio',
                'version' => '1.0',
            ],
        ]);
        $token = $login['result']['token'] ?? null;
        if (!is_string($token) || $token === '') {
            throw new RuntimeException('Kerio login response did not contain a session token.');
        }
        $count = max(1, min(50000, $count));
        $response = $this->request('Logs.get', [
            'logName' => $logName,
            'fromLine' => $fromLine,
            'countLines' => $count,
        ], $token);
        $result = $response['result'] ?? [];
        $rows = $result['viewport'] ?? [];
        if (!is_array($rows)) {
            throw new RuntimeException('Kerio Logs.get returned an unexpected viewport structure.');
        }
        $lines = [];
        foreach ($rows as $row) {
            if (is_array($row) && isset($row['content'])) {
                $lines[] = (string)$row['content'];
            } elseif (is_string($row)) {
                $lines[] = $row;
            }
        }
        return [
            'log' => $logName,
            'from_line' => $fromLine,
            'total_items' => (int)($result['totalItems'] ?? 0),
            'returned' => count($lines),
            'lines' => $lines,
            'raw_result_keys' => array_keys($result),
        ];
    }

    public function getLogTail(string $logName, int $count = 5): array
    {
        return $this->getLogBatch($logName, -1, max(1, min(50, $count)));
    }

    public function __destruct()
    {
        if ($this->curlHandle) {
            curl_close($this->curlHandle);
        }
    }

    public function getActiveHosts(): array
    {
        $login = $this->request('Session.login', [
            'userName' => $this->username,
            'password' => $this->password,
            'application' => [
                'name' => $this->application,
                'vendor' => 'Kerio',
                'version' => '1.0',
            ],
        ]);

        $token = $login['result']['token'] ?? null;
        if (!is_string($token) || $token === '') {
            throw new RuntimeException('Kerio login response did not contain a session token.');
        }

        $response = $this->request('ActiveHosts.get', [
            'query' => ['limit' => 2000, 'start' => 0],
            'refresh' => true,
        ], $token);

        $hosts = $this->findHostList($response['result'] ?? []);
        if ($hosts === null) {
            throw new RuntimeException('ActiveHosts.get succeeded, but its host-list structure was not recognized.');
        }
        return $hosts;
    }

    private function findHostList(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }
        if ($value !== [] && array_is_list($value)) {
            $first = $value[0] ?? null;
            if (is_array($first) && (isset($first['ip']) || isset($first['ipAddressFromDHCP']) || isset($first['macAddress']))) {
                return $value;
            }
        }
        foreach ($value as $child) {
            if (is_array($child)) {
                $found = $this->findHostList($child);
                if ($found !== null) {
                    return $found;
                }
            }
        }
        return null;
    }
}

function collectActiveHosts(): array
{
    global $config;
    $client = new KerioApiClient($config['kerio'] ?? []);
    $hosts = $client->getActiveHosts();
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $find = $pdo->prepare(
            'SELECT id FROM devices WHERE ip_address = ? AND
             (mac_address = ? OR (mac_address IS NULL AND ? IS NULL))
             LIMIT 1'
        );
        $insert = $pdo->prepare(
            'INSERT INTO devices (mac_address, ip_address, hostname, first_seen, last_seen, is_active)
             VALUES (:mac, :ip, :hostname, NOW(), NOW(), 1)'
        );
        $update = $pdo->prepare(
            'UPDATE devices SET hostname = :hostname, last_seen = NOW(), is_active = 1,
             mac_address = COALESCE(:mac, mac_address) WHERE id = :id'
        );

        foreach ($hosts as $host) {
            $ip = trim((string)($host['ip'] ?? $host['ipAddressFromDHCP'] ?? ''));
            if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
                continue;
            }
            $mac = trim((string)($host['macAddress'] ?? ''));
            $mac = $mac !== '' ? $mac : null;
            $hostname = trim((string)($host['hostname'] ?? ''));
            $hostname = $hostname !== '' ? $hostname : null;

            $find->execute([$ip, $mac, $mac]);
            $id = $find->fetchColumn();
            if ($id !== false) {
                $update->execute(['hostname' => $hostname, 'mac' => $mac, 'id' => $id]);
            } else {
                $insert->execute(['mac' => $mac, 'ip' => $ip, 'hostname' => $hostname]);
            }
        }

        $pdo->prepare('UPDATE devices SET is_active = 0')->execute();
        // Mark the hosts returned by this successful snapshot as active again.
        $mark = $pdo->prepare('UPDATE devices SET is_active = 1, last_seen = NOW() WHERE ip_address = ?');
        foreach ($hosts as $host) {
            $ip = trim((string)($host['ip'] ?? $host['ipAddressFromDHCP'] ?? ''));
            if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                $mark->execute([$ip]);
            }
        }
        $pdo->prepare('UPDATE sync_state SET last_active_hosts_sync = NOW() WHERE id = 1')->execute();
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    return ['received' => count($hosts), 'stored' => count($hosts)];
}
