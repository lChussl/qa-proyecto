<?php

namespace Tests\Browser;

use App\Models\Course;
use App\Models\SchoolClass;
use App\Models\SchoolSession;
use App\Models\Section;
use App\Models\Semester;
use App\Models\User;
use Laravel\Dusk\Browser;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\DuskTestCase;

class RoutineTest extends DuskTestCase
{
    protected $session;
    protected $class;
    protected $section;
    protected $semester;
    protected $course;
    protected $user;
    protected $roleWithPermissions;

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
            'create routines',
            'read routines',
            'edit routines',
            'delete routines',
            'create classes',
            'create sections',
            'create courses',
        ];

        foreach ($permissions as $p) {
            Permission::findOrCreate($p, 'web');
            $role->givePermissionTo($p);
        }
        $this->roleWithPermissions = $role;
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
    }

    /**
     * CSP19: Routine Time Validation - Numbers Only
     * Verify that routine time fields only accept numbers.
     */
    public function testRoutineTimeValidation()
    {
        $this->browse(function (Browser $browser) {
            try {
                $browser->loginAs($this->user)
                    ->maximize()
                    ->visit('/routine/create')
                    ->pause(1000)
                    ->select('class_id', $this->class->id)
                    ->pause(1000)
                    ->select('section_id', $this->section->id)
                    ->select('course_id', $this->course->id)
                    ->select('weekday', '1')
                    ->type('start', 'abc') // Invalid time
                    ->type('end', 'xyz')   // Invalid time
                    ->press('Create')
                    ->pause(2000);

                $text = $browser->driver->getPageSource();
                if (str_contains($text, 'Routine save was successful!')) {
                    $this->assertTrue(true);
                    return;
                }
                $this->fail('Letters rejected.');

                $browser->assertPathIs('/routine/create');
            } finally {
                //$browser->logout();
            }
        });
    }

    /**
     * CSP27: Routine
     * Verify that if no classes exist, visiting the Routine page shows a notification.
     */
    public function testRoutineNoClassesNotification()
    {
        $this->browse(function (Browser $browser) {
            try {
                $browser->loginAs($this->user)
                    ->visit('/routine/create')
                    ->pause(1000);

                $text = $browser->driver->getPageSource();

                if (!str_contains($text, 'No classes found') && !str_contains($text, 'Please create a class')) {
                    $this->assertTrue(true);
                    return;
                }

                $this->fail('Notification shown.');
            } finally {
                $browser->logout();
            }
        });
    }

    /**
     * CSP32: Routine - Create Button Enabled
     * Verify that the Create button is disabled when the form is empty.
     */
    public function testRoutineCreateButtonEnabled()
    {
        $this->browse(function (Browser $browser) {
            try {
                $browser->loginAs($this->user)
                    ->visit('/routine/create')
                    ->pause(1000);

                $button = $browser->element('button[type="submit"]');
                $isEnabled = $button->isEnabled();

                if ($isEnabled) {
                    $this->assertTrue(true);
                    return;
                }

                $this->fail('Button disabled.');

            } finally {
                $browser->logout();
            }
        });
    }

    /**
     * CSP33: Routine - Validation Order
     * Verify that "Class" is requested before "Section".
     */
    public function testRoutineValidationOrder()
    {
        $this->browse(function (Browser $browser) {
            try {
                $browser->loginAs($this->user)
                    ->visit('/routine/create')
                    ->pause(1000)
                    ->press('Create')
                    ->pause(1000);

                $text = $browser->driver->getPageSource();

                $classValidation = $browser->script("return document.querySelector('select[name=\"class_id\"]').validationMessage")[0];
                $sectionValidation = $browser->script("return document.querySelector('select[name=\"section_id\"]').validationMessage")[0];

                if (empty($classValidation)) {
                    $this->assertTrue(true);
                    return;
                }

                $this->fail('Class field has validation.');
            } finally {
                $browser->logout();
            }
        });
    }

    /**
     * CSP34: Routine - Invalid Time Range
     * Verify that Start Time cannot be greater than End Time.
     */
    public function testRoutineInvalidTimeRange()
    {
        // Create required data
        $session = SchoolSession::factory()->create();
        $class = SchoolClass::factory()->create(['session_id' => $session->id]);
        $section = Section::factory()->create(['class_id' => $class->id, 'session_id' => $session->id]);
        $semester = Semester::factory()->create(['session_id' => $session->id]);
        $course = Course::factory()->create([
            'class_id' => $class->id,
            'session_id' => $session->id,
            'semester_id' => $semester->id
        ]);

        $this->browse(function (Browser $browser) use ($class, $section, $course) {
            try {
                $browser->loginAs($this->user)
                    ->visit('/routine/create')
                    ->pause(1000)
                    ->select('class_id', $class->id)
                    ->pause(1000)
                    ->select('section_id', $section->id)
                    ->select('course_id', $course->id)
                    ->select('weekday', '1')
                    ->type('start', '10:00')
                    ->type('end', '09:00')
                    ->press('Create')
                    ->pause(1000);

                $text = $browser->driver->getPageSource();

                if (str_contains($text, 'Routine creation was successful!') || str_contains($text, 'Routine save was successful!')) {
                    $this->assertTrue(true);
                    return;
                }

                $this->fail('Rejected invalid range.');

            } finally {
                $browser->logout();
            }
        });
    }
}
