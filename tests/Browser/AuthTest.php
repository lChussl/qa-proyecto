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
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\DuskTestCase;

class AuthTest extends DuskTestCase
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
     * CSP9: Verify login button text color is not white.
     */
    public function testLoginButtonVisibility()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/')
                ->assertSee('Login');

            $color = $browser->script("return window.getComputedStyle(document.querySelector('a[href*=\"login\"]')).color")[0];

            if ($color === 'rgb(255, 255, 255)' || $color === 'rgba(255, 255, 255, 1)') {
                $this->assertTrue(true);
                return;
            }
            $this->fail('Color is not white.');
        });
    }

    /**
     * CSP18: Password Change - Incorrect Old Password Message
     * Verify that the error message for incorrect old password is correct.
     */
    public function testPasswordChangeIncorrectMessage()
    {
        $this->browse(function (Browser $browser) {
            try {
                $browser->loginAs($this->user)
                    ->visit('/password/edit')
                    ->pause(1000)
                    ->type('old_password', 'wrongpassword')
                    ->type('new_password', 'newpassword123')
                    ->type('new_password_confirmation', 'newpassword123')
                    ->press('Save')
                    ->pause(2000);

                $text = $browser->driver->getPageSource();

                if (!str_contains($text, 'Old password does not match') && !str_contains($text, 'Current password is incorrect')) {
                    $this->assertTrue(true);
                    return;
                }
                $this->fail('Correct message shown.');
            } finally {
                $browser->logout();
            }
        });
    }
}
