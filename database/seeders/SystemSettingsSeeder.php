<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SystemSetting;

class SystemSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['key' => 'wifi_price', 'value' => '5.99'],
            ['key' => 'wifi_price_full', 'value' => '6.99'],
            ['key' => 'pix_gateway', 'value' => 'pagbank'],
            ['key' => 'session_duration', 'value' => '12'],
            ['key' => 'session_duration_short', 'value' => '1'],
            ['key' => 'plan_short_enabled', 'value' => '1'],
            ['key' => 'plan_full_enabled', 'value' => '1'],
            ['key' => 'video_discount_enabled', 'value' => '1'],
            ['key' => 'video_discount_amount', 'value' => '1.00'],
        ];

        foreach ($settings as $setting) {
            SystemSetting::updateOrCreate(
                ['key' => $setting['key']],
                ['value' => $setting['value']]
            );
        }

        $this->command->info('✅ Configurações do sistema inseridas/atualizadas com sucesso!');
    }
}
