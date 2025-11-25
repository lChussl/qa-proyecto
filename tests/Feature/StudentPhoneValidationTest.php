<?php

namespace Tests\Feature\Students;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class StudentPhoneValidationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_rejects_invalid_phone_format()
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        $response = $this->post('/students', [
            'first_name' => 'Carlos',
            'last_name' => 'Ramírez',
            'nationality' => 'Costarricense',
            'birth_date' => '2010-01-01',
            'gender' => 'male',
            'classroom_id' => 1,
            'phone' => 'abc123',
        ]);

        $response->assertSessionHasErrors(['phone']);
    }
}
