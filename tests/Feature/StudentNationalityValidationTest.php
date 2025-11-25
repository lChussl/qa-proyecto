<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class StudentNationalityValidationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_rejects_numbers_in_nationality_field()
    {
        $admin = User::factory()->create([
            'email' => 'admin@ut.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $this->actingAs($admin);
        $response = $this->post('/students', [
            'first_name' => 'Carlos',
            'last_name' => 'Méndez',
            'nationality' => 'Costa123Rica',
            'birth_date' => '2010-05-10',
            'gender' => 'male',
            'classroom_id' => 1,
        ]);
        $response->assertSessionHasErrors(['nationality']);
    }
}
