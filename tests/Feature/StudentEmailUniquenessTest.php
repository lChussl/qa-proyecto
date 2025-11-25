<?php

namespace Tests\Feature\Students;

use Tests\TestCase;
#use App\Models\User;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;

class StudentEmailUniquenessTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_rejects_duplicate_email()
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        Student::factory()->create(['email' => 'test@correo.com']);

        $response = $this->post('/students', [
            'first_name' => 'Ana',
            'last_name' => 'Soto',
            'nationality' => 'Costarricense',
            'birth_date' => '2010-01-01',
            'gender' => 'female',
            'classroom_id' => 1,
            'email' => 'test@correo.com',
        ]);

        $response->assertSessionHasErrors(['email']);
    }
}
