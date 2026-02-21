-- Migration 007: Cron Run Tracking
-- Creates the cron_runs table used by CronJob::dbInsertStart/dbUpdateFinish
-- to track heartbeat, duration, and output of every scheduled job.
-- Run: php database/run_migration.php

CREATE TABLE IF NOT EXISTS cron_runs (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    job_name           VARCHAR(80)  NOT NULL,
    status             ENUM('running','success','failed') NOT NULL DEFAULT 'running',
    started_at         DATETIME     NOT NULL,
    finished_at        DATETIME,
    duration_ms        INT          UNSIGNED,
    records_processed  INT          UNSIGNED NOT NULL DEFAULT 0,
    output             TEXT,
    INDEX idx_job_start (job_name, started_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
