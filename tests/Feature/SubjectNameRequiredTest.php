<?php

namespace Tests\Feature\Subjects;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SubjectNameRequiredTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_requires_subject_name()
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        $response = $this->post('/subjects', [
            'name' => '',
        ]);

        $response->assertSessionHasErrors(['name']);
    }
}
