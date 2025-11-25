<?php

namespace Tests\Feature\Attendance;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AttendanceStatusValidationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_rejects_invalid_attendance_status()
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        $response = $this->post('/attendance', [
            'student_id' => 1,
            'status' => 'desconocido',
        ]);

        $response->assertSessionHasErrors(['status']);
    }
}
