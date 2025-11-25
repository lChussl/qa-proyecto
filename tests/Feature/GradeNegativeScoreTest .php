<?php

namespace Tests\Feature\Grades;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class GradeNegativeScoreTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_rejects_negative_score_in_grade()
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        $response = $this->post('/grades', [
            'student_id' => 1,
            'subject_id' => 1,
            'score' => -10,
        ]);

        $response->assertSessionHasErrors(['score']);
    }
}
