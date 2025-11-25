<?php

namespace Tests\Feature\Grades;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class GradeStudentRequiredTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_requires_student_for_grade()
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        $response = $this->post('/grades', [
            'student_id' => null,
            'subject_id' => 1,
            'score' => 90,
        ]);

        $response->assertSessionHasErrors(['student_id']);
    }
}
