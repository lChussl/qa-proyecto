<?php

namespace Tests\Feature\Grades;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class GradeSubjectRequiredTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_requires_subject_for_grade()
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        $response = $this->post('/grades', [
            'student_id' => 1,
            'subject_id' => null,
            'score' => 90,
        ]);

        $response->assertSessionHasErrors(['subject_id']);
    }
}
