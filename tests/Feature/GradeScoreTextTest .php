<?php

namespace Tests\Feature\Grades;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class GradeScoreTextTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_rejects_text_in_score_field()
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        $response = $this->post('/grades', [
            'student_id' => 1,
            'subject_id' => 1,
            'score' => 'noventa',
        ]);

        $response->assertSessionHasErrors(['score']);
    }
}
