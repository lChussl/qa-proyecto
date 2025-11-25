<?php

namespace Tests\Feature\Subjects;

use Tests\TestCase;
use App\Models\User;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SubjectNameUniquenessTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_rejects_duplicate_subject_name()
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        Subject::factory()->create(['name' => 'Matemáticas']);

        $response = $this->post('/subjects', [
            'name' => 'Matemáticas',
            'teacher_id' => 1,
        ]);

        $response->assertSessionHasErrors(['name']);
    }
}
