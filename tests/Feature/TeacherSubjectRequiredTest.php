<?php

namespace Tests\Feature\Teachers;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TeacherSubjectRequiredTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_requires_subject_for_teacher()
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        $response = $this->post('/teachers', [
            'name' => 'Mario',
            'email' => 'mario@ut.com',
            'subject_id' => null,
        ]);

        $response->assertSessionHasErrors(['subject_id']);
    }
}
