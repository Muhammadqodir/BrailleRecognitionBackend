<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders(): void
    {
        $this->get(route('admin.login'))->assertOk();
    }

    public function test_guests_are_sent_to_the_admin_login(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
    }

    public function test_an_admin_can_sign_in_and_out(): void
    {
        Admin::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'secret-password']);

        $this->post(route('admin.login'), ['email' => 'admin@example.com', 'password' => 'secret-password'])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticated('admin');

        $this->post(route('admin.logout'))->assertRedirect(route('admin.login'));
        $this->assertGuest('admin');
    }

    public function test_a_wrong_password_is_rejected(): void
    {
        Admin::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'secret-password']);

        $this->post(route('admin.login'), ['email' => 'admin@example.com', 'password' => 'nope'])
            ->assertSessionHasErrors('email');
        $this->assertGuest('admin');
    }

    public function test_app_accounts_cannot_open_the_dashboard(): void
    {
        $user = User::factory()->create(['email' => 'user@example.com', 'password' => 'secret-password']);

        $this->post(route('admin.login'), ['email' => 'user@example.com', 'password' => 'secret-password'])
            ->assertSessionHasErrors('email');

        $this->actingAs($user)->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
    }

    public function test_api_guests_still_get_a_401_not_the_admin_login(): void
    {
        $this->getJson('/api/subscription')->assertUnauthorized();
    }

    public function test_an_admin_can_change_their_password(): void
    {
        $admin = Admin::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'secret-password']);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.settings.password.update'), [
                'current_password' => 'secret-password',
                'password' => 'a-new-password',
                'password_confirmation' => 'a-new-password',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(password_verify('a-new-password', $admin->fresh()->password));
    }
}
