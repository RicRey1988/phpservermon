<?php

declare(strict_types=1);

namespace Tests\Unit\Notification;

use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\TestCase;
use psm\Notification\SmtpSecurityResolver;

final class SmtpSecurityResolverTest extends TestCase
{
    public function testInfersStartTlsForSubmissionPort(): void
    {
        self::assertSame(PHPMailer::ENCRYPTION_STARTTLS, SmtpSecurityResolver::resolve('', 587));
    }

    public function testInfersImplicitTlsForSmtpsPort(): void
    {
        self::assertSame(PHPMailer::ENCRYPTION_SMTPS, SmtpSecurityResolver::resolve('', 465));
    }

    public function testKeepsExplicitSettingAndPlainLegacyPort(): void
    {
        self::assertSame(PHPMailer::ENCRYPTION_STARTTLS, SmtpSecurityResolver::resolve('tls', 25));
        self::assertSame('', SmtpSecurityResolver::resolve('', 25));
    }
}
