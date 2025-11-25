<?php

namespace Tests\Feature\Teachers;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TeacherEmailRequiredTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_requires_email_for_teacher()
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        $response = $this->post('/teachers', [
            'name' => 'Sofía Jiménez',
            'email' => '',
        ]);

        $response->assertSessionHasErrors(['email']);
    }
}
