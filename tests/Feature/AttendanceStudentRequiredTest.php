<?php

namespace Tests\Feature\Attendance;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AttendanceStudentRequiredTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_requires_student_for_attendance()
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        $response = $this->post('/attendance', [
            'student_id' => null,
            'status' => 'present',
        ]);

        $response->assertSessionHasErrors(['student_id']);
    }
}
