<?php

namespace App\Enums;

enum Channel: string
{
    case Sms = 'sms';
    case Email = 'email';
    case Push = 'push';

    public function maxContentLength(): int
    {
        return match ($this) {
            self::Sms => 160,
            self::Email => 10000,
            self::Push => 500,
        };
    }

    public function recipientRules(): array
    {
        return match ($this) {
            self::Sms => ['regex:/^\+[1-9]\d{7,14}$/'],
            self::Email => ['email'],
            self::Push => ['max:255'],
        };
    }
}
