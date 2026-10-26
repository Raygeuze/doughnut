<?php

namespace Database\Seeders;

use App\Models\OutreachSetting;
use Illuminate\Database\Seeder;

class OutreachSettingsSeeder extends Seeder
{
    /**
     * Seed outreach defaults.
     */
    public function run(): void
    {
        OutreachSetting::query()->updateOrCreate(
            ['key' => 'outreach_cooldown_days'],
            ['value' => (string) config('outreach.cooldown_days', 30)],
        );
    }
}
