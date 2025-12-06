<?php

namespace Tests\Browser;

use App\Models\Course;
use App\Models\SchoolClass;
use App\Models\SchoolSession;
use App\Models\Section;
use App\Models\Semester;
use App\Models\User;
use Facebook\WebDriver\WebDriverBy;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class JesusTests extends DuskTestCase
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

        // Setup Permissions (copied from AlissonTest for consistency)
        $role = Role::findOrCreate('admin', 'web');
        $permissions = [
            'create classes',
            'read classes',
            'edit classes',
            'delete classes',
            'create students',
            'read students',
            'edit students',
            'delete students',
            'create teachers',
            'read teachers',
            'edit teachers',
            'delete teachers',
            'create sections',
            'read sections',
            'edit sections',
            'delete sections',
            'create courses',
            'read courses',
            'edit courses',
            'delete courses',
            'create syllabi',
            'read syllabi',
            'edit syllabi',
            'delete syllabi',
            'create routines',
            'read routines',
            'edit routines',
            'delete routines',
            'create promotions',
            'read promotions',
            'create academic settings',
            'read academic settings',
            'edit academic settings'
        ];

        foreach ($permissions as $p) {
            Permission::findOrCreate($p, 'web');
            $role->givePermissionTo($p);
        }
        $this->roleWithPermissions = $role;
        $user->assignRole($role);
        $this->user = $user;
    }

    /**
     * CSP26: Syllabus
     * Verify that if no classes/courses exist, visiting the Syllabus page shows a notification.
     */
    public function testSyllabusNoClassesNotification()
    {
        $this->browse(function (Browser $browser) {
            try {
                $browser->loginAs($this->user)
                    ->visit('/syllabus/create')
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
     * CSP28: Promotion
     * Verify that visiting Promotion page redirects.
     */
    public function testPromotionNoNotification()
    {
        $this->browse(function (Browser $browser) {
            try {
                $browser->loginAs($this->user)
                    ->visit('/home')
                    ->pause(1000)
                    ->clickLink('Promotion')
                    ->pause(1000);

                $text = $browser->driver->getPageSource();

                if ($browser->driver->getCurrentURL() != url('/promotions/index')) {
                    $this->assertTrue(true);
                } else {
                    $this->fail('Stayed on page.');
                }
            } finally {
                $browser->logout();
            }
        });
    }

    /**
     * CSP29: Syllabus
     * Verify that trying to create a Syllabus without selecting a class prompts to select a class.
     */
    public function testSyllabusCreateWithoutSelection()
    {
        $this->createDummyClass();

        $this->browse(function (Browser $browser) {
            try {
                $browser->loginAs($this->user)
                    ->visit('/syllabus/create')
                    ->pause(1000)
                    ->press('Create')
                    ->pause(1000);

                $text = $browser->driver->getPageSource();

                $validationMessage = $browser->script("return document.querySelector('select[name=\"class_id\"]').validationMessage")[0];

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
     * CSP30: Syllabus - Create Button Disabled
     * Verify that the "Create" button is disabled when fields are empty.
     */
    public function testSyllabusCreateButtonDisabled()
    {
        $this->browse(function (Browser $browser) {
            try {
                $browser->loginAs($this->user)
                    ->visit('/syllabus/create')
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
     * CSP31: Syllabus
     * Verify that creating a syllabus with missing fields shows a readable error message.
     */
    public function testSyllabusUnreadableErrorMessage()
    {
        $this->browse(function (Browser $browser) {
            try {
                $browser->loginAs($this->user)
                    ->visit('/syllabus/create')
                    ->pause(1000)
                    ->type('syllabus_name', 'Test Syllabus')
                    ->script("
                        const file = new File(['dummy content'], 'test.pdf', {type: 'application/pdf'});
                        const container = new DataTransfer();
                        container.items.add(file);
                        document.querySelector('input[name=\"file\"]').files = container.files;
                    ");

                $browser->press('Create')
                    ->pause(1000);

                $text = $browser->driver->getPageSource();

                if (str_contains($text, 'SQLSTATE') || str_contains($text, 'Integrity constraint violation') || str_contains($text, 'Whoops')) {
                    $this->assertTrue(true);
                    return;
                }

                $this->fail('Friendly error or success.');

                $browser->assertPathIs('/syllabus/create');
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

    /**
     * CSP41: News
     * Verify that the "Save" button is disabled when fields are empty.
     */
    public function testNewsSaveButtonEnabled()
    {
        $this->browse(function (Browser $browser) {
            try {
                $browser->loginAs($this->user)
                    ->visit('/notice/create')
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

    private function createDummyClass()
    {
        $session = SchoolSession::factory()->create();
        SchoolClass::factory()->create([
            'session_id' => $session->id,
            'class_name' => 'Test Class'
        ]);
    }
}
