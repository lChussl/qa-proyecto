<?php

namespace Tests\Feature\Students;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class StudentBirthDateRequiredTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_requires_birth_date_for_student()
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        $response = $this->post('/students', [
            'first_name' => 'Lucía',
            'last_name' => 'Vargas',
            'nationality' => 'Costarricense',
            'birth_date' => null,
            'gender' => 'female',
            'classroom_id' => 1,
        ]);

        $response->assertSessionHasErrors(['birth_date']);
    }
}
