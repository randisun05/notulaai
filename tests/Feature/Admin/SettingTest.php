<?php

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_superadmin_can_view_settings_page(): void
    {
        $user = User::factory()->create();
        $user->syncRoles(['superadmin']);

        $response = $this->actingAs($user)->get('/admin/settings');

        $response->assertOk();
    }

    public function test_regular_user_cannot_view_settings_page(): void
    {
        $user = User::factory()->create();
        $user->syncRoles(['user']);

        $response = $this->actingAs($user)->get('/admin/settings');

        $response->assertForbidden();
    }

    public function test_superadmin_can_update_settings(): void
    {
        $user = User::factory()->create();
        $user->syncRoles(['superadmin']);

        $response = $this->actingAs($user)->put('/admin/settings', [
            'company_name' => 'PT Contoh Indonesia',
            'company_address' => 'Jl. Contoh No. 1, Jakarta',
            'ai_text_provider' => 'gemini',
            'ai_transcription_provider' => 'whisper_local',
            'ai_ocr_provider' => 'gemini_ocr',
            'timezone' => 'Asia/Jakarta',
        ]);

        $response->assertRedirect();
        $this->assertSame('PT Contoh Indonesia', Setting::current()->company_name);
        $this->assertSame('gemini', Setting::current()->ai_text_provider);
    }

    public function test_updating_settings_rejects_unknown_ai_provider(): void
    {
        $user = User::factory()->create();
        $user->syncRoles(['superadmin']);

        $response = $this->actingAs($user)->put('/admin/settings', [
            'company_name' => 'PT Contoh Indonesia',
            'ai_text_provider' => 'not-a-real-provider',
            'ai_transcription_provider' => 'whisper_local',
            'ai_ocr_provider' => 'gemini_ocr',
            'timezone' => 'Asia/Jakarta',
        ]);

        $response->assertSessionHasErrors('ai_text_provider');
    }
}
