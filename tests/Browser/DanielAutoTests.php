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

class DanielAutoTests extends DuskTestCase
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

        $this->sessionYear = \App\Models\SchoolSession::factory()->create([
            'session_name' => '2024-2025',
        ]);

        $this->class = \App\Models\SchoolClass::factory()->create([
            'session_id' => $this->sessionYear->id,   
            'class_name' => 'Class 1',
        ]);

        $this->section = \App\Models\Section::factory()->create([
            'class_id' => $this->class->id,
            'session_id' => $this->sessionYear->id, // <-- ¡ESTO ES LO QUE FALTABA!
            'section_name' => 'Section A',
            'room_no' => 101,
        ]);

    }


    //Esta funcion me permite rellenar los formularios más facilmente, la invoco en cada test para no extender el codigo.
    protected function fillStudentForm(Browser $browser, array $overrides = [])
{
    // Valores por defecto
    $defaults = [
        'first_name'      => 'Daniel',
        'last_name'       => 'Montero',
        'email'           => 'daniel@montero.com',
        'password'        => 'Montes123',
        'birthday'        => '2001-02-27', 
        'address'         => 'Casa Bavaria',
        'address2'        => 'Casa Blanca',
        'city'            => 'Alajuela',
        'zip'             => '20103',
        'nationality'     => 'Costa Rica',
        'phone'           => '85581676',
        'id_card_number'  => '118030857',
        'father_name'     => 'Oscar',
        'father_phone'    => '83885929',
        'mother_name'     => 'Adriana',
        'mother_phone'    => '85204279',
        'parent_address'  => 'Vuelta de Jorco',
        'class_id'        => $this->class->id,
        'section_id'      => $this->section->id,
        'board_reg_no'    => '1',
    ];

    // Merge: lo que venga en $overrides reemplaza a $defaults
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
            // selects 
            ->select('class_id', $data['class_id']) 
            ->select('section_id', $data['section_id']) 
            ->type('board_reg_no', $data['board_reg_no']);

    // devolver el browser para seguir encadenando si se desea
    return $browser;
}


    
    //Prueba automatizada del caso CSP51 "Crear un estudiante nuevo sin ingresar un nombre en el formulario"
    public function testNoCreaEstudianteSinNombre(){
        $this->browse(function(Browser $browser){
            //Este primer test hace login como administrador(los siguientes mantienen la sesión)
            $browser->visit('/login')
                    ->type('email', 'admin@ut.com')
                    ->type('password', 'password')
                    ->press('Login')
                    ->visit('/students/add');
            //Llena el fomulario con los datos sumistrados en la función fillStudentForm
            $this->fillStudentForm($browser, [
                        'first_name' => '' //En esta linea se modifica el campo específico que se desea probar
            ]);


            $browser->scrollTo('button[type="submit"]')
                    ->press('Add')
                    ->assertDontSee(' Student creation was successful!') //revisa la ausencia del texto que nos dice si se crea el estudiante.
                    ->assertPathIs('/students/add'); //este assert revisa si cambiamos de ruta, validando si se creó el estudiante.


        });

    }

    //Prueba automatizada del caso CSP52 "Crear un estudiante nuevo sin ingresar un apellido en el formulario"
    public function testNoCreaEstudianteSinApellido(){
        $this->browse(function(Browser $browser){

            $browser->visit('/students/add');
            //Llena el fomulario con los datos sumistrados en la función fillStudentForm
            $this->fillStudentForm($browser, [
                        'last_name' => '' //En esta linea se modifica el campo específico que se desea probar
            ]);


            $browser->scrollTo('button[type="submit"]')
                    ->press('Add')
                    ->assertDontSee(' Student creation was successful!') //revisa la ausencia del texto que nos dice si se crea el estudiante.
                    ->assertPathIs('/students/add'); //este assert revisa si cambiamos de ruta, validando si se creó el estudiante.


        });

    }

    //Prueba automatizada del caso CSP53 "Crear un estudiante nuevo sin ingresar un email en el formulario"
    public function testNoCreaEstudianteSinEmail(){
        $this->browse(function(Browser $browser){

            $browser->visit('/students/add');
            //Llena el fomulario con los datos sumistrados en la función fillStudentForm
            $this->fillStudentForm($browser, [
                        'email' => '' //En esta linea se modifica el campo específico que se desea probar
            ]);


            $browser->scrollTo('button[type="submit"]')
                    ->press('Add')
                    ->assertDontSee(' Student creation was successful!') //revisa la ausencia del texto que nos dice si se crea el estudiante.
                    ->assertPathIs('/students/add'); //este assert revisa si cambiamos de ruta, validando si se creó el estudiante.


        });

    }

    //Prueba automatizada del caso CSP54 "Crear un estudiante nuevo sin ingresar una contraseña en el formulario"
    public function testNoCreaEstudianteSinContraseña(){
        $this->browse(function(Browser $browser){

            $browser->visit('/students/add');
            //Llena el fomulario con los datos sumistrados en la función fillStudentForm
            $this->fillStudentForm($browser, [
                        'password' => '' //En esta linea se modifica el campo específico que se desea probar
            ]);


            $browser->scrollTo('button[type="submit"]')
                    ->press('Add')
                    ->assertDontSee(' Student creation was successful!') //revisa la ausencia del texto que nos dice si se crea el estudiante.
                    ->assertPathIs('/students/add'); //este assert revisa si cambiamos de ruta, validando si se creó el estudiante.


        });

    }

    //Prueba automatizada del caso CSP55 "Crear un estudiante nuevo sin ingresar una fecha de nacimiento en el formulario"
    public function testNoCreaEstudianteSinBirthday(){
        $this->browse(function(Browser $browser){

            $browser->visit('/students/add');
            //Llena el fomulario con los datos sumistrados en la función fillStudentForm
            $this->fillStudentForm($browser, [
                        'birthday' => '' //En esta linea se modifica el campo específico que se desea probar
            ]);


            $browser->scrollTo('button[type="submit"]')
                    ->press('Add')
                    ->assertDontSee(' Student creation was successful!') //revisa la ausencia del texto que nos dice si se crea el estudiante.
                    ->assertPathIs('/students/add'); //este assert revisa si cambiamos de ruta, validando si se creó el estudiante.


        });

    }

    //Prueba automatizada del caso CSP56 "Crear un estudiante nuevo sin ingresar una dirección en el formulario"
    public function testNoCreaEstudianteSinDireccion(){
        $this->browse(function(Browser $browser){

            $browser->visit('/students/add');
            //Llena el fomulario con los datos sumistrados en la función fillStudentForm
            $this->fillStudentForm($browser, [
                        'address' => '' //En esta linea se modifica el campo específico que se desea probar
            ]);


            $browser->scrollTo('button[type="submit"]')
                    ->press('Add')
                    ->assertDontSee(' Student creation was successful!') //revisa la ausencia del texto que nos dice si se crea el estudiante.
                    ->assertPathIs('/students/add'); //este assert revisa si cambiamos de ruta, validando si se creó el estudiante.


        });

    }


    //Prueba automatizada del caso CSP57 "Crear un estudiante nuevo sin ingresar una direccion 2 en el formulario"
    //Esta prueba se espera que no falle, puesto que el campo de dirección 2 no se indica como requerido, sin embargo el sistema lo espera
    public function testCreaEstudianteSinDireccion2(){
        $this->browse(function(Browser $browser){

            $browser->visit('/students/add');
            //Llena el fomulario con los datos sumistrados en la función fillStudentForm
            $this->fillStudentForm($browser, [
                        'address2' => '' //En esta linea se modifica el campo específico que se desea probar
            ]);


            $browser->scrollTo('button[type="submit"]')
                    ->press('Add')
                    ->assertSee(' Student creation was successful!') //revisa la ausencia del texto que nos dice si se crea el estudiante.
                    ->assertPathIs('/students/add'); //este assert revisa si cambiamos de ruta, validando si se creó el estudiante.


        });

    }

    //Prueba automatizada del caso CSP58 "Crear un estudiante nuevo sin ingresar una ciudad en el formulario"
    public function testNoCreaEstudianteSinCity(){
        $this->browse(function(Browser $browser){

            $browser->visit('/students/add');
            //Llena el fomulario con los datos sumistrados en la función fillStudentForm
            $this->fillStudentForm($browser, [
                        'city' => '' //En esta linea se modifica el campo específico que se desea probar
            ]);


            $browser->scrollTo('button[type="submit"]')
                    ->press('Add')
                    ->assertDontSee(' Student creation was successful!') //revisa la ausencia del texto que nos dice si se crea el estudiante.
                    ->assertPathIs('/students/add'); //este assert revisa si cambiamos de ruta, validando si se creó el estudiante.


        });

    }

    //Prueba automatizada del caso CSP59 "Crear un estudiante nuevo sin ingresar un zip code en el formulario"
    public function testNoCreaEstudianteSinZip(){
        $this->browse(function(Browser $browser){

            $browser->visit('/students/add');
            //Llena el fomulario con los datos sumistrados en la función fillStudentForm
            $this->fillStudentForm($browser, [
                        'zip' => '' //En esta linea se modifica el campo específico que se desea probar
            ]);


            $browser->scrollTo('button[type="submit"]')
                    ->press('Add')
                    ->assertDontSee(' Student creation was successful!') //revisa la ausencia del texto que nos dice si se crea el estudiante.
                    ->assertPathIs('/students/add'); //este assert revisa si cambiamos de ruta, validando si se creó el estudiante.


        });

    }

    //Prueba automatizada del caso CSP60 "Crear un estudiante nuevo sin ingresar una nacionalidad en el formulario"
    public function testNoCreaEstudianteSinNationality(){
        $this->browse(function(Browser $browser){

            $browser->visit('/students/add');
            //Llena el fomulario con los datos sumistrados en la función fillStudentForm
            $this->fillStudentForm($browser, [
                        'nationality' => '' //En esta linea se modifica el campo específico que se desea probar
            ]);


            $browser->scrollTo('button[type="submit"]')
                    ->press('Add')
                    ->assertDontSee(' Student creation was successful!') //revisa la ausencia del texto que nos dice si se crea el estudiante.
                    ->assertPathIs('/students/add'); //este assert revisa si cambiamos de ruta, validando si se creó el estudiante.


        });

    }

    //Prueba automatizada del caso CSP61 "Crear un estudiante nuevo sin ingresar un phone en el formulario"
    public function testNoCreaEstudianteSinPhone(){
        $this->browse(function(Browser $browser){

            $browser->visit('/students/add');
            //Llena el fomulario con los datos sumistrados en la función fillStudentForm
            $this->fillStudentForm($browser, [
                        'phone' => '' //En esta linea se modifica el campo específico que se desea probar
            ]);


            $browser->scrollTo('button[type="submit"]')
                    ->press('Add')
                    ->assertDontSee(' Student creation was successful!') //revisa la ausencia del texto que nos dice si se crea el estudiante.
                    ->assertPathIs('/students/add'); //este assert revisa si cambiamos de ruta, validando si se creó el estudiante.


        });

    }

    //Prueba automatizada del caso CSP62 "Crear un estudiante nuevo sin ingresar un IdCardNumber en el formulario"
    public function testNoCreaEstudianteSinIdCardNumber(){
        $this->browse(function(Browser $browser){

            $browser->visit('/students/add');
            //Llena el fomulario con los datos sumistrados en la función fillStudentForm
            $this->fillStudentForm($browser, [
                        'id_card_number' => '' //En esta linea se modifica el campo específico que se desea probar
            ]);


            $browser->scrollTo('button[type="submit"]')
                    ->press('Add')
                    ->assertDontSee(' Student creation was successful!') //revisa la ausencia del texto que nos dice si se crea el estudiante.
                    ->assertPathIs('/students/add'); //este assert revisa si cambiamos de ruta, validando si se creó el estudiante.


        });

    }

    //Prueba automatizada del caso CSP63 "Crear un estudiante nuevo sin ingresar un Father Name en el formulario"
    public function testNoCreaEstudianteSinFatherName(){
        $this->browse(function(Browser $browser){

            $browser->visit('/students/add');
            //Llena el fomulario con los datos sumistrados en la función fillStudentForm
            $this->fillStudentForm($browser, [
                        'father_name' => '' //En esta linea se modifica el campo específico que se desea probar
            ]);


            $browser->scrollTo('button[type="submit"]')
                    ->press('Add')
                    ->assertDontSee(' Student creation was successful!') //revisa la ausencia del texto que nos dice si se crea el estudiante.
                    ->assertPathIs('/students/add'); //este assert revisa si cambiamos de ruta, validando si se creó el estudiante.


        });

    }

    //Prueba automatizada del caso CSP64 "Crear un estudiante nuevo sin ingresar un FathersPhone en el formulario"
    public function testNoCreaEstudianteSinFatherPhone(){
        $this->browse(function(Browser $browser){

            $browser->visit('/students/add');
            //Llena el fomulario con los datos sumistrados en la función fillStudentForm
            $this->fillStudentForm($browser, [
                        'father_phone' => '' //En esta linea se modifica el campo específico que se desea probar
            ]);


            $browser->scrollTo('button[type="submit"]')
                    ->press('Add')
                    ->assertDontSee(' Student creation was successful!') //revisa la ausencia del texto que nos dice si se crea el estudiante.
                    ->assertPathIs('/students/add'); //este assert revisa si cambiamos de ruta, validando si se creó el estudiante.


        });

    }

    //Prueba automatizada del caso CSP65 "Crear un estudiante nuevo sin ingresar un MotherName en el formulario"
    public function testNoCreaEstudianteSinMotherName(){
        $this->browse(function(Browser $browser){

            $browser->visit('/students/add');
            //Llena el fomulario con los datos sumistrados en la función fillStudentForm
            $this->fillStudentForm($browser, [
                        'mother_name' => '' //En esta linea se modifica el campo específico que se desea probar
            ]);


            $browser->scrollTo('button[type="submit"]')
                    ->press('Add')
                    ->assertDontSee(' Student creation was successful!') //revisa la ausencia del texto que nos dice si se crea el estudiante.
                    ->assertPathIs('/students/add'); //este assert revisa si cambiamos de ruta, validando si se creó el estudiante.


        });

    }

    //Prueba automatizada del caso CSP66 "Crear un estudiante nuevo sin ingresar un MotherPhone en el formulario"
    public function testNoCreaEstudianteSinMotherPhone(){
        $this->browse(function(Browser $browser){

            $browser->visit('/students/add');
            //Llena el fomulario con los datos sumistrados en la función fillStudentForm
            $this->fillStudentForm($browser, [
                        'mother_phone' => '' //En esta linea se modifica el campo específico que se desea probar
            ]);


            $browser->scrollTo('button[type="submit"]')
                    ->press('Add')
                    ->assertDontSee(' Student creation was successful!') //revisa la ausencia del texto que nos dice si se crea el estudiante.
                    ->assertPathIs('/students/add'); //este assert revisa si cambiamos de ruta, validando si se creó el estudiante.


        });

    }

    //Prueba automatizada del caso CSP67 "Crear un estudiante nuevo sin ingresar un ParentAddress en el formulario"
    public function testNoCreaEstudianteSinParentAddress(){
        $this->browse(function(Browser $browser){

            $browser->visit('/students/add');
            //Llena el fomulario con los datos sumistrados en la función fillStudentForm
            $this->fillStudentForm($browser, [
                        'parent_address' => '' //En esta linea se modifica el campo específico que se desea probar
            ]);


            $browser->scrollTo('button[type="submit"]')
                    ->press('Add')
                    ->assertDontSee(' Student creation was successful!') //revisa la ausencia del texto que nos dice si se crea el estudiante.
                    ->assertPathIs('/students/add'); //este assert revisa si cambiamos de ruta, validando si se creó el estudiante.


        });

    }

    //Prueba automatizada del caso CSP68 "Crear un estudiante nuevo sin seleccionar una "class" en el formulario"
    public function testNoCreaEstudianteSinClass(){
        $this->browse(function(Browser $browser){

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
                    ->press('Add')
                    ->assertDontSee(' Student creation was successful!') //revisa la ausencia del texto que nos dice si se crea el estudiante.
                    ->assertPathIs('/students/add'); //este assert revisa si cambiamos de ruta, validando si se creó el estudiante.


        });

    }

    //Prueba automatizada del caso CSP69 "Crear un estudiante nuevo sin seleccionar una "Section" en el formulario"
    public function testNoCreaEstudianteSinSection(){
        $this->browse(function(Browser $browser){

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
            ->select('section_id', 'Class 1') 
            //Dejamos ausente el select de section
            ->type('board_reg_no', '1');


            $browser->scrollTo('button[type="submit"]')
                    ->press('Add')
                    ->assertDontSee(' Student creation was successful!') //revisa la ausencia del texto que nos dice si se crea el estudiante.
                    ->assertPathIs('/students/add'); //este assert revisa si cambiamos de ruta, validando si se creó el estudiante.


        });

    }

    //Prueba automatizada del caso CSP70 "Crear un estudiante nuevo sin ingresar un BoradRegNo en el formulario"
    public function testNoCreaEstudianteSinBoardRegNo(){
        $this->browse(function(Browser $browser){

            $browser->visit('/students/add');
            //Llena el fomulario con los datos sumistrados en la función fillStudentForm
            $this->fillStudentForm($browser, [
                        'board_reg_no' => '' //En esta linea se modifica el campo específico que se desea probar
            ]);


            $browser->scrollTo('button[type="submit"]')
                    ->press('Add')
                    ->assertDontSee(' Student creation was successful!') //revisa la ausencia del texto que nos dice si se crea el estudiante.
                    ->assertPathIs('/students/add'); //este assert revisa si cambiamos de ruta, validando si se creó el estudiante.
                    


        });

    }
    

}



