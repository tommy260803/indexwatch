<?php

namespace App\Enums;

enum AuditSource: string
{
    case Webhook = 'webhook';
    case Cli = 'cli';
    case Dashboard = 'dashboard';
    case Scheduler = 'scheduler';
    case Job = 'job';

    public static function fromValue(string $value): self
    {
        return match ($value) {
            'webhook' => self::Webhook,
            'cli' => self::Cli,
            'dashboard' => self::Dashboard,
            'scheduler' => self::Scheduler,
            'job' => self::Job,
            'whatsapp' => self::Webhook,
            'system' => self::Cli,
            default => self::Cli,
        };
    }
}