<?php

declare(strict_types=1);

namespace psm\Service\System;

use psm\Service\Database;

final readonly class JobRunRepository
{
    public function __construct(private Database $database)
    {
    }

    public function start(string $jobName): int
    {
        $result = $this->database->save($this->table(), [
            'job_name' => mb_substr(preg_replace('/[^a-z0-9_-]/i', '', $jobName) ?? 'job', 0, 40),
            'status' => 'running',
            'started_at' => date('Y-m-d H:i:s'),
            'processed' => 0,
            'failed' => 0,
        ]);
        return is_numeric($result) ? (int) $result : 0;
    }

    public function finish(int $jobRunId, int $processed, int $failed, string $summary = ''): void
    {
        if ($jobRunId <= 0) { return; }
        $this->database->save($this->table(), [
            'status' => $failed === 0 ? 'success' : 'failed',
            'finished_at' => date('Y-m-d H:i:s'),
            'processed' => max(0, $processed),
            'failed' => max(0, $failed),
            'summary' => mb_substr(strip_tags($summary), 0, 255),
        ], ['job_run_id' => $jobRunId]);
    }

    private function table(): string
    {
        return (defined('PSM_DB_PREFIX') ? (string) PSM_DB_PREFIX : 'psm_') . 'job_runs';
    }
}
