<?php

namespace Tests\Browser;

use App\Models\Course;
use App\Models\Exam;
use App\Models\SchoolClass;
use App\Models\SchoolSession;
use App\Models\Section;
use App\Models\Semester;
use App\Models\User;
use Facebook\WebDriver\WebDriverBy;
use Laravel\Dusk\Browser;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\DuskTestCase;

class DashboardTest extends DuskTestCase
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
     * CSP35: Dashboard
     * Verify that "Payment", "Staff", and "Library" options are hidden or have an explanation if disabled.
     */
    public function testDashboardDisabledOptions()
    {
        $this->browse(function (Browser $browser) {
            try {
                $browser->loginAs($this->user)
                    ->visit('/home')
                    ->resize(1920, 1080)
                    ->pause(1000);

                $payment = $browser->driver->findElement(WebDriverBy::xpath('//*[@id="app"]/main/div/div/div[1]/div/ul/li[12]/a'));
                $staff = $browser->driver->findElement(WebDriverBy::xpath('//*[@id="app"]/main/div/div/div[1]/div/ul/li[13]/a'));
                $library = $browser->driver->findElement(WebDriverBy::xpath('//*[@id="app"]/main/div/div/div[1]/div/ul/li[14]/a'));

                if ($payment && $staff && $library) {
                    $paymentDisabled = $payment->getAttribute('class');
                    $staffDisabled = $staff->getAttribute('class');
                    $libraryDisabled = $library->getAttribute('class');

                    if (str_contains($paymentDisabled, 'disabled') && str_contains($staffDisabled, 'disabled') && str_contains($libraryDisabled, 'disabled')) {
                        $this->assertTrue(true);
                        return;
                    }
                }

                $this->fail('Options not disabled or not found.');

            } finally {
                $browser->logout();
            }
        });
    }
}
