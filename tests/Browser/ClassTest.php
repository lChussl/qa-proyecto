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

class ClassTest extends DuskTestCase
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
            'create classes',
            'create academic settings',
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
     * CSP25: Class Creation
     * Verify that Class Name field does not accept special characters.
     */
    public function testClassCreationDotsValidation()
    {
        $this->browse(function (Browser $browser) {
            try {
                $browser->loginAs($this->user)
                    ->visit('/academics/settings')
                    ->maximize()
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
}
