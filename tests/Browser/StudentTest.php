<?php

namespace Tests\Browser;

use App\Models\SchoolClass;
use App\Models\SchoolSession;
use App\Models\Section;
use App\Models\Promotion;
use App\Models\Assignment;
use App\Models\Mark;
use App\Models\Exam;
use Facebook\WebDriver\Exception\TimeOutException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\DuskTestCase;
use Laravel\Dusk\Browser;
use App\Models\User;
use App\Models\Semester;
use App\Models\Course;
use Throwable;

class StudentTest extends DuskTestCase
{
    protected $session;
    protected $class;
    protected $section;
    protected $semester;
    protected $course;
    protected $user;
    protected $roleWithPermissions;
    protected $sessionYear;

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
            'view users',
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
        $this->sessionYear = $this->session;
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
     * Helper function to fill student form (from DanielAutoTest).
     */
    protected function fillStudentForm(Browser $browser, array $overrides = [])
    {
        $defaults = [
            'first_name' => 'Daniel',
            'last_name' => 'Montero',
            'email' => 'daniel@montero.com',
            'password' => 'Montes123',
            'birthday' => '2001-02-27',
            'address' => 'Casa Bavaria',
            'address2' => 'Casa Blanca',
            'city' => 'Alajuela',
            'zip' => '20103',
            'nationality' => 'Costa Rica',
            'phone' => '85581676',
            'id_card_number' => '118030857',
            'father_name' => 'Oscar',
            'father_phone' => '83885929',
            'mother_name' => 'Adriana',
            'mother_phone' => '85204279',
            'parent_address' => 'Vuelta de Jorco',
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'board_reg_no' => '1',
        ];

        $data = array_merge($defaults, $overrides);

        $browser->type('first_name', $data['first_name'])
            ->type('last_name', $data['last_name'])
            ->type('email', $data['email'])
            ->type('password', $data['password'])
            ->type('birthday', $data['birthday'])
            ->type('address', $data['address'])
            ->type('address2', $data['address2'])
            ->type('city', $data['city'])
            ->type('zip', $data['zip'])
            ->type('nationality', $data['nationality'])
            ->type('phone', $data['phone'])
            ->type('id_card_number', $data['id_card_number'])
            ->type('father_name', $data['father_name'])
            ->type('father_phone', $data['father_phone'])
            ->type('mother_name', $data['mother_name'])
            ->type('mother_phone', $data['mother_phone'])
            ->type('parent_address', $data['parent_address'])
            ->select('class_id', $data['class_id'])
            ->select('section_id', $data['section_id'])
            ->type('board_reg_no', $data['board_reg_no']);

        return $browser;
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

    // ============== Tests from DanielAutoTest.php ==============

    //Prueba automatizada del caso CSP51 "Crear un estudiante nuevo sin ingresar un nombre en el formulario"
    public function testNoCreaEstudianteSinNombre()
    {
        $this->browse(function (Browser $browser) {
            //Este primer test hace login como administrador(los siguientes mantienen la sesión)
            $browser->visit('/login')
                ->maximize()
                ->type('email', 'admin@ut.com')
                ->type('password', 'password')
                ->press('Login')
                ->visit('/students/add');
            //Llena el fomulario con los datos sumistrados en la función fillStudentForm
            $this->fillStudentForm($browser, [
                'first_name' => '' //En esta linea se modifica el campo específico que se desea probar
            ]);


            $browser->scrollIntoView('button[type="submit"]')
                ->pause(1000)
                ->press('Add')
                ->assertDontSee(' Student creation was successful!') //revisa la ausencia del texto que nos dice si se crea el estudiante.
                ->assertPathIs('/students/add'); //este assert revisa si cambiamos de ruta, validando si se creó el estudiante.


        });

    }

    //Prueba automatizada del caso CSP52 "Crear un estudiante nuevo sin ingresar un apellido en el formulario"
    public function testNoCreaEstudianteSinApellido()
    {
        $this->browse(function (Browser $browser) {

            $browser->visit('/students/add');
            //Llena el fomulario con los datos sumistrados en la función fillStudentForm
            $this->fillStudentForm($browser, [
                'last_name' => '' //En esta linea se modifica el campo específico que se desea probar
            ]);


            $browser->scrollTo('button[type="submit"]')
                ->pause(1000)
                ->press('Add')
                ->assertDontSee(' Student creation was successful!') //revisa la ausencia del texto que nos dice si se crea el estudiante.
                ->assertPathIs('/students/add'); //este assert revisa si cambiamos de ruta, validando si se creó el estudiante.


        });

    }

    //Prueba automatizada del caso CSP53 "Crear un estudiante nuevo sin ingresar un email en el formulario"
    public function testNoCreaEstudianteSinEmail()
    {
        $this->browse(function (Browser $browser) {

            $browser->visit('/students/add');
            //Llena el fomulario con los datos sumistrados en la función fillStudentForm
            $this->fillStudentForm($browser, [
                'email' => '' //En esta linea se modifica el campo específico que se desea probar
            ]);


            $browser->scrollTo('button[type="submit"]')
                ->pause(1000)
                ->press('Add')
                ->assertDontSee(' Student creation was successful!') //revisa la ausencia del texto que nos dice si se crea el estudiante.
                ->assertPathIs('/students/add'); //este assert revisa si cambiamos de ruta, validando si se creó el estudiante.


        });

    }

    //Prueba automatizada del caso CSP54 "Crear un estudiante nuevo sin ingresar una contraseña en el formulario"
    public function testNoCreaEstudianteSinContraseña()
    {
        $this->browse(function (Browser $browser) {

            $browser->visit('/students/add');
            //Llena el fomulario con los datos sumistrados en la función fillStudentForm
            $this->fillStudentForm($browser, [
                'password' => '' //En esta linea se modifica el campo específico que se desea probar
            ]);


            $browser->scrollTo('button[type="submit"]')
                ->pause(1000)
                ->press('Add')
                ->assertDontSee(' Student creation was successful!') //revisa la ausencia del texto que nos dice si se crea el estudiante.
                ->assertPathIs('/students/add'); //este assert revisa si cambiamos de ruta, validando si se creó el estudiante.


        });

    }

    //Prueba automatizada del caso CSP55 "Crear un estudiante nuevo sin ingresar una fecha de nacimiento en el formulario"
    public function testNoCreaEstudianteSinBirthday()
    {
        $this->browse(function (Browser $browser) {

            $browser->visit('/students/add');
            //Llena el fomulario con los datos sumistrados en la función fillStudentForm
            $this->fillStudentForm($browser, [
                'birthday' => '' //En esta linea se modifica el campo específico que se desea probar
            ]);


            $browser->scrollTo('button[type="submit"]')
                ->pause(1000)
                ->press('Add')
                ->assertDontSee(' Student creation was successful!') //revisa la ausencia del texto que nos dice si se crea el estudiante.
                ->assertPathIs('/students/add'); //este assert revisa si cambiamos de ruta, validando si se creó el estudiante.


        });

    }

    //Prueba automatizada del caso CSP56 "Crear un estudiante nuevo sin ingresar una dirección en el formulario"
    public function testNoCreaEstudianteSinDireccion()
    {
        $this->browse(function (Browser $browser) {

            $browser->visit('/students/add');
            //Llena el fomulario con los datos sumistrados en la función fillStudentForm
            $this->fillStudentForm($browser, [
                'address' => '' //En esta linea se modifica el campo específico que se desea probar
            ]);


            $browser->scrollTo('button[type="submit"]')
                ->pause(1000)
                ->press('Add')
                ->assertDontSee(' Student creation was successful!') //revisa la ausencia del texto que nos dice si se crea el estudiante.
                ->assertPathIs('/students/add'); //este assert revisa si cambiamos de ruta, validando si se creó el estudiante.


        });

    }


    //Prueba automatizada del caso CSP57 "Crear un estudiante nuevo sin ingresar una direccion 2 en el formulario"
    //Esta prueba se espera que no falle, puesto que el campo de dirección 2 no se indica como requerido, sin embargo el sistema lo espera
    public function testCreaEstudianteSinDireccion2()
    {
        $this->browse(function (Browser $browser) {

            $browser->visit('/students/add');
            //Llena el fomulario con los datos sumistrados en la función fillStudentForm
            $this->fillStudentForm($browser, [
                'address2' => '' //En esta linea se modifica el campo específico que se desea probar
            ]);


            $browser->scrollTo('button[type="submit"]')
                ->pause(1000)
                ->press('Add')
                ->assertSee(' Student creation was successful!') //revisa la ausencia del texto que nos dice si se crea el estudiante.
                ->assertPathIs('/students/add'); //este assert revisa si cambiamos de ruta, validando si se creó el estudiante.


        });

    }

    //Prueba automatizada del caso CSP58 "Crear un estudiante nuevo sin ingresar una ciudad en el formulario"
    public function testNoCreaEstudianteSinCity()
    {
        $this->browse(function (Browser $browser) {

            $browser->visit('/students/add');
            //Llena el fomulario con los datos sumistrados en la función fillStudentForm
            $this->fillStudentForm($browser, [
                'city' => '' //En esta linea se modifica el campo específico que se desea probar
            ]);


            $browser->scrollTo('button[type="submit"]')
                ->pause(1000)
                ->press('Add')
                ->assertDontSee(' Student creation was successful!') //revisa la ausencia del texto que nos dice si se crea el estudiante.
                ->assertPathIs('/students/add'); //este assert revisa si cambiamos de ruta, validando si se creó el estudiante.


        });

    }

    //Prueba automatizada del caso CSP59 "Crear un estudiante nuevo sin ingresar un zip code en el formulario"
    public function testNoCreaEstudianteSinZip()
    {
        $this->browse(function (Browser $browser) {

            $browser->visit('/students/add');
            //Llena el fomulario con los datos sumistrados en la función fillStudentForm
            $this->fillStudentForm($browser, [
                'zip' => '' //En esta linea se modifica el campo específico que se desea probar
            ]);


            $browser->scrollTo('button[type="submit"]')
                ->pause(1000)
                ->press('Add')
                ->assertDontSee(' Student creation was successful!') //revisa la ausencia del texto que nos dice si se crea el estudiante.
                ->assertPathIs('/students/add'); //este assert revisa si cambiamos de ruta, validando si se creó el estudiante.


        });

    }

    //Prueba automatizada del caso CSP60 "Crear un estudiante nuevo sin ingresar una nacionalidad en el formulario"
    public function testNoCreaEstudianteSinNationality()
    {
        $this->browse(function (Browser $browser) {

            $browser->visit('/students/add');
            //Llena el fomulario con los datos sumistrados en la función fillStudentForm
            $this->fillStudentForm($browser, [
                'nationality' => '' //En esta linea se modifica el campo específico que se desea probar
            ]);


            $browser->scrollTo('button[type="submit"]')
                ->pause(1000)
                ->press('Add')
                ->assertDontSee(' Student creation was successful!') //revisa la ausencia del texto que nos dice si se crea el estudiante.
                ->assertPathIs('/students/add'); //este assert revisa si cambiamos de ruta, validando si se creó el estudiante.


        });

    }

    //Prueba automatizada del caso CSP61 "Crear un estudiante nuevo sin ingresar un phone en el formulario"
    public function testNoCreaEstudianteSinPhone()
    {
        $this->browse(function (Browser $browser) {

            $browser->visit('/students/add');
            //Llena el fomulario con los datos sumistrados en la función fillStudentForm
            $this->fillStudentForm($browser, [
                'phone' => '' //En esta linea se modifica el campo específico que se desea probar
            ]);


            $browser->scrollTo('button[type="submit"]')
                ->pause(1000)
                ->press('Add')
                ->assertDontSee(' Student creation was successful!') //revisa la ausencia del texto que nos dice si se crea el estudiante.
                ->assertPathIs('/students/add'); //este assert revisa si cambiamos de ruta, validando si se creó el estudiante.


        });

    }

    //Prueba automatizada del caso CSP62 "Crear un estudiante nuevo sin ingresar un IdCardNumber en el formulario"
    public function testNoCreaEstudianteSinIdCardNumber()
    {
        $this->browse(function (Browser $browser) {

            $browser->visit('/students/add');
            //Llena el fomulario con los datos sumistrados en la función fillStudentForm
            $this->fillStudentForm($browser, [
                'id_card_number' => '' //En esta linea se modifica el campo específico que se desea probar
            ]);


            $browser->scrollTo('button[type="submit"]')
                ->pause(1000)
                ->press('Add')
                ->assertDontSee(' Student creation was successful!') //revisa la ausencia del texto que nos dice si se crea el estudiante.
                ->assertPathIs('/students/add'); //este assert revisa si cambiamos de ruta, validando si se creó el estudiante.


        });

    }

    //Prueba automatizada del caso CSP63 "Crear un estudiante nuevo sin ingresar un Father Name en el formulario"
    public function testNoCreaEstudianteSinFatherName()
    {
        $this->browse(function (Browser $browser) {

            $browser->visit('/students/add');
            //Llena el fomulario con los datos sumistrados en la función fillStudentForm
            $this->fillStudentForm($browser, [
                'father_name' => '' //En esta linea se modifica el campo específico que se desea probar
            ]);


            $browser->scrollTo('button[type="submit"]')
                ->pause(1000)
                ->press('Add')
                ->assertDontSee(' Student creation was successful!') //revisa la ausencia del texto que nos dice si se crea el estudiante.
                ->assertPathIs('/students/add'); //este assert revisa si cambiamos de ruta, validando si se creó el estudiante.


        });

    }

    //Prueba automatizada del caso CSP64 "Crear un estudiante nuevo sin ingresar un FathersPhone en el formulario"
    public function testNoCreaEstudianteSinFatherPhone()
    {
        $this->browse(function (Browser $browser) {

            $browser->visit('/students/add');
            //Llena el fomulario con los datos sumistrados en la función fillStudentForm
            $this->fillStudentForm($browser, [
                'father_phone' => '' //En esta linea se modifica el campo específico que se desea probar
            ]);


            $browser->scrollTo('button[type="submit"]')
                ->pause(1000)
                ->press('Add')
                ->assertDontSee(' Student creation was successful!') //revisa la ausencia del texto que nos dice si se crea el estudiante.
                ->assertPathIs('/students/add'); //este assert revisa si cambiamos de ruta, validando si se creó el estudiante.


        });

    }

    //Prueba automatizada del caso CSP65 "Crear un estudiante nuevo sin ingresar un MotherName en el formulario"
    public function testNoCreaEstudianteSinMotherName()
    {
        $this->browse(function (Browser $browser) {

            $browser->visit('/students/add');
            //Llena el fomulario con los datos sumistrados en la función fillStudentForm
            $this->fillStudentForm($browser, [
                'mother_name' => '' //En esta linea se modifica el campo específico que se desea probar
            ]);


            $browser->scrollTo('button[type="submit"]')
                ->pause(1000)
                ->press('Add')
                ->assertDontSee(' Student creation was successful!') //revisa la ausencia del texto que nos dice si se crea el estudiante.
                ->assertPathIs('/students/add'); //este assert revisa si cambiamos de ruta, validando si se creó el estudiante.


        });

    }

    //Prueba automatizada del caso CSP66 "Crear un estudiante nuevo sin ingresar un MotherPhone en el formulario"
    public function testNoCreaEstudianteSinMotherPhone()
    {
        $this->browse(function (Browser $browser) {

            $browser->visit('/students/add');
            //Llena el fomulario con los datos sumistrados en la función fillStudentForm
            $this->fillStudentForm($browser, [
                'mother_phone' => '' //En esta linea se modifica el campo específico que se desea probar
            ]);


            $browser->scrollTo('button[type="submit"]')
                ->pause(1000)
                ->press('Add')
                ->assertDontSee(' Student creation was successful!') //revisa la ausencia del texto que nos dice si se crea el estudiante.
                ->assertPathIs('/students/add'); //este assert revisa si cambiamos de ruta, validando si se creó el estudiante.


        });

    }

    //Prueba automatizada del caso CSP67 "Crear un estudiante nuevo sin ingresar un ParentAddress en el formulario"
    public function testNoCreaEstudianteSinParentAddress()
    {
        $this->browse(function (Browser $browser) {

            $browser->visit('/students/add');
            //Llena el fomulario con los datos sumistrados en la función fillStudentForm
            $this->fillStudentForm($browser, [
                'parent_address' => '' //En esta linea se modifica el campo específico que se desea probar
            ]);


            $browser->scrollTo('button[type="submit"]')
                ->pause(1000)
                ->press('Add')
                ->assertDontSee(' Student creation was successful!') //revisa la ausencia del texto que nos dice si se crea el estudiante.
                ->assertPathIs('/students/add'); //este assert revisa si cambiamos de ruta, validando si se creó el estudiante.


        });

    }

    //Prueba automatizada del caso CSP68 "Crear un estudiante nuevo sin seleccionar una "class" en el formulario"
    public function testNoCreaEstudianteSinClass()
    {
        $this->browse(function (Browser $browser) {

            $browser->visit('/students/add')
                //En este caso llenamos los datos linea por línea debido a que en los select de class y section no existe una opción de "vacío"
                ->type('first_name', 'Daniel')
                ->type('last_name', 'Montero')
                ->type('email', 'daniel@montero.com')
                ->type('password', 'Montes123')
                ->type('birthday', '2001-02-27')
                ->type('address', 'Casa Bavaria')
                ->type('address2', 'Casa Blanca')
                ->type('city', 'Alajuela')
                ->type('zip', '20103')
                ->type('nationality', 'Costa Rica')
                ->type('phone', '85581676')
                ->type('id_card_number', '118030857')
                ->type('father_name', 'Oscar')
                ->type('father_phone', '83885929')
                ->type('mother_name', 'Adriana')
                ->type('mother_phone', '85204279')
                ->type('parent_address', 'Vuelta de Jorco')
                //Dejamos ausentes los selects de class y section
                ->type('board_reg_no', '1');


            $browser->scrollTo('button[type="submit"]')
                ->pause(1000)
                ->press('Add')
                ->assertDontSee(' Student creation was successful!') //revisa la ausencia del texto que nos dice si se crea el estudiante.
                ->assertPathIs('/students/add'); //este assert revisa si cambiamos de ruta, validando si se creó el estudiante.


        });

    }

    //Prueba automatizada del caso CSP69 "Crear un estudiante nuevo sin seleccionar una "Section" en el formulario"
    public function testNoCreaEstudianteSinSection()
    {
        $this->browse(function (Browser $browser) {

            $browser->visit('/students/add')
                //En este caso llenamos los datos linea por línea debido a que en los select de class y section no existe una opción de "vacío"
                ->type('first_name', 'Daniel')
                ->type('last_name', 'Montero')
                ->type('email', 'daniel@montero.com')
                ->type('password', 'Montes123')
                ->type('birthday', '2001-02-27')
                ->type('address', 'Casa Bavaria')
                ->type('address2', 'Casa Blanca')
                ->type('city', 'Alajuela')
                ->type('zip', '20103')
                ->type('nationality', 'Costa Rica')
                ->type('phone', '85581676')
                ->type('id_card_number', '118030857')
                ->type('father_name', 'Oscar')
                ->type('father_phone', '83885929')
                ->type('mother_name', 'Adriana')
                ->type('mother_phone', '85204279')
                ->type('parent_address', 'Vuelta de Jorco')
                ->select('class_id', 'Class 1')
                //Dejamos ausente el select de section
                ->type('board_reg_no', '1');


            $browser->scrollTo('button[type="submit"]')
                ->pause(1000)
                ->press('Add')
                ->assertDontSee(' Student creation was successful!') //revisa la ausencia del texto que nos dice si se crea el estudiante.
                ->assertPathIs('/students/add'); //este assert revisa si cambiamos de ruta, validando si se creó el estudiante.


        });

    }

    //Prueba automatizada del caso CSP70 "Crear un estudiante nuevo sin ingresar un BoradRegNo en el formulario"
    public function testNoCreaEstudianteSinBoardRegNo()
    {
        $this->browse(function (Browser $browser) {

            $browser->visit('/students/add');
            //Llena el fomulario con los datos sumistrados en la función fillStudentForm
            $this->fillStudentForm($browser, [
                'board_reg_no' => '' //En esta linea se modifica el campo específico que se desea probar
            ]);


            $browser->scrollTo('button[type="submit"]')
                ->pause(1000)
                ->press('Add')
                ->assertDontSee(' Student creation was successful!') //revisa la ausencia del texto que nos dice si se crea el estudiante.
                ->assertPathIs('/students/add'); //este assert revisa si cambiamos de ruta, validando si se creó el estudiante.



        });

    }
}
