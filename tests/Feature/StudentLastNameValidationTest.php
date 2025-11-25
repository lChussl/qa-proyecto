<?php

namespace Tests\Feature\Students;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class StudentLastNameValidationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_requires_last_name()
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        $response = $this->post('/students', [
            'first_name' => 'Luis',
            'last_name' => '',
            'nationality' => 'Costarricense',
            'birth_date' => '2010-01-01',
            'gender' => 'male',
            'classroom_id' => 1,
        ]);

        $response->assertSessionHasErrors(['last_name']);
    }
}
