<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class StudentBirthDateValidationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_rejects_invalid_birth_date_format()
    {
        $admin = User::factory()->create();

        $this->actingAs($admin);

        $response = $this->post('/students', [
            'first_name' => 'Ana',
            'last_name' => 'López',
            'nationality' => 'Costarricense',
            'birth_date' => '2025-13-40',
            'gender' => 'female',
            'classroom_id' => 1,
        ]);

        $response->assertSessionHasErrors(['birth_date']);
    }
}
