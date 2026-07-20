<?php

declare(strict_types=1);

namespace psm\Notification;

use PHPMailer\PHPMailer\PHPMailer;

final class SmtpSecurityResolver
{
    public static function resolve(string $configured, int $port): string
    {
        $configured = strtolower(trim($configured));
        if ($configured !== '') {
            return $configured;
        }

        return match ($port) {
            465 => PHPMailer::ENCRYPTION_SMTPS,
            587 => PHPMailer::ENCRYPTION_STARTTLS,
            default => '',
        };
    }
}
