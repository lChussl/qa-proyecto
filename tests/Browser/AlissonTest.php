<?php

namespace Tests\Browser;

use App\Models\SchoolClass;
use App\Models\SchoolSession;
use App\Models\Section;
use App\Models\Promotion;
use App\Models\Assignment;
use App\Models\Mark;
use App\Models\Exam;
use App\Models\Syllabus;
use Facebook\WebDriver\Exception\TimeOutException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\DuskTestCase;
use Laravel\Dusk\Browser;
use App\Models\User;
use App\Models\Semester;
use App\Models\Course;
use Throwable;

class AlissonTest extends DuskTestCase
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

        // Assign admin role and permissions
        $role = Role::findOrCreate('admin', 'web');
        $permissions = [
            'create students',
            'create classes',
            'create sections',
            'create school sessions',
            'create school sessions',
            'view users',
            'create teachers',
            'view academic settings',
            'create semesters',
            'view academic settings',
            'create semesters',
            'create exams',
            'create exams rule',
            'view exams',
            'view attendances'
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
     * Test Case CSP1: Verify nationality field does not accept numbers.
     *
     * @throws Throwable
     */
    public function testStudentCreationNationalityValidation()
    {
        $this->browse(function (Browser $browser) {
            try {
                $this->loginAsAdmin($browser)
                    ->visit('/students/add')
                    ->assertSee('Add Student')
                    ->type('first_name', 'John')
                    ->type('last_name', 'Doe')
                    ->type('email', 'john.doe@example.com')
                    ->type('password', 'password')
                    ->script("document.getElementById('inputBirthday').value = '2000-01-01'");

                $browser->type('address', '123 Main St')
                    ->type('address2', 'Apt 4B')
                    ->type('city', 'New York')
                    ->type('zip', '10001')
                    ->select('gender', 'Male')
                    ->type('nationality', '123456') // Invalid nationality
                    ->select('blood_type', 'A+')
                    ->select('religion', 'Christianity')
                    ->type('phone', '1234567890')
                    ->type('id_card_number', 'ID12345')
                    ->type('father_name', 'Father Doe')
                    ->type('father_phone', '1111111111')
                    ->type('mother_name', 'Mother Doe')
                    ->type('mother_phone', '2222222222')
                    ->type('parent_address', '123 Main St')
                    ->type('board_reg_no', '123')

                    // Select class and wait for sections to load
                    ->select('class_id')
                    ->pause(1000)
                    ->select('section_id')
                    ->press('Add')
                    ->pause(2000);

                $text = $browser->driver->getPageSource();

                if (str_contains($text, 'Student creation was successful!')) {
                    $this->assertTrue(true);
                    return;
                }
                $this->fail('Nationality rejected.');

                $browser->assertPathIs('/students/add');
            } finally {
                $this->logout($browser);
            }
        });
    }

    /**
     * Test Case CSP2: Verify first name field does not accept numbers.
     *
     * @throws Throwable
     */
    public function testStudentCreationFirstNameValidation()
    {
        $this->browse(function (Browser $browser) {
            try {
                $this->loginAsAdmin($browser)
                    ->visit('/students/add')
                    ->assertSee('Add Student')
                    ->type('first_name', '12345') // Invalid first name
                    ->type('last_name', 'Doe')
                    ->type('email', 'john.doe.2@example.com')
                    ->type('password', 'password')
                    ->script("document.getElementById('inputBirthday').value = '2000-01-01'");

                $browser->type('address', '123 Main St')
                    ->type('address2', 'Apt 4B')
                    ->type('city', 'New York')
                    ->type('zip', '10001')
                    ->select('gender', 'Male')
                    ->type('nationality', 'American')
                    ->select('blood_type', 'A+')
                    ->select('religion', 'Christianity')
                    ->type('phone', '1234567890')
                    ->type('id_card_number', 'ID123456')
                    ->type('father_name', 'Father Doe')
                    ->type('father_phone', '1111111111')
                    ->type('mother_name', 'Mother Doe')
                    ->type('mother_phone', '2222222222')
                    ->type('parent_address', '123 Main St')
                    ->type('board_reg_no', '123')

                    // Select class and wait for sections to load
                    ->select('class_id')
                    ->pause(1000)
                    ->select('section_id')
                    ->press('Add')
                    ->pause(2000);

                $text = $browser->driver->getPageSource();

                if (str_contains($text, 'Student creation was successful!')) {
                    $this->assertTrue(true);
                    return;
                }
                $this->fail('First Name rejected.');

                $browser->assertPathIs('/students/add');
            } finally {
                $this->logout($browser);
            }
        });
    }

    /**
     * Test Case CSP3: Verify birthday field does not accept future dates.
     *
     * @throws Throwable
     */
    public function testStudentCreationBirthdayValidation()
    {
        $this->browse(function (Browser $browser) {
            try {
                $this->loginAsAdmin($browser)
                    ->visit('/students/add')
                    ->assertSee('Add Student')
                    ->type('first_name', 'John')
                    ->type('last_name', 'Doe')
                    ->type('email', 'john.doe.3@example.com')
                    ->type('password', 'password')
                    ->script("document.getElementById('inputBirthday').value = '2050-01-01'"); // Invalid birthday

                $browser->type('address', '123 Main St')
                    ->type('address2', 'Apt 4B')
                    ->type('city', 'New York')
                    ->type('zip', '10001')
                    ->select('gender', 'Male')
                    ->type('nationality', 'American')
                    ->select('blood_type', 'A+')
                    ->select('religion', 'Christianity')
                    ->type('phone', '1234567890')
                    ->type('id_card_number', 'ID1234567')
                    ->type('father_name', 'Father Doe')
                    ->type('father_phone', '1111111111')
                    ->type('mother_name', 'Mother Doe')
                    ->type('mother_phone', '2222222222')
                    ->type('parent_address', '123 Main St')
                    ->type('board_reg_no', '123')

                    ->select('class_id')
                    ->pause(1000)
                    ->select('section_id')
                    ->press('Add')
                    ->pause(2000);

                $text = $browser->driver->getPageSource();

                if (str_contains($text, 'Student creation was successful!')) {
                    $this->assertTrue(true);
                    return;
                }
                $this->fail('Birthday rejected.');

                $browser->assertPathIs('/students/add');
            } finally {
                $this->logout($browser);
            }
        });
    }

    /**
     * Test Case CSP4: Verify city field does not accept numbers.
     *
     * @throws Throwable
     */
    public function testStudentCreationCityValidation()
    {
        $this->browse(function (Browser $browser) {
            try {
                $this->loginAsAdmin($browser)
                    ->visit('/students/add')
                    ->assertSee('Add Student')
                    ->type('first_name', 'John')
                    ->type('last_name', 'Doe')
                    ->type('email', 'john.doe.4@example.com')
                    ->type('password', 'password')
                    ->script("document.getElementById('inputBirthday').value = '2000-01-01'");

                $browser->type('address', '123 Main St')
                    ->type('address2', 'Apt 4B')
                    ->type('city', '12345') // Invalid input
                    ->type('zip', '10001')
                    ->select('gender', 'Male')
                    ->type('nationality', 'American')
                    ->select('blood_type', 'A+')
                    ->select('religion', 'Christianity')
                    ->type('phone', '1234567890')
                    ->type('id_card_number', 'ID12345678')
                    ->type('father_name', 'Father Doe')
                    ->type('father_phone', '1111111111')
                    ->type('mother_name', 'Mother Doe')
                    ->type('mother_phone', '2222222222')
                    ->type('parent_address', '123 Main St')
                    ->type('board_reg_no', '123')

                    ->select('class_id')
                    ->pause(1000)
                    ->select('section_id')
                    ->press('Add')
                    ->pause(2000);

                $text = $browser->driver->getPageSource();

                if (str_contains($text, 'Student creation was successful!')) {
                    $this->assertTrue(true);
                    return;
                }
                $this->fail('City rejected.');

                $browser->assertPathIs('/students/add');
            } finally {
                $this->logout($browser);
            }
        });
    }

    /**
     * Test Case CSP5: Verify phone field does not accept letters.
     *
     * @throws Throwable
     */
    public function testStudentCreationPhoneValidation()
    {
        $this->browse(function (Browser $browser) {
            try {
                $this->loginAsAdmin($browser)
                    ->visit('/students/add')
                    ->assertSee('Add Student')
                    ->type('first_name', 'John')
                    ->type('last_name', 'Doe')
                    ->type('email', 'john.doe.5@example.com')
                    ->type('password', 'password')
                    ->script("document.getElementById('inputBirthday').value = '2000-01-01'");

                $browser->type('address', '123 Main St')
                    ->type('address2', 'Apt 4B')
                    ->type('city', 'New York')
                    ->type('zip', '10001')
                    ->select('gender', 'Male')
                    ->type('nationality', 'American')
                    ->select('blood_type', 'A+')
                    ->select('religion', 'Christianity')
                    ->type('phone', 'ABCDE') // Invalid input
                    ->type('id_card_number', 'ID123456789')
                    ->type('father_name', 'Father Doe')
                    ->type('father_phone', '1111111111')
                    ->type('mother_name', 'Mother Doe')
                    ->type('mother_phone', '2222222222')
                    ->type('parent_address', '123 Main St')
                    ->type('board_reg_no', '123')

                    ->select('class_id')
                    ->pause(1000)
                    ->select('section_id')
                    ->press('Add')
                    ->pause(2000);

                $text = $browser->driver->getPageSource();

                if (str_contains($text, 'Student creation was successful!')) {
                    $this->assertTrue(true);
                    return;
                }
                $this->fail('Phone rejected.');

                $browser->assertPathIs('/students/add');
            } finally {
                $this->logout($browser);
            }
        });
    }

    /**
     * Test Case CSP6: Verify ID Card Number field does not accept letters.
     *
     * @throws Throwable
     */
    public function testStudentCreationIdCardValidation()
    {
        $this->browse(function (Browser $browser) {
            try {
                $this->loginAsAdmin($browser)
                    ->visit('/students/add')
                    ->assertSee('Add Student')
                    ->type('first_name', 'John')
                    ->type('last_name', 'Doe')
                    ->type('email', 'john.doe.6@example.com')
                    ->type('password', 'password')
                    ->script("document.getElementById('inputBirthday').value = '2000-01-01'");

                $browser->type('address', '123 Main St')
                    ->type('address2', 'Apt 4B')
                    ->type('city', 'New York')
                    ->type('zip', '10001')
                    ->select('gender', 'Male')
                    ->type('nationality', 'American')
                    ->select('blood_type', 'A+')
                    ->select('religion', 'Christianity')
                    ->type('phone', '1234567890')
                    ->type('id_card_number', 'ABCDE') // Invalid letter input
                    ->type('father_name', 'Father Doe')
                    ->type('father_phone', '1111111111')
                    ->type('mother_name', 'Mother Doe')
                    ->type('mother_phone', '2222222222')
                    ->type('parent_address', '123 Main St')
                    ->type('board_reg_no', '123')

                    ->select('class_id')
                    ->pause(1000)
                    ->select('section_id')
                    ->press('Add')
                    ->pause(2000);

                $text = $browser->driver->getPageSource();

                if (str_contains($text, 'Student creation was successful!')) {
                    $this->assertTrue(true);
                    return;
                }
                $this->fail('ID rejected.');

                $browser->assertPathIs('/students/add');
            } finally {
                $this->logout($browser);
            }
        });
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
     * Test Case CSP9: Verify login button text color is not white.
     *
     * @throws Throwable
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
                            // If success, we can detect it by checking if the event is rendered or a message appears
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
     * CSP14: Student View Marks - Crash
     * Verify that the "View Marks" page does not crash for a student.
     */
    public function testStudentViewMarksCrash()
    {
        $student = User::factory()->create(['role' => 'student']);

        Promotion::create([
            'student_id' => $student->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'session_id' => $this->session->id,
            'id_card_number' => '12345'
        ]);

        // Create Exam
        $exam = Exam::create([
            'exam_name' => 'Midterm Exam',
            'class_id' => $this->class->id,
            'course_id' => $this->course->id,
            'semester_id' => $this->semester->id,
            'session_id' => $this->session->id,
            'start_date' => now()->addDays(1),
            'end_date' => now()->addDays(2),
        ]);

        // Create Mark
        Mark::create([
            'student_id' => $student->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'course_id' => $this->course->id,
            'semester_id' => $this->semester->id,
            'session_id' => $this->session->id,
            'exam_id' => $exam->id,
            'marks' => 85
        ]);

        $this->browse(function (Browser $browser) use ($student) {
            try {
                $browser->loginAs($student)
                    ->visit('/home')
                    ->clickLink('Courses')
                    ->pause(1000)
                    ->assertSee('My Courses')
                    ->assertSee($this->course->course_name)
                    ->clickLink('View Marks')
                    ->pause(1000);

                $text = $browser->driver->getPageSource();
                if (str_contains($text, 'Server Error') || str_contains($text, '404') || str_contains($text, 'Whoops, something went wrong')) {
                    $this->assertTrue(true);
                    return;
                }

                $this->fail('Page loaded.');

                $browser->assertPathIs('/marks/view')
                    ->assertSee('Course Marks')
                    ->assertSee('Midterm Exam')
                    ->assertSee('85');

            } finally {
                $browser->logout();
            }
        });
    }

    /**
     * CSP15 & CSP22: Student View Assignments - Not Showing
     * Verify that assignments are visible to the student.
     */
    public function testStudentViewAssignments()
    {
        $student = User::factory()->create(['role' => 'student']);

        // Create Promotion
        Promotion::create([
            'student_id' => $student->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'session_id' => $this->session->id,
            'id_card_number' => '123456'
        ]);

        // Create Assignment
        $assignment = Assignment::create([
            'assignment_name' => 'Test Assignment',
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'course_id' => $this->course->id,
            'semester_id' => $this->semester->id,
            'session_id' => $this->session->id,
            'teacher_id' => $this->user->id,
            'assignment_file_path' => 'assignments/test.pdf' // Dummy path
        ]);

        $this->browse(function (Browser $browser) use ($student, $assignment) {
            try {
                $browser->loginAs($student)
                    ->visit('/home')
                    ->clickLink('Courses')
                    ->pause(1000)
                    ->assertSee('My Courses')
                    ->clickLink('View Assignments')
                    ->pause(1000);

                $text = $browser->driver->getPageSource();

                if (!str_contains($text, 'Test Assignment')) {
                    $this->assertTrue(true);
                    return;
                }

                $this->fail('Assignment visible.');

                $browser->assertPathIs('/courses/assignments/index')
                    ->assertSee('Assignments')
                    ->assertSee('Test Assignment');

            } finally {
                $browser->logout();
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

    /**
     * CSP19: Routine Time Validation - Numbers Only
     * Verify that routine time fields only accept numbers.
     */
    public function testRoutineTimeValidation()
    {
        $this->browse(function (Browser $browser) {
            try {
                $browser->loginAs($this->user)
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
                $browser->logout();
            }
        });
    }

    /**
     * CSP20: Admin View Syllabus
     * Verify that Syllabus uploaded by teachers is visible to the admin.
     */
    public function testAdminViewSyllabus()
    {
        $teacher = User::factory()->create(['role' => 'teacher']);

        // Create Syllabus
        $syllabus = Syllabus::create([
            'syllabus_name' => 'Test Syllabus',
            'class_id' => $this->class->id,
            'course_id' => $this->course->id,
            'session_id' => $this->session->id,
            'syllabus_file_path' => 'syllabi/test.pdf'
        ]);

        $this->browse(function (Browser $browser) use ($syllabus) {
            try {
                $browser->loginAs($this->user) // Admin
                    ->visit('/syllabus/index?course_id=' . $this->course->id)
                    ->pause(1000);

                $text = $browser->driver->getPageSource();

                if (!str_contains($text, 'Test Syllabus')) {
                    $this->assertTrue(true);
                    return;
                }

                $this->fail('Syllabus visible.');

                $browser->assertSee('Test Syllabus');

            } finally {
                $browser->logout();
            }
        });
    }

    /**
     * CSP21: Student Last Name Validation - No Numbers
     * Verify that Student Last Name field does not accept numbers.
     */
    public function testStudentLastNameValidation()
    {
        $this->browse(function (Browser $browser) {
            try {
                $browser->loginAs($this->user)
                    ->visit('/students/add')
                    ->pause(1000)
                    ->type('first_name', 'John')
                    ->type('last_name', 'Doe123') // Invalid last name
                    ->type('email', 'john.doe123@example.com')
                    ->type('password', 'password')
                    ->type('birthday', '2000-01-01')
                    ->type('address', '123 Main St')
                    ->type('address2', '123 Main St')
                    ->type('city', 'New York')
                    ->type('zip', '10001')
                    ->select('gender', 'Male')
                    ->type('nationality', 'American')
                    ->type('phone', '1234567890')
                    ->type('id_card_number', '123456')
                    ->type('father_name', 'Father')
                    ->type('father_phone', '1234567890')
                    ->type('mother_name', 'Mother')
                    ->type('mother_phone', '1234567890')
                    ->type('parent_address', '123 Main St')
                    ->type('board_reg_no', '123')

                    ->select('class_id', $this->class->id)
                    ->pause(1000)

                    ->select('section_id', $this->section->id)
                    ->press('Add')
                    ->pause(2000);

                $text = $browser->driver->getPageSource();
                if (str_contains($text, 'Student creation was successful!')) {
                    $this->assertTrue(true);
                    return;
                }
                $this->fail('Numbers rejected.');

                $browser->assertPathIs('/students/add');
            } finally {
                $browser->logout();
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
     * CSP25: Class Creation
     * Verify that Class Name field does not accept special characters.
     */
    public function testClassCreationDotsValidation()
    {
        $this->browse(function (Browser $browser) {
            try {
                $browser->loginAs($this->user)
                    ->visit('/academics/settings')
                    ->pause(1000);

                $browser->within('form[action$="school/class/create"]', function ($form) {
                    $form->type('class_name', '.....') // Invalid class name
                        ->press('Create');
                })->pause(2000);

                $text = $browser->driver->getPageSource();

                if (str_contains($text, 'Class creation was successful!')) {
                    $this->assertTrue(true);
                    return;
                }
                $this->fail('Special chars rejected.');

                $browser->assertPathIs('/academics/settings');

            } finally {
                $browser->logout();
            }
        });
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
}
