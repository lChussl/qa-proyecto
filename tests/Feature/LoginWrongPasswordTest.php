<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LoginWrongPasswordTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function user_cannot_login_with_wrong_password()
    {
        User::factory()->create([
            'email' => 'admin@ut.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->post('/login', [
            'email' => 'admin@ut.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertSessionHasErrors();
        $this->assertGuest();
    }
}
