<?php

namespace Tests\Feature\Teachers;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TeacherEmailFormatTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_rejects_invalid_email_format_for_teacher()
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        $response = $this->post('/teachers', [
            'name' => 'Mario',
            'email' => 'profesor.com',
        ]);

        $response->assertSessionHasErrors(['email']);
    }
}
