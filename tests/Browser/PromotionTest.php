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

class PromotionTest extends DuskTestCase
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
            'create promotions',
            'read promotions',
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
}
