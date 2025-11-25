<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class StudentGenderValidationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_rejects_invalid_gender_value()
    {
        $admin = User::factory()->create();

        $this->actingAs($admin);

        $response = $this->post('/students', [
            'first_name' => 'Luis',
            'last_name' => 'Gómez',
            'nationality' => 'Costarricense',
            'birth_date' => '2011-04-12',
            'gender' => 'robot', // Valor inválido
            'classroom_id' => 1,
        ]);

        $response->assertSessionHasErrors(['gender']);
    }
}
