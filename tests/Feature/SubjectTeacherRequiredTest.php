<?php

namespace Tests\Feature\Subjects;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SubjectTeacherRequiredTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_requires_teacher_for_subject()
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        $response = $this->post('/subjects', [
            'name' => 'Física',
            'teacher_id' => null,
        ]);

        $response->assertSessionHasErrors(['teacher_id']);
    }
}
