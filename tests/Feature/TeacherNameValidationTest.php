<?php

namespace Tests\Feature\Teachers;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TeacherNameValidationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_rejects_numbers_in_teacher_name()
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        $response = $this->post('/teachers', [
            'name' => 'Prof3Mario',
            'email' => 'mario@ut.com',
        ]);

        $response->assertSessionHasErrors(['name']);
    }
}
