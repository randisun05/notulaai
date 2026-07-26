<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class SocialAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_unsupported_provider_returns_404(): void
    {
        $this->get('/auth/facebook/redirect')->assertNotFound();
        $this->get('/auth/facebook/callback')->assertNotFound();
    }

    public function test_redirect_delegates_to_socialite_driver(): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('redirect')->once()->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));

        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);

        $response = $this->get('/auth/google/redirect');

        $response->assertRedirect('https://accounts.google.com/o/oauth2/auth');
    }

    public function test_callback_logs_in_existing_user_matched_by_email(): void
    {
        $user = User::factory()->create(['email' => 'budi@example.com']);

        $socialiteUser = new SocialiteUser();
        $socialiteUser->email = 'budi@example.com';

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);

        $response = $this->get('/auth/google/callback');

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'action' => 'auth.login']);
    }

    public function test_callback_rejects_email_with_no_matching_account(): void
    {
        $socialiteUser = new SocialiteUser();
        $socialiteUser->email = 'tidak-terdaftar@example.com';

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')->once()->with('microsoft')->andReturn($provider);

        $response = $this->get('/auth/microsoft/callback');

        $this->assertGuest();
        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_callback_handles_socialite_failure_gracefully(): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andThrow(new \Exception('invalid state'));

        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);

        $response = $this->get('/auth/google/callback');

        $this->assertGuest();
        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error');
    }
}
