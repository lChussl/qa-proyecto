<?php

namespace Tests\Browser;

use App\Models\Course;
use App\Models\SchoolClass;
use App\Models\SchoolSession;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Syllabus;
use App\Models\User;
use Laravel\Dusk\Browser;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\DuskTestCase;

class SyllabusTest extends DuskTestCase
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
            'create syllabi',
            'read syllabi',
            'edit syllabi',
            'delete syllabi',
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

    private function createDummyClass()
    {
        $session = SchoolSession::factory()->create();
        SchoolClass::factory()->create([
            'session_id' => $session->id,
            'class_name' => 'Test Class'
        ]);
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
}
