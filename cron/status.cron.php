<?php

declare(strict_types=1);

use psm\Util\Cron\CronLock;
use psm\Util\Cron\WebCronAuthorizer;
use psm\Util\Install\PlatformRequirements;
use psm\Util\Server\UpdateManager;

$isCli = PHP_SAPI === 'cli';

try {
    require_once __DIR__ . '/../src/bootstrap.php';

    $platform = new PlatformRequirements();
    if (!$platform->isSatisfied()) {
        throw new RuntimeException('Platform requirements are not satisfied.');
    }
} catch (Throwable) {
    if ($isCli) {
        fwrite(STDERR, "PHP Server Monitor bootstrap/platform check failed.\n");
    } else {
        http_response_code(500);
        echo 'PHP Server Monitor bootstrap/platform check failed.';
    }
    exit(2);
}

if (!$isCli) {
    $allowlist = defined('PSM_CRON_ALLOW') && is_array(PSM_CRON_ALLOW)
        ? array_values(array_filter(PSM_CRON_ALLOW, 'is_string'))
        : [];
    if (!defined('PSM_WEBCRON_ENABLE_IP_WHITELIST') || !PSM_WEBCRON_ENABLE_IP_WHITELIST) {
        $allowlist = [];
    }
    $authorizer = new WebCronAuthorizer(
        defined('PSM_WEBCRON_ENABLED') && PSM_WEBCRON_ENABLED === true,
        defined('PSM_WEBCRON_KEY') ? (string) PSM_WEBCRON_KEY : '',
        $allowlist
    );
    $remoteIp = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
    $key = isset($_GET['webcron_key']) && is_string($_GET['webcron_key']) ? $_GET['webcron_key'] : null;
    if (!$authorizer->isAllowed($remoteIp, $key)) {
        http_response_code(403);
        echo 'Forbidden';
        exit(2);
    }
}

$status = null;
if ($isCli) {
    $options = getopt('s:', ['status:', 'uri:']);
    $statusInput = $options['status'] ?? $options['s'] ?? null;
    $statusValues = ['on' => 'on', '1' => 'on', 'up' => 'on', 'off' => 'off', '0' => 'off', 'down' => 'off'];
    if (is_string($statusInput) && isset($statusValues[strtolower($statusInput)])) {
        $status = $statusValues[strtolower($statusInput)];
    }
    if (isset($options['uri']) && is_string($options['uri']) && !defined('PSM_BASE_URL')) {
        define('PSM_BASE_URL', $options['uri']);
    }
}

$lock = new CronLock(PSM_PATH_LOGS . 'status.cron.lock');
if (!$lock->acquire()) {
    $message = "Cron is already running.\n";
    $isCli ? fwrite(STDERR, $message) : print $message;
    exit(1);
}

$processed = 0;
$failed = 0;

try {
    /** @var UpdateManager $manager */
    $manager = $router->getService('util.server.updatemanager');

    if ($status !== 'off') {
        $summary = $manager->run(true, $status);
        $processed += $summary->processed();
        $failed += $summary->failed();
    } else {
        set_time_limit(65);
        $interval = defined('CRON_DOWN_INTERVAL') ? max(1, (int) CRON_DOWN_INTERVAL) : 5;
        $startedAt = time();
        for ($elapsed = 0; $elapsed < 60; $elapsed += $interval) {
            $summary = $manager->run(true, 'off');
            $processed += $summary->processed();
            $failed += $summary->failed();
            $next = $startedAt + $elapsed + $interval;
            if ($next < $startedAt + 60) {
                time_sleep_until((float) $next);
            }
        }
    }

    echo sprintf("Cron completed: processed=%d failed=%d\n", $processed, $failed);
} catch (Throwable) {
    $failed++;
    $isCli ? fwrite(STDERR, "Cron execution failed.\n") : print 'Cron execution failed.';
} finally {
    $lock->release();
}

exit($failed === 0 ? 0 : 1);
