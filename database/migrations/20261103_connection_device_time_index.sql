-- Speed up per-device traffic aggregation over a selected time range.
-- Run once against an existing kerio_report database.
ALTER TABLE connection_logs
  ADD INDEX idx_connection_device_time_bytes (device_id, occurred_at, bytes_total);
