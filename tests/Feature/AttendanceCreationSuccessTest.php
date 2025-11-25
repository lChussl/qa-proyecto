<?php

namespace Tests\Feature\Attendance;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AttendanceCreationSuccessTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_creates_attendance_with_valid_status()
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        $response = $this->post('/attendance', [
            'student_id' => 1,
            'status' => 'present',
        ]);

        $response->assertRedirect('/attendance');
        $this->assertDatabaseHas('attendances', ['student_id' => 1, 'status' => 'present']);
    }
}
