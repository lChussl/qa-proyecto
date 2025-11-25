<?php

namespace Tests\Feature\Teachers;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TeacherPhoneValidationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_rejects_non_numeric_phone_for_teacher()
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        $response = $this->post('/teachers', [
            'name' => 'Mario',
            'email' => 'mario@ut.com',
            'phone' => '123abc',
        ]);

        $response->assertSessionHasErrors(['phone']);
    }
}
