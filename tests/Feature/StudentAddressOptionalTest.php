<?php

namespace Tests\Feature\Students;

use Tests\TestCase;
use App\Models\User;
use App\Models\Classroom;
use Illuminate\Foundation\Testing\RefreshDatabase;

class StudentAddressOptionalTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_allows_empty_address()
    {
        $admin = User::factory()->create();
        $classroom = Classroom::factory()->create();

        $this->actingAs($admin);

        $response = $this->post('/students', [
            'first_name' => 'Laura',
            'last_name' => 'Jiménez',
            'nationality' => 'Costarricense',
            'birth_date' => '2010-01-01',
            'gender' => 'female',
            'classroom_id' => $classroom->id,
            'address' => '',
        ]);

        $response->assertSessionDoesntHaveErrors(['address']);
    }
}
