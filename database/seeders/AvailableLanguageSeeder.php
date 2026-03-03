<?php

namespace Database\Seeders;

use App\Models\AvailableLanguage;
use Illuminate\Database\Seeder;

class AvailableLanguageSeeder extends Seeder
{
    /**
     * Seed the available_languages table.
     */
    public function run(): void
    {
        $languages = [
            ['name' => 'GR1 English',    'code' => 'EN'],
            ['name' => 'GR2 English',    'code' => 'EN2'],
            ['name' => 'Portuguese',     'code' => 'EN'],
            ['name' => 'Russian',        'code' => 'RU'],
            ['name' => 'Uzbek',          'code' => 'UZ'],
            ['name' => 'Uzbek(Latin)',   'code' => 'UZL'],
            ['name' => 'Deutsch',        'code' => 'DE'],
            ['name' => 'Greek',          'code' => 'GR'],
            ['name' => 'Latvian',        'code' => 'LV'],
            ['name' => 'Polish',         'code' => 'PL'],
            ['name' => 'Bulgarian',      'code' => 'BG'],
        ];

        foreach ($languages as $language) {
            AvailableLanguage::firstOrCreate(
                ['name' => $language['name'], 'code' => $language['code']]
            );
        }
    }
}
