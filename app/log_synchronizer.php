<?php
declare(strict_types=1);

require_once __DIR__ . '/collector.php';

final class KerioLogSynchronizer
{
    private PDO $pdo;
    private KerioApiClient $api;

    public function __construct(PDO $pdo, KerioApiClient $api)
    {
        $this->pdo = $pdo;
        $this->api = $api;
    }

    public function sync(string $type, int $batchSize = 1000, int $maxBatches = 20): array
    {
        $cursorColumn = $type === 'connection' ? 'connection_cursor' : ($type === 'http' ? 'web_cursor' : null);
        $timeColumn = $type === 'connection' ? 'last_connection_sync' : ($type === 'http' ? 'last_web_sync' : null);
        if ($cursorColumn === null || $timeColumn === null) {
            throw new InvalidArgumentException('Unsupported log type.');
        }

        $state = $this->pdo->query('SELECT ' . $cursorColumn . ' AS cursor_value FROM sync_state WHERE id = 1')->fetch();
        if (!$state) {
            throw new RuntimeException('sync_state row id=1 is missing.');
        }
        $cursor = max(0, (int)$state['cursor_value']);
        $inserted = 0;
        $parsed = 0;
        $batches = 0;
        $totalItems = null;
        $reset = false;

        for (; $batches < $maxBatches; $batches++) {
            $batch = $this->api->getLogBatch($type, $cursor, $batchSize);
            $totalItems = $batch['total_items'];

            // Kerio may truncate/rotate a log. Restart at its first available line.
            if ($totalItems < $cursor) {
                $cursor = 0;
                $reset = true;
                $batch = $this->api->getLogBatch($type, $cursor, $batchSize);
                $totalItems = $batch['total_items'];
            }

            $lines = $batch['lines'];
            if (!$lines) {
                break;
            }

            $this->pdo->beginTransaction();
            try {
                foreach ($lines as $line) {
                    $record = $type === 'connection'
                        ? $this->parseConnectionLine($line)
                        : $this->parseHttpLine($line);
                    if ($record !== null) {
                        $parsed++;
                        $inserted += $type === 'connection'
                            ? $this->insertConnection($record)
                            : $this->insertWeb($record);
                    }
                }

                $cursor += count($lines);
                $update = $this->pdo->prepare(
                    'UPDATE sync_state SET ' . $cursorColumn . ' = ?, ' . $timeColumn . ' = NOW() WHERE id = 1'
                );
                $update->execute([$cursor]);
                $this->pdo->commit();
            } catch (Throwable $e) {
                $this->pdo->rollBack();
                throw $e;
            }

            if ($cursor >= $totalItems || count($lines) < $batchSize) {
                $batches++;
                break;
            }
        }

        return [
            'log' => $type,
            'cursor' => $cursor,
            'total_items' => $totalItems,
            'batches' => $batches,
            'parsed' => $parsed,
            'inserted' => $inserted,
            'rotation_detected' => $reset,
            'caught_up' => $totalItems !== null && $cursor >= $totalItems,
        ];
    }

    /**
     * Import the newest log window without moving the historical backfill cursor.
     * INSERT IGNORE and the unique Kerio log key make overlapping tail windows safe.
     */
    public function syncTail(string $type, int $count = 2000): array
    {
        if (!in_array($type, ['connection', 'http'], true)) {
            throw new InvalidArgumentException('Unsupported log type.');
        }

        $batch = $this->api->getLogTail($type, $count);
        $inserted = 0;
        $parsed = 0;
        $this->pdo->beginTransaction();
        try {
            foreach ($batch['lines'] as $line) {
                $record = $type === 'connection'
                    ? $this->parseConnectionLine($line)
                    : $this->parseHttpLine($line);
                if ($record === null) {
                    continue;
                }
                $parsed++;
                $inserted += $type === 'connection'
                    ? $this->insertConnection($record)
                    : $this->insertWeb($record);
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return [
            'log' => $type,
            'mode' => 'tail',
            'requested' => $count,
            'returned' => $batch['returned'],
            'total_items' => $batch['total_items'],
            'parsed' => $parsed,
            'inserted' => $inserted,
        ];
    }

    private function parseConnectionLine(string $line): ?array
    {
        if (!preg_match('/^\[(\d{2}\/\w{3}\/\d{4} \d{2}:\d{2}:\d{2})\]\s+\[ID\]\s+(\d+)/', $line, $head)) {
            return null;
        }
        $date = DateTime::createFromFormat('!d/M/Y H:i:s', $head[1]);
        if (!$date) return null;

        preg_match('/\[Rule\]\s+(.*?)(?=\s+\[Service\]|\s+\[Connection\])/', $line, $rule);
        preg_match('/\[Service\]\s+(.*?)(?=\s+\[Connection\])/', $line, $service);
        if (!preg_match('/\[Connection\]\s+(\S+)\s+(\S+)\s+->\s+(.*?)\s+\((\d{1,3}(?:\.\d{1,3}){3})\):(\d+)/', $line, $connection)) {
            if (!preg_match('/\[Connection\]\s+(\S+)\s+(\S+)\s+->\s+(\d{1,3}(?:\.\d{1,3}){3}):(\d+)/', $line, $simple)) {
                return null;
            }
            $protocol = $simple[1]; $source = $simple[2]; $destinationHost = null;
            $destinationIp = $simple[3]; $destinationPort = (int)$simple[4];
        } else {
            $protocol = $connection[1]; $source = $connection[2];
            $destinationHost = trim($connection[3]);
            $destinationIp = $connection[4]; $destinationPort = (int)$connection[5];
        }
        if (!preg_match('/^(\d{1,3}(?:\.\d{1,3}){3}):(\d+)$/', $source, $src)) return null;
        if (!filter_var($src[1], FILTER_VALIDATE_IP)) return null;

        preg_match('/\[Iface\]\s+(.*?)(?=\s+\[Duration\])/', $line, $iface);
        preg_match('/\[Duration\]\s+(\d+)\s+sec/', $line, $duration);
        preg_match('/\[Bytes\]\s+(\d+)\/(\d+)\/(\d+)/', $line, $bytes);

        $key = hash('sha256', 'connection|' . $head[1] . '|' . $head[2]);
        return [
            'key' => $key, 'occurred_at' => $date->format('Y-m-d H:i:s'),
            'connection_id' => $head[2], 'rule_name' => $rule[1] ?? null,
            'service_name' => $service[1] ?? null, 'protocol' => $protocol,
            'source_ip' => $src[1], 'source_port' => (int)$src[2],
            'destination_host' => $destinationHost, 'destination_ip' => $destinationIp,
            'destination_port' => $destinationPort, 'interface_name' => $iface[1] ?? null,
            'duration_seconds' => isset($duration[1]) ? (int)$duration[1] : null,
            'bytes_out' => isset($bytes[1]) ? (int)$bytes[1] : null,
            'bytes_in' => isset($bytes[2]) ? (int)$bytes[2] : null,
            'bytes_total' => isset($bytes[3]) ? (int)$bytes[3] : null,
            'raw_line' => $line,
        ];
    }

    private function parseHttpLine(string $line): ?array
    {
        if (!preg_match('/^(\S+)\s+\S+\s+\S+\s+\[([^\]]+)\]\s+"([A-Z]+)\s+(\S+)\s+HTTP\/[^"]+"\s+(\d{3})\s+(\d+)/', trim($line), $m)) {
            return null;
        }
        if (!filter_var($m[1], FILTER_VALIDATE_IP)) return null;
        $date = DateTime::createFromFormat('!d/M/Y:H:i:s O', $m[2]);
        if (!$date) $date = DateTime::createFromFormat('!d/M/Y H:i:s O', $m[2]);
        if (!$date) return null;

        $url = $m[4];
        $host = strtolower((string)parse_url($url, PHP_URL_HOST));
        $path = (string)parse_url($url, PHP_URL_PATH);
        // Exclude Kerio administration/API traffic from end-user browsing reports.
        if ($path !== '' && str_contains(strtolower($path), '/admin/api/')) return null;
        if ($host !== '' && (in_array($host, ['localhost', '127.0.0.1'], true) || filter_var($host, FILTER_VALIDATE_IP) && str_starts_with($host, '192.168.'))) {
            if (str_contains(strtolower($path), '/admin/')) return null;
        }

        return [
            'key' => hash('sha256', 'http|' . trim($line)),
            'occurred_at' => $date->format('Y-m-d H:i:s'),
            'source_ip' => $m[1], 'http_method' => $m[3], 'url' => $url,
            'http_status' => (int)$m[5], 'response_bytes' => (int)$m[6],
            'raw_line' => trim($line),
        ];
    }

    private function findDeviceId(string $ip): ?int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM devices WHERE ip_address = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$ip]);
        $id = $stmt->fetchColumn();
        return $id === false ? null : (int)$id;
    }

    private function insertConnection(array $r): int
    {
        $r['device_id'] = $this->findDeviceId($r['source_ip']);
        $sql = 'INSERT IGNORE INTO connection_logs
            (kerio_log_key,device_id,occurred_at,connection_id,rule_name,service_name,protocol,source_ip,source_port,destination_host,destination_ip,destination_port,interface_name,duration_seconds,bytes_in,bytes_out,bytes_total,raw_line)
            VALUES (:key,:device_id,:occurred_at,:connection_id,:rule_name,:service_name,:protocol,:source_ip,:source_port,:destination_host,:destination_ip,:destination_port,:interface_name,:duration_seconds,:bytes_in,:bytes_out,:bytes_total,:raw_line)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($r);
        return $stmt->rowCount();
    }

    private function insertWeb(array $r): int
    {
        $r['device_id'] = $this->findDeviceId($r['source_ip']);
        $sql = 'INSERT IGNORE INTO web_logs
            (kerio_log_key,device_id,occurred_at,source_ip,http_method,url,http_status,response_bytes,raw_line)
            VALUES (:key,:device_id,:occurred_at,:source_ip,:http_method,:url,:http_status,:response_bytes,:raw_line)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($r);
        return $stmt->rowCount();
    }
}
