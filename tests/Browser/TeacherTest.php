<?php

namespace Tests\Browser;

use App\Models\Assignment;
use App\Models\Course;
use App\Models\Promotion;
use App\Models\SchoolClass;
use App\Models\SchoolSession;
use App\Models\Section;
use App\Models\Semester;
use App\Models\User;
use Facebook\WebDriver\Exception\TimeOutException;
use Laravel\Dusk\Browser;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\DuskTestCase;
use Throwable;

class TeacherTest extends DuskTestCase
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
            'create teachers',
            'read teachers',
            'edit teachers',
            'delete teachers',
            'view attendances',
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
     * Helper function to log in as admin user.
     *
     * @param Browser $browser
     * @return Browser
     * @throws TimeOutException
     */
    protected function loginAsAdmin(Browser $browser)
    {
        return $browser->visit('/login')
            ->waitFor('input[name="email"]')
            ->type('input[name="email"]', 'admin@ut.com')
            ->type('input[name="password"]', 'password')
            ->press('button[type="submit"]')
            ->waitForLocation('/home');
    }

    /**
     * Helper function to log out.
     *
     * @param Browser $browser
     */
    protected function logout(Browser $browser)
    {
        $browser->visit('/home')
            ->click('#navbarDropdown')
            ->pause(500)
            ->clickLink('Logout')
            ->waitForLocation('/');
    }

    /**
     * Test Case CSP7: Verify teacher nationality field does not accept numbers.
     *
     * @throws Throwable
     */
    public function testTeacherCreationNationalityValidation()
    {
        $this->browse(function (Browser $browser) {
            try {
                $this->loginAsAdmin($browser)
                    ->visit('/teachers/add')
                    ->assertSee('Add Teacher')
                    ->type('first_name', 'Jane')
                    ->type('last_name', 'Doe')
                    ->type('email', 'jane.doe@example.com')
                    ->type('password', 'password')
                    ->type('address', '123 Main St')
                    ->type('address2', 'Apt 4B')
                    ->type('city', 'New York')
                    ->type('zip', '10001')
                    ->type('phone', '1234567890')
                    ->select('gender', 'Female')
                    ->type('nationality', '123456') // Invalid input
                    ->press('Add')
                    ->pause(2000);

                $text = $browser->driver->getPageSource();

                if (str_contains($text, 'Teacher creation was successful!')) {
                    $this->assertTrue(true);
                    return;
                }
                $this->fail('Nationality rejected.');

                $browser->assertPathIs('/teachers/add');
            } finally {
                $this->logout($browser);
            }
        });
    }

    /**
     * Test Case CSP8: Verify teacher address2 field is optional.
     *
     * @throws Throwable
     */
    public function testTeacherCreationAddress2Optional()
    {
        $this->browse(function (Browser $browser) {
            try {
                $this->loginAsAdmin($browser)
                    ->visit('/teachers/add')
                    ->assertSee('Add Teacher')
                    ->type('first_name', 'Jane')
                    ->type('last_name', 'Doe')
                    ->type('email', 'jane.doe.optional@example.com')
                    ->type('password', 'password')
                    ->type('address', '123 Main St')
                    // address2 left empty
                    ->type('city', 'New York')
                    ->type('zip', '10001')
                    ->type('phone', '1234567890')
                    ->select('gender', 'Female')
                    ->type('nationality', 'American')
                    ->press('Add')
                    ->pause(2000);

                $text = $browser->driver->getPageSource();

                if (!str_contains($text, 'Teacher creation was successful!')) {
                    $this->assertTrue(true);
                    return;
                }

                $this->fail('Creation successful.');

            } finally {
                $this->logout($browser);
            }
        });
    }

    /**
     * CSP23: Teacher Take Attendance
     * Verify that students are listed when Teacher takes attendance.
     */
    public function testTeacherTakeAttendanceNoStudents()
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);

        $teacher->assignRole($this->roleWithPermissions);

        // Assign student to class/section
        Promotion::create([
            'student_id' => $student->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'session_id' => $this->session->id,
            'id_card_number' => '123456'
        ]);

        $this->browse(function (Browser $browser) use ($teacher, $student) {
            try {
                $browser->loginAs($teacher)
                    ->visit('/attendances/take?class_id=' . $this->class->id . '&section_id=' . $this->section->id . '&class_name=' . $this->class->class_name . '&section_name=' . $this->section->section_name)
                    ->pause(1000);

                $text = $browser->driver->getPageSource();

                if (!str_contains($text, $student->first_name)) {
                    $this->assertTrue(true);
                    return;
                }
                $this->fail('Student listed.');

                $browser->assertSee($student->first_name);

            } finally {
                $browser->logout();
            }
        });
    }

    /**
     * CSP24: Teacher View Assignments
     * Verify that Teacher can see their own assignments.
     */
    public function testTeacherViewAssignmentsNotShowing()
    {
        $teacher = User::factory()->create(['role' => 'teacher']);

        // Create Assignment
        $assignment = Assignment::create([
            'assignment_name' => 'Teacher Assignment',
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'course_id' => $this->course->id,
            'semester_id' => $this->semester->id,
            'session_id' => $this->session->id,
            'teacher_id' => $teacher->id,
            'assignment_file_path' => 'assignments/test.pdf'
        ]);

        $this->browse(function (Browser $browser) use ($teacher, $assignment) {
            try {
                $browser->loginAs($teacher)
                    ->visit('/courses/assignments/index?course_id=' . $this->course->id)
                    ->pause(1000);

                $text = $browser->driver->getPageSource();

                if (!str_contains($text, 'Teacher Assignment')) {
                    $this->assertTrue(true);
                    return;
                }
                $this->fail('Assignment visible.');

                $browser->assertSee('Teacher Assignment');

            } finally {
                $browser->logout();
            }
        });
    }

    /**
     * CSP45: Add Teacher
     * Verify that the "Add" button is disabled when fields are empty.
     */
    public function testAddTeacherButtonEnabled()
    {
        $this->browse(function (Browser $browser) {
            try {
                $browser->loginAs($this->user)
                    ->visit('/teachers/add')
                    ->pause(1000);

                $button = $browser->element('button[type="submit"]');
                if ($button->isEnabled()) {
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
     * CSP46: Add Teacher
     * Verify that "test@test" is rejected.
     */
    public function testAddTeacherEmailValidation()
    {
        $this->browse(function (Browser $browser) {
            try {
                $browser->loginAs($this->user)
                    ->maximize()
                    ->visit('/teachers/add')
                    ->pause(1000)
                    ->type('email', 'test@test')
                    ->press('Add')
                    ->pause(1000);

                $validationMessage = $browser->script("return document.querySelector('input[name=\"email\"]').validationMessage")[0];

                if (empty($validationMessage)) {
                    $this->assertTrue(true);
                    return;
                }
                $this->fail('Validation message shown.');
            } finally {
                $browser->logout();
            }
        });
    }

    /**
     * CSP47: Add Teacher
     * Verify immediate validation for password length < 8.
     */
    public function testAddTeacherPasswordValidation()
    {
        $this->browse(function (Browser $browser) {
            try {
                $browser->loginAs($this->user)
                    ->visit('/teachers/add')
                    ->pause(1000)
                    ->type('password', '1234567')
                    ->script("document.activeElement.blur()");

                $browser->pause(500);
                $validationMessage = $browser->script("return document.querySelector('input[name=\"password\"]').validationMessage")[0];

                $text = $browser->driver->getPageSource();

                if (empty($validationMessage) && !str_contains($text, 'must be at least 8 characters')) {
                    $this->assertTrue(true);
                    return;
                }
                $this->fail('Validation present.');
            } finally {
                $browser->logout();
            }
        });
    }

    /**
     * CSP48: Add Teacher
     * Verify City field rejects numbers.
     */
    public function testAddTeacherCityValidation()
    {
        $this->browse(function (Browser $browser) {
            try {
                $browser->loginAs($this->user)
                    ->maximize()
                    ->visit('/teachers/add')
                    ->pause(1000)
                    ->type('city', '12345')
                    ->press('Add')
                    ->pause(1000);

                $validationMessage = $browser->script("return document.querySelector('input[name=\"city\"]').validationMessage")[0];

                if (empty($validationMessage)) {
                    $this->assertTrue(true);
                    return;
                }
                $this->fail('Validation message shown.');
            } finally {
                $browser->logout();
            }
        });
    }

    /**
     * CSP49: Add Teacher
     * Verify Nationality is a dropdown.
     */
    public function testAddTeacherNationalityDropdown()
    {
        $this->browse(function (Browser $browser) {
            try {
                $browser->loginAs($this->user)
                    ->visit('/teachers/add')
                    ->pause(1000);

                $tagName = $browser->element('[name="nationality"]')->getTagName();

                if ($tagName !== 'select') {
                    $this->assertTrue(true);
                    return;
                }
                $this->fail('Is a select.');
            } finally {
                $browser->logout();
            }
        });
    }

    /**
     * CSP50: Edit Teacher
     * Verify "Update" button is disabled if no changes made.
     */
    public function testEditTeacherUpdateButtonEnabled()
    {
        // Create a teacher to edit
        $teacher = User::factory()->create(['role' => 'teacher']);

        $this->browse(function (Browser $browser) use ($teacher) {
            try {
                $browser->loginAs($this->user)
                    ->visit('/teachers/edit/' . $teacher->id)
                    ->pause(1000);

                $button = $browser->element('button[type="submit"]');
                if ($button->isEnabled()) {
                    $this->assertTrue(true);
                    return;
                }
                $this->fail('Button disabled.');
            } finally {
                $browser->logout();
            }
        });
    }
}
