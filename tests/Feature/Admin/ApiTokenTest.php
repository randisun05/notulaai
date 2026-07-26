<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class ApiTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_manage_api_tokens(): void
    {
        $this->post('/api-tokens', ['name' => 'x'])->assertRedirect('/login');
        $this->delete('/api-tokens/1')->assertRedirect('/login');
    }

    public function test_user_can_create_an_api_token(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/api-tokens', ['name' => 'Integrasi Zapier']);

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHas('plainTextToken');
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'Integrasi Zapier',
        ]);
    }

    public function test_created_token_can_authenticate_api_requests(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('CI Test');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token->plainTextToken)
            ->getJson('/api/user');

        $response->assertOk();
        $response->assertJsonPath('id', $user->id);
    }

    public function test_user_can_revoke_own_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('Untuk dicabut');

        $response = $this->actingAs($user)->delete('/api-tokens/' . $token->accessToken->id);

        $response->assertRedirect(route('profile.edit'));
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
    }

    public function test_user_cannot_revoke_another_users_token(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $token = $owner->createToken('Milik owner');

        $response = $this->actingAs($intruder)->delete('/api-tokens/' . $token->accessToken->id);

        $response->assertForbidden();
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $token->accessToken->id]);
    }
}
