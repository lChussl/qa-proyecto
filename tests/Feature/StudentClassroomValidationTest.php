<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class StudentClassroomValidationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_requires_classroom_id_to_create_student()
    {
        $admin = User::factory()->create();

        $this->actingAs($admin);

        $response = $this->post('/students', [
            'first_name' => 'María',
            'last_name' => 'Soto',
            'nationality' => 'Costarricense',
            'birth_date' => '2012-03-15',
            'gender' => 'female',
            'classroom_id' => null, // Sin aula
        ]);

        $response->assertSessionHasErrors(['classroom_id']);
    }
}
