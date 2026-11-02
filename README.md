# Kerio Report

PHP 8.2 / Apache / MySQL reporting application for Kerio Control 9.6.1.

## Current integration findings

- JSON-RPC endpoint: `https://KERIO_HOST:4081/admin/api/jsonrpc/`
- Active hosts: `ActiveHosts.get`
- Logs: `Logs.get` with `logName=connection|http|web`, `fromLine`, and `countLines`
- The tested `web` log contains HTTP URLs and source IPs. HTTPS page paths are generally not visible without TLS inspection.
- Connection log records include source/destination, protocol, duration and byte counters. Byte direction must be verified before mapping to upload/download fields.
- Active-host counters are cumulative snapshots, so polling and delta handling are required.

## Setup

1. Install XAMPP with PHP 8.2 Thread Safe x64 and enable `sqlsrv` only if separately required by other applications. Kerio reporting itself uses MySQL.
2. Copy this repository to `D:\\xampp\\htdocs\\kerio-report`.
3. Import `database/schema.sql` into MySQL.
4. Copy `config.example.php` to `config.local.php` and set local database and Kerio credentials. Do not commit the local file.
5. Configure Apache to use port 8080 and open `http://localhost:8080/kerio-report/public/`.

## Security

- Keep `config.local.php` out of Git.
- Use a trusted Kerio TLS certificate and keep certificate verification enabled.
- Restrict access to the application to trusted administrators.
