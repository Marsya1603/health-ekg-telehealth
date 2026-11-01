<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_loads(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('Masuk ke akun Anda');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/dashboard')->assertRedirect('/login');
        $this->get('/doctor/dashboard')->assertRedirect('/login');
        $this->get('/patient/dashboard')->assertRedirect('/login');
        $this->get('/network/dashboard')->assertRedirect('/login');
    }

    public function test_login_with_invalid_credentials_fails(): void
    {
        $response = $this->post('/login', [
            'email' => 'tidak-ada@example.test',
            'password' => 'password salah sekali',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
