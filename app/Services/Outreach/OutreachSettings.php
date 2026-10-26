<?php

namespace App\Services\Outreach;

use App\Models\OutreachSetting;

class OutreachSettings
{
    public function getInt(string $key, int $default): int
    {
        $value = OutreachSetting::query()->where('key', $key)->value('value');

        if ($value === null) {
            return $default;
        }

        return (int) $value;
    }

    public function getBool(string $key, bool $default): bool
    {
        $value = OutreachSetting::query()->where('key', $key)->value('value');

        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
