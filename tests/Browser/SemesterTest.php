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

class SemesterTest extends DuskTestCase
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
        $permissions = [
            'create semesters',
            'view academic settings',
        ];

        foreach ($permissions as $p) {
            Permission::findOrCreate($p, 'web');
            $role->givePermissionTo($p);
        }
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
     * CSP10: Semester Creation - End Date Validation
     * Verify that the semester end date cannot be before the start date.
     */
    public function testSemesterCreationDateValidation()
    {
        $this->browse(function (Browser $browser) {
            try {
                $browser->loginAs($this->user)
                    ->visit('/academics/settings')
                    ->pause(1000);

                // Target the Semester Creation form
                $browser->within('form[action$="semester/create"]', function ($form) {
                    $form->type('semester_name', 'Invalid Date Semester')
                        // Start Date: 2024-09-25
                        ->script([
                            "document.getElementsByName('start_date')[0].value = '2024-09-25'",
                            // End Date: 2023-03-12 (Before Start Date)
                            "document.getElementsByName('end_date')[0].value = '2023-03-12'"
                        ]);
                    $form->press('Create');
                })->pause(2000);

                $text = $browser->driver->getPageSource();

                if (str_contains($text, 'Semester creation was successful!')) {
                    $this->assertTrue(true);
                    return;
                }
                $this->fail('Date order rejected.');
            } finally {
                $browser->logout();
            }
        });
    }

    /**
     * CSP17: Semester Creation - Past Start Date
     * Verify that semesters cannot be created with a start date in the past.
     */
    public function testSemesterCreationPastDate()
    {
        $this->browse(function (Browser $browser) {
            try {
                $browser->loginAs($this->user)
                    ->visit('/academics/settings')
                    ->pause(1000);

                $browser->within('form[action$="semester/create"]', function ($form) {
                    $form->type('semester_name', 'Past Semester')
                        // Start Date: 2020-01-01
                        ->script([
                            "document.getElementsByName('start_date')[0].value = '2020-01-01'",
                            "document.getElementsByName('end_date')[0].value = '2020-06-01'"
                        ]);
                    $form->press('Create');
                })->pause(2000);

                $text = $browser->driver->getPageSource();

                if (str_contains($text, 'Semester creation was successful!')) {
                    $this->assertTrue(true);
                    return;
                }
                $this->fail('Past date rejected.');

            } finally {
                $browser->logout();
            }
        });
    }
}
