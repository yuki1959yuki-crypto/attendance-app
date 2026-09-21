<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function email_is_required_for_admin_login()
    {
        $response = $this->post('/login', [
            'email' => '',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors(['email']);
    }

    /** @test */
    public function password_is_required_for_admin_login()
    {
        $response = $this->post('/login', [
            'email' => 'admin@example.com',
            'password' => '',
        ]);

        $response->assertSessionHasErrors(['password']);
    }

    /** @test */
    public function error_message_is_displayed_when_admin_login_credentials_do_not_match()
    {
        $response = $this->post('/login', [
            'email' => 'wrong_admin@example.com',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors(['email']);
    }
}
