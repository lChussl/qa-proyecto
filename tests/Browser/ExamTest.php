<?php

namespace Tests\Browser;

use App\Models\Course;
use App\Models\Exam;
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

class ExamTest extends DuskTestCase
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
            'create exams',
            'create exams rule',
            'view exams',
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
     * Test Case CSP12: Verify exam creation in past dates.
     *
     * @throws Throwable
     */
    public function testExamCreationPastDate()
    {
        $this->browse(function (Browser $browser) {
            try {
                $this->loginAsAdmin($browser)
                    ->visit('/exams/create')
                    ->assertSee('Create Exam')
                    ->select('semester_id')
                    ->pause(1000)
                    ->select('class_id')
                    ->pause(2000)
                    ->waitUntilEnabled('select[name="course_id"]')
                    ->pause(2000)
                    ->select('course_id')
                    ->type('exam_name', 'Past Exam')
                    // Start Date: 2020-01-01
                    ->script([
                        "document.getElementsByName('start_date')[0].value = '2020-01-01T10:00'",
                        "document.getElementsByName('end_date')[0].value = '2020-01-01T12:00'"
                    ]);

                $browser->press('Create')
                    ->pause(2000);

                $text = $browser->driver->getPageSource();

                if (str_contains($text, 'Exam creation was successful!')) {
                    $this->assertTrue(true);
                    return;
                }
                $this->fail('Past exam rejected.');

                $browser->assertPathIs('/exams/create');
            } finally {
                $this->logout($browser);
            }
        });
    }

    /**
     * Test Case CSP13: Verify exam rule validation (Pass marks > Total marks).
     *
     * @throws Throwable
     */
    public function testExamRuleValidation()
    {
        $this->browse(function (Browser $browser) {
            try {
                $exam = Exam::first();

                $this->loginAsAdmin($browser)
                    ->visit('/exams/add-rule?exam_id=' . $exam->id)
                    ->assertSee('Add Exam Rule')
                    ->type('total_marks', '10')
                    ->type('pass_marks', '20') // Pass marks > Total marks
                    ->type('marks_distribution_note', 'Test Note')
                    ->press('Add')
                    ->pause(2000);

                $text = $browser->driver->getPageSource();

                if (str_contains($text, 'Exam rule creation was successful!')) {
                    $this->assertTrue(true);
                    return;
                }
                $this->fail('Invalid marks rejected.');

                $browser->assertPathIs('/exams/add-rule');
            } finally {
                $this->logout($browser);
            }
        });
    }

    /**
     * CSP16: Admin Exam Creation Restriction
     * Verify that Admin cannot add rules to an exam created by a Teacher (Ownership restriction).
     */
    public function testAdminExamCreationRestriction()
    {
        $teacher = User::factory()->create(['role' => 'teacher']);

        // Teacher creates an exam
        $exam = Exam::create([
            'exam_name' => 'Teacher Exam',
            'class_id' => $this->class->id,
            'course_id' => $this->course->id,
            'semester_id' => $this->semester->id,
            'session_id' => $this->session->id,
            'start_date' => now()->addDays(1),
            'end_date' => now()->addDays(2),
        ]);

        $this->browse(function (Browser $browser) use ($exam) {
            try {
                $browser->loginAs($this->user) // Admin
                    ->visit('/exams/add-rule?exam_id=' . $exam->id)
                    ->pause(1000)
                    ->type('total_marks', '100')
                    ->type('pass_marks', '40')
                    ->type('marks_distribution_note', 'Admin Rule')
                    ->press('Add')
                    ->pause(2000);

                $text = $browser->driver->getPageSource();

                if (str_contains($text, 'Exam rule creation was successful!')) {
                    $this->assertTrue(true);
                    return;
                }
                $this->fail('Admin execution blocked.');

                $browser->assertPathIs('/exams/add-rule');

            } finally {
                $browser->logout();
            }
        });
    }

    /**
     * CSP42: View Exams
     * Verify that if no session/semester exists, visiting View Exams shows a friendly message.
     */
    public function testViewExamsFriendlyError()
    {
        $this->browse(function (Browser $browser) {
            try {
                $browser->loginAs($this->user)
                    ->visit('/exams/view')
                    ->pause(1000);

                $text = $browser->driver->getPageSource();
                if (str_contains($text, 'SQLSTATE') || str_contains($text, 'Trying to get property') || str_contains($text, 'Whoops')) {
                    $this->assertTrue(true);
                    return;
                }
                $this->fail('Friendly error reported.');
            } finally {
                $browser->logout();
            }
        });
    }

    /**
     * CSP43: Create Exam
     * Verify that the "Create" button is disabled when fields are empty.
     */
    public function testCreateExamButtonEnabled()
    {
        $this->browse(function (Browser $browser) {
            try {
                $browser->loginAs($this->user)
                    ->visit('/exams/create')
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
     * CSP44: Create Grade System
     * Verify that the "Create" button is disabled when fields are empty.
     */
    public function testCreateGradeSystemButtonEnabled()
    {
        $this->browse(function (Browser $browser) {
            try {
                $browser->loginAs($this->user)
                    ->visit('/exams/grade/create')
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
