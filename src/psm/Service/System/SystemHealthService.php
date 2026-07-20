<?php

declare(strict_types=1);

namespace psm\Service\System;

use psm\Service\Database;
use Throwable;

final readonly class SystemHealthService
{
    public function __construct(private string $root, private string $logs, private ?Database $database = null)
    {
    }

    /** @return array<string, list<array{label:string,status:string,value:string,detail:string}>> */
    public function collect(): array
    {
        $runtime = [[
            'label' => 'PHP',
            'status' => version_compare(PHP_VERSION, '8.5.0', '>=') ? 'ok' : 'error',
            'value' => PHP_VERSION,
            'detail' => 'Se requiere PHP 8.5 o superior.',
        ]];
        foreach (['curl', 'gd', 'intl', 'mbstring', 'openssl', 'pdo_mysql', 'zip'] as $extension) {
            $loaded = extension_loaded($extension);
            $runtime[] = [
                'label' => 'ext-' . $extension,
                'status' => $loaded ? 'ok' : 'error',
                'value' => $loaded ? 'Disponible' : 'Falta',
                'detail' => $loaded ? 'Extensión cargada.' : 'Instala la extensión antes de usar todas las funciones.',
            ];
        }

        $free = @disk_free_space($this->root);
        $filesystem = [
            $this->writableCheck('Aplicación', $this->root),
            $this->writableCheck('Registros', $this->logs),
            [
                'label' => 'Espacio libre',
                'status' => is_float($free) && $free >= 256 * 1024 * 1024 ? 'ok' : 'warning',
                'value' => is_float($free) ? $this->bytes($free) : 'Desconocido',
                'detail' => 'Se recomiendan al menos 256 MB para actualizaciones seguras.',
            ],
        ];

        $lock = rtrim($this->logs, '/\\') . DIRECTORY_SEPARATOR . 'status.cron.lock';
        $modified = is_file($lock) ? filemtime($lock) : false;
        $fresh = is_int($modified) && $modified >= time() - 3600;
        $cron = [[
            'label' => 'Comprobador cron',
            'status' => $fresh ? 'ok' : 'warning',
            'value' => is_int($modified) ? date('Y-m-d H:i:s', $modified) : 'Sin ejecución detectada',
            'detail' => $fresh ? 'El bloqueo de cron tuvo actividad durante la última hora.' : 'Ejecuta cron/status.cron.php al menos cada minuto.',
        ]];

        $database = [];
        $notifications = [];
        if ($this->database !== null) {
            $connected = $this->database->status();
            $database[] = [
                'label' => 'Base de datos', 'status' => $connected ? 'ok' : 'error',
                'value' => $connected ? 'Conectada' : 'No disponible',
                'detail' => $connected ? 'La conexión de la aplicación responde.' : 'Revisa el servicio MySQL y la configuración privada.',
            ];
            try {
                $deliveryRows = $this->database->execute(
                    'SELECT state, COUNT(*) AS total FROM `' . $this->table('notification_deliveries') . '` '
                    . "WHERE state IN ('pending','permanent_failure') GROUP BY state",
                    [],
                );
                $counts = ['pending' => 0, 'permanent_failure' => 0];
                foreach (is_array($deliveryRows) ? $deliveryRows : [] as $row) {
                    if (isset($counts[(string) ($row['state'] ?? '')])) {
                        $counts[(string) $row['state']] = (int) ($row['total'] ?? 0);
                    }
                }
                $notifications[] = [
                    'label' => 'Entregas pendientes', 'status' => $counts['pending'] > 20 ? 'warning' : 'ok',
                    'value' => (string) $counts['pending'], 'detail' => 'Cola persistente de Email, Telegram, Web Push y otros canales.',
                ];
                $notifications[] = [
                    'label' => 'Fallos permanentes', 'status' => $counts['permanent_failure'] > 0 ? 'warning' : 'ok',
                    'value' => (string) $counts['permanent_failure'], 'detail' => 'Revisa destinatarios o credenciales si este contador aumenta.',
                ];
                $jobRows = $this->database->execute(
                    'SELECT job_name, status, finished_at, processed, failed FROM `' . $this->table('job_runs') . '` '
                    . 'WHERE finished_at IS NOT NULL ORDER BY job_run_id DESC LIMIT 1',
                    [],
                );
                $job = is_array($jobRows) ? ($jobRows[0] ?? null) : null;
                $cron[] = [
                    'label' => 'Último trabajo',
                    'status' => is_array($job) && ($job['status'] ?? '') === 'success' ? 'ok' : 'warning',
                    'value' => is_array($job) ? (string) ($job['finished_at'] ?? 'Sin finalizar') : 'Sin historial',
                    'detail' => is_array($job)
                        ? sprintf('%s: %d procesados, %d fallidos.', (string) $job['job_name'], (int) $job['processed'], (int) $job['failed'])
                        : 'El historial comenzará con la próxima comprobación.',
                ];
            } catch (Throwable) {
                $notifications[] = [
                    'label' => 'Cola de notificaciones', 'status' => 'warning', 'value' => 'No comprobada',
                    'detail' => 'La tabla de diagnóstico aún puede requerir la migración 4.1.0-hs.',
                ];
            }
        }
        $config = is_array($GLOBALS['sm_config'] ?? null) ? $GLOBALS['sm_config'] : [];
        foreach (['email' => 'Email', 'telegram' => 'Telegram', 'webpush' => 'Web Push'] as $key => $label) {
            $enabled = ($config[$key . '_status'] ?? '0') === '1';
            $notifications[] = [
                'label' => $label,
                'status' => $enabled ? 'ok' : 'warning',
                'value' => $enabled ? 'Habilitado' : 'Deshabilitado',
                'detail' => $enabled ? 'Canal activado; use su prueba dedicada para validar la entrega.' : 'Este canal no enviará alertas.',
            ];
        }
        $application = [[
            'label' => 'PWA',
            'status' => is_file($this->root . '/service-worker.js') && is_file($this->root . '/manifest.webmanifest') ? 'ok' : 'error',
            'value' => is_file($this->root . '/service-worker.js') ? 'Disponible' : 'Incompleta',
            'detail' => 'La instalación y Web Push requieren HTTPS en producción.',
        ]];

        return [
            'runtime' => $runtime,
            'filesystem' => $filesystem,
            'database' => $database,
            'cron' => $cron,
            'notifications' => $notifications,
            'application' => $application,
        ];
    }

    /** @return array{label:string,status:string,value:string,detail:string} */
    private function writableCheck(string $label, string $path): array
    {
        $writable = is_dir($path) && is_writable($path);
        $phpUser = $this->effectiveUser();
        $quotedPath = "'" . str_replace("'", "'\\''", $path) . "'";
        $remedy = 'sudo chgrp -R ' . $phpUser . ' ' . $quotedPath . ' && sudo chmod -R g+rwX ' . $quotedPath;
        return [
            'label' => $label,
            'status' => $writable ? 'ok' : 'error',
            'value' => $writable ? 'Escribible' : 'Sin escritura',
            'detail' => ($writable ? 'Permisos correctos. ' : 'PHP no puede crear actualizaciones, caché o registros. ')
                . 'Usuario PHP: ' . $phpUser . '. Ruta: ' . $path . '. '
                . ($writable ? 'Comando de recuperación si cambian: ' : 'Solución recomendada: ') . $remedy,
        ];
    }

    private function effectiveUser(): string
    {
        if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
            $account = posix_getpwuid(posix_geteuid());
            if (is_array($account) && $account['name'] !== '') {
                return preg_replace('/[^a-zA-Z0-9_.-]/', '', $account['name']) ?: 'www-data';
            }
        }
        return preg_replace('/[^a-zA-Z0-9_.-]/', '', get_current_user()) ?: 'www-data';
    }

    private function bytes(float $bytes): string
    {
        if ($bytes >= 1024 ** 3) {
            return number_format($bytes / (1024 ** 3), 1) . ' GB';
        }
        return number_format($bytes / (1024 ** 2), 0) . ' MB';
    }

    private function table(string $name): string
    {
        return (defined('PSM_DB_PREFIX') ? (string) PSM_DB_PREFIX : 'psm_') . $name;
    }
}
