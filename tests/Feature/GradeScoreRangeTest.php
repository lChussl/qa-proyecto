<?php

namespace Tests\Feature\Grades;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class GradeScoreRangeTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_rejects_score_above_100()
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        $response = $this->post('/grades', [
            'student_id' => 1,
            'subject_id' => 1,
            'score' => 120,
        ]);

        $response->assertSessionHasErrors(['score']);
    }
}
