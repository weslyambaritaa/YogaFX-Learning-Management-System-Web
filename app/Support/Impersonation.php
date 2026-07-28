<?php

namespace App\Support;

class Impersonation
{
    public const SESSION_KEY = 'impersonator_admin_id';

    public const STARTED_AT_KEY = 'impersonator_started_at';

    public static function active(): bool
    {
        return session()->has(self::SESSION_KEY);
    }

    public static function adminId(): ?int
    {
        $value = session(self::SESSION_KEY);

        return $value !== null ? (int) $value : null;
    }

    public static function startedAt(): ?int
    {
        $value = session(self::STARTED_AT_KEY);

        return $value !== null ? (int) $value : null;
    }
}
