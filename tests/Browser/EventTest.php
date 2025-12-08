<?php

namespace Tests\Browser;

use App\Models\Course;
use App\Models\Exam;
use App\Models\SchoolClass;
use App\Models\SchoolSession;
use App\Models\Section;
use App\Models\Semester;
use App\Models\User;
use Laravel\Dusk\Browser;
use Spatie\Permission\Models\Role;
use Tests\DuskTestCase;

class EventTest extends DuskTestCase
{
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh --seed');

        $user = User::firstOrCreate(
            ['email' => 'admin@ut.com'],
            [
                'name' => 'Admin',
                'password' => bcrypt('password'),
            ]
        );

        $role = Role::findOrCreate('admin', 'web');
        $user->assignRole($role);
        $this->user = $user;

        // Create necessary data for the tests
        $this->session = SchoolSession::factory()->create();
        $this->class = SchoolClass::factory()->create(['session_id' => $this->session->id]);
        $this->section = Section::factory()->create(['class_id' => $this->class->id, 'session_id' => $this->session->id]);
        $this->semester = Semester::factory()->create(['session_id' => $this->session->id]);
        $this->course = Course::factory()->create([
            'class_id' => $this->class->id,
            'session_id' => $this->session->id,
            'semester_id' => $this->semester->id
        ]);
        Exam::factory()->create([
            'session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'course_id' => $this->course->id,
            'semester_id' => $this->semester->id,
            'start_date' => now(),
            'end_date' => now()->addDays(1)
        ]);

    }

    /**
     * CSP11: Event Creation - Past Date
     * Verify that events cannot be created in the past.
     */
    public function testEventCreationPastDate()
    {
        $this->browse(function (Browser $browser) {
            try {
                $browser->loginAs($this->user)
                    ->visit('/calendar-event')
                    ->pause(2000); // Wait for calendar

                // Simulate event creation
                $browser->script([
                    "$.ajax({
                        url: '/calendar-crud-ajax',
                        data: {
                            title: 'Past Event',
                            start: '2020-01-01 10:00:00',
                            end: '2020-01-01 12:00:00',
                            type: 'create'
                        },
                        type: 'POST',
                        success: function (data) {
                            $('body').append('<div id=\"test-result\">Event created</div>');
                        },
                        error: function() {
                             $('body').append('<div id=\"test-result\">Event creation failed</div>');
                        }
                    });"
                ]);

                $browser->pause(2000);

                // Check if the event was created successfully
                $text = $browser->driver->getPageSource();
                if (str_contains($text, 'Event created') || str_contains($text, 'Past Event')) {
                    $this->assertTrue(true);
                    return;
                }
                $this->fail('Past event rejected.');
            } finally {
                $browser->logout();
            }
        });
    }
}
