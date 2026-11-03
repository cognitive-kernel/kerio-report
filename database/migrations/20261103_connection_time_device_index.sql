-- Add a date-leading covering index for date-range consumer aggregation.
-- Run once against an existing kerio_report database.
ALTER TABLE connection_logs
  ADD INDEX idx_connection_time_device_bytes (occurred_at, device_id, bytes_total);
