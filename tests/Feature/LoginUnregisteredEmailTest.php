<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LoginUnregisteredEmailTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function user_cannot_login_with_unregistered_email()
    {
        $response = $this->post('/login', [
            'email' => 'fake@correo.com',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors();
        $this->assertGuest();
    }
}
