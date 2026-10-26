<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Translation;
use App\Models\User;
use App\Support\OcrModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OcrModelTest extends TestCase
{
    use RefreshDatabase;

    private function scan(string $installId, string $appVersion, array $meta = []): Translation
    {
        $user = User::factory()->create(['install_id' => $installId]);
        $translation = Translation::create([
            'user_id' => $user->id,
            'lang' => 'EN',
            'result_json' => json_encode(array_merge(['engine' => 'device', 'model' => OcrModel::OLD_NAME, 'cells' => []], $meta)),
        ]);
        DB::table('analytics_events')->insert([
            'event_uid' => uniqid('e', true), 'install_id' => $installId, 'name' => 'scan_completed',
            'platform' => 'android', 'app_version' => $appVersion,
            'created_at' => $translation->created_at->copy()->subSeconds(4), 'updated_at' => now(),
        ]);

        return $translation;
    }

    public function test_the_dashboard_names_the_models_by_the_app_version_that_read_the_scan(): void
    {
        $admin = Admin::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'secret-password']);

        $new = $this->scan('install-new', '4.3.1+125');
        $this->actingAs($admin, 'admin')->getJson(route('admin.translations.show', $new))
            ->assertJsonPath('data.app_version', '4.3.1+125')
            ->assertJsonPath('data.model', OcrModel::H2_NAME);

        $old = $this->scan('install-old', '4.2.0+123');
        $this->actingAs($admin, 'admin')->getJson(route('admin.translations.show', $old))
            ->assertJsonPath('data.app_version', '4.2.0+123')
            ->assertJsonPath('data.model', OcrModel::OLD_NAME);

        // From 4.3.2 the scan says both itself.
        $own = $this->scan('install-own', '4.2.0+123', ['app_version' => '4.3.2+126', 'model' => 'next model']);
        $this->actingAs($admin, 'admin')->getJson(route('admin.translations.show', $own))
            ->assertJsonPath('data.app_version', '4.3.2+126')
            ->assertJsonPath('data.model', 'next model');
    }
}
