<?php

namespace Tests\Feature\UniTestsDaniel;

use Tests\TestCase;
use App\Models\User;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\SchoolSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Hash;


class UniTestsDanielTest extends TestCase
{
    use RefreshDatabase;

    protected $session;
    protected $class;
    protected $section;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        $this->seed(\Database\Seeders\PermissionSeeder::class);


        // Crear rol ADMIN si no existe
        $this->adminRole = Role::firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'web']
        );
        $this->studentRole = Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

        $this->teacherRole = Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);

        Permission::firstOrCreate(['name' => 'create courses']);
        $this->adminRole->givePermissionTo('create courses');


        /*
         // Crear permisos necesarios por el formulario / controlador
        $createUsers = Permission::firstOrCreate(['name' => 'create users', 'guard_name' => 'web']);
        $viewUsers      = Permission::firstOrCreate(['name' => 'view users', 'guard_name' => 'web']);
        */
        // Asignar permisos al rol
        $this->adminRole->givePermissionTo('create users');
        $this->adminRole->givePermissionTo('view users');
        $this->adminRole->givePermissionTo('view attendances');


        // Crear sesión activa
        $this->session = \App\Models\SchoolSession::create([
            'session_name' => '2025',
        ]);

        // Crear clase
        $this->class = \App\Models\SchoolClass::create([
            'session_id' => $this->session->id,
            'class_name' => 'Class Test'
        ]);

        // Crear section
        $this->section = \App\Models\Section::create([
            'session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_name' => 'Section A',
            'room_no' => '101'
        ]);


        // Crear semestre válido
        $this->semester = \App\Models\Semester::create([
            'semester_name' => 'Semestre Test',
            'start_date' => '2025-01-01',
            'end_date' => '2025-06-01',
            'session_id' => $this->session->id
        ]);

        // Crear course
        $this->course = \App\Models\Course::create([
            'course_name' => 'Matemáticas',
            'course_type' => 'main',
            'class_id' => $this->class->id,
            'semester_id' => $this->semester->id,
            'session_id' => $this->session->id
        ]);


    }

    protected function makeAdmin(): User
    {
        $u = User::factory()->create();
        $u->assignRole('admin');
        return $u;
    }



    //Prueba 51. validar la creación de un estudiante
    /** @test */
    public function test_crear_estudiante_valido_happy_path()
    {
        // Creamos admin autenticado
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $payload = [
            'first_name' => 'Juan',
            'last_name' => 'Pérez',
            'email' => 'juanp@example.com',
            'gender' => 'male',
            'nationality' => 'Costa Rica',
            'phone' => '12345678',
            'address' => 'San José',
            'address2' => 'N/A',
            'city' => 'Alajuela',
            'zip' => '10101',
            'birthday' => '2010-05-10',
            'religion' => 'None',
            'blood_type' => 'O+',
            'password' => 'Secret123!',
            'id_card_number' => '1234567',

            // Parents
            'father_name' => 'Oscar Pérez',
            'father_phone' => '88881111',
            'mother_name' => 'Ana Pérez',
            'mother_phone' => '77776666',
            'parent_address' => 'Barrio Los Ángeles',

            // Academic related
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'session_id' => $this->session->id,
            'board_reg_no' => 'BRN-001'
        ];


        $response = $this->actingAs($admin)->post(
            route('school.student.create'),
            $payload
        );

        // ASSERTS
        //dump(session()->all());
        $response->assertStatus(302);
        $response->assertSessionHas('status', 'Student creation was successful!');
        $this->assertDatabaseHas('users', ['email' => 'juanp@example.com']);

    }

    //Prueba 52, validar que el sistema no permita registrar un usuario con un email repetido
    /** @test */
    public function test_no_permite_email_duplicado()
    {
        // Admin autenticado
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        // Creamos un usuario existente con ese email
        User::factory()->create([
            'email' => 'correo@duplicado.com'
        ]);

        // Payload que intenta registrar usando el mismo email
        $payload = [
            'first_name' => 'Carlos',
            'last_name' => 'Ramírez',
            'email' => 'correo@duplicado.com', // duplicado
            'gender' => 'male',
            'nationality' => 'Costa Rica',
            'phone' => '12345678',
            'address' => 'San José',
            'address2' => 'N/A',
            'city' => 'Alajuela',
            'zip' => '10101',
            'birthday' => '2009-02-10',
            'religion' => 'None',
            'blood_type' => 'O+',
            'password' => 'Secret123!',
            'id_card_number' => '77777',

            // Parents
            'father_name' => 'Pedro',
            'father_phone' => '88889999',
            'mother_name' => 'María',
            'mother_phone' => '99998888',
            'parent_address' => 'Barrio Los Pinos',

            // Academic related
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'session_id' => $this->session->id,
            'board_reg_no' => 'BRN-010'
        ];

        $response = $this->actingAs($admin)->post(
            route('school.student.create'),
            $payload
        );

        // Validación
        $response->assertStatus(302); // Laravel redirige con errores
        $response->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('users', ['first_name' => 'Carlos']);

    }

    //Prueba 53, Validar si el sistema me permite registrar a un estudiante de edad muy baja
    //Este test falla puesto que el sistema no verifica una edad mínima a la hora del registro
    /** @test */
    public function test_no_permite_registrar_estudiante_menor_a_la_edad_minima()
    {
        // Admin con permisos
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        // Payload con fecha que implica muy poca edad
        $payload = [
            'first_name' => 'Baby',
            'last_name' => 'Test',
            'email' => 'babytest@example.com',
            'gender' => 'male',
            'nationality' => 'Costa Rica',
            'phone' => '12345678',
            'address' => 'San José',
            'address2' => 'N/A',
            'city' => 'Alajuela',
            'zip' => '10101',
            'birthday' => now()->subYears(2)->format('Y-m-d'), // 🙋 SOLO 2 años
            'religion' => 'None',
            'blood_type' => 'O+',
            'password' => 'Secret123!',
            'id_card_number' => '999999',

            // Parents
            'father_name' => 'Papá',
            'father_phone' => '88881111',
            'mother_name' => 'Mamá',
            'mother_phone' => '77776666',
            'parent_address' => 'Barrio Los Ángeles',

            // Academic
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'session_id' => $this->session->id,
            'board_reg_no' => 'BRN-002'
        ];

        $response = $this->actingAs($admin)
            ->post(route('school.student.create'), $payload);

        // ASSERTS → Validación debe fallar
        $response->assertStatus(302);
        $response->assertSessionHasErrors('birthday');
    }

    //Prueba 54. Formato de Id Card Number: Valida que el ID cumpla el formato requerido; rechaza valores con letras o estructura incorrecta.
    /** @test */
    public function test_no_permite_id_card_con_letras_o_formato_invalido()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $payload = [
            'first_name' => 'Luis',
            'last_name' => 'Ramírez',
            'email' => 'lramirez@example.com',
            'gender' => 'male',
            'nationality' => 'Costa Rica',
            'phone' => '84758475',
            'address' => 'San José',
            'address2' => 'N/A',
            'city' => 'Alajuela',
            'zip' => '10101',
            'birthday' => '2009-05-10',
            'religion' => 'None',
            'blood_type' => 'O+',
            'password' => 'Secret123!',

            // Campo con formato inválido
            'id_card_number' => 'ABC1234',

            'father_name' => 'Carlos',
            'father_phone' => '88880001',
            'mother_name' => 'Karina',
            'mother_phone' => '70004422',
            'parent_address' => 'Paraiso',

            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'session_id' => $this->session->id,
            'board_reg_no' => 'BRN-002'
        ];

        $response = $this
            ->actingAs($admin)
            ->post(route('school.student.create'), $payload);

        // Debería rechazar por formato inválido
        $response->assertStatus(302);
        $response->assertSessionHasErrors('id_card_number');
    }

    //Prueba 55, El sistema no permite ingresar algo diferente a un tipo de sangre
    /** @test */
    public function no_permite_tipo_de_sangre_fuera_del_catalogo()
    {
        $admin = $this->makeAdmin();

        // Valores NO válidos
        $payload = [
            'first_name' => 'Luis',
            'last_name' => 'Ramírez',
            'email' => 'lramirez@example.com',
            'gender' => 'male',
            'nationality' => 'Costa Rica',
            'phone' => '12345678',
            'address' => 'Alajuela Centro',
            'address2' => 'N/A',
            'city' => 'Alajuela',
            'zip' => '10101',
            'birthday' => '2010-05-10',
            'religion' => 'None',
            'blood_type' => 'Tornillo-14mm', // ❌ valor inválido
            'password' => 'Secret123!',
            'id_card_number' => '1234567',
            'father_name' => 'Oscar',
            'father_phone' => '88881111',
            'mother_name' => 'Ana',
            'mother_phone' => '11112222',
            'parent_address' => 'Barrio Los Sauces',
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'session_id' => $this->session->id,
            'board_reg_no' => 'BRN-002'
        ];

        $response = $this->actingAs($admin)
            ->post(route('school.student.create'), $payload);

        // VALIDACIÓN — debería fallar
        $response->assertStatus(302);
        $response->assertSessionHasErrors('blood_type');
    }

    //Prueba 56, al crear un estudiante, se crea asocia correctamente en academic_info
    /**
     * Este test falla debido a que la tabla student_academic_infos
     * no contiene columnas class_id, section_id ni session_id,
     * por lo tanto, la relación académica no se almacena.
     * Este comportamiento debe ser corregido a nivel de modelo y migración.
     */
    /** @test */
    public function crea_academic_info_con_relaciones_correctas()
    {
        $admin = $this->makeAdmin();

        $payload = [
            'first_name' => 'Mario',
            'last_name' => 'Torres',
            'email' => 'mario.torres@example.com',
            'gender' => 'male',
            'nationality' => 'Costa Rica',
            'phone' => '65432100',
            'address' => 'San José',
            'address2' => 'N/A',
            'city' => 'San José',
            'zip' => '20202',
            'birthday' => '2010-01-01',
            'religion' => 'None',
            'blood_type' => 'AB+',
            'password' => 'Secret123!',
            'id_card_number' => '445566',
            'father_name' => 'Julio Torres',
            'father_phone' => '88885555',
            'mother_name' => 'Laura Torres',
            'mother_phone' => '77774444',
            'parent_address' => 'Zapote',
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'session_id' => $this->session->id,
            'board_reg_no' => 'BRN-010'
        ];

        $response = $this->actingAs($admin)
            ->post(route('school.student.create'), $payload);

        $response->assertStatus(302);

        // Obtener el estudiante recién creado
        $student = User::where('email', 'mario.torres@example.com')->first();
        $this->assertNotNull($student, "No se creó el usuario.");

        // Validar relación en BD
        $this->assertDatabaseHas('student_academic_infos', [
            'student_id' => $student->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'session_id' => $this->session->id,
        ]);
    }

    //Prueba 57, Relación con notas (marks): marks() del estudiante devuelve solo sus notas, sin incluir notas de otros.
    /** @test */
    public function un_estudiante_solo_devuelve_sus_proprias_notas_en_marks_relation()
    {
        // Crear admin
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        // Crear estudiantes
        $estudianteA = User::factory()->create(['email' => 'a@example.com']);
        $estudianteA->assignRole('student');

        $estudianteB = User::factory()->create(['email' => 'b@example.com']);
        $estudianteB->assignRole('student');

        // Crear notas para A
        \App\Models\Mark::create([
            'student_id' => $estudianteA->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'course_id' => $this->course->id,
            'exam_id' => 1,   // O crea un Exam antes, pero ponemos 1 hardcode si no lo validas
            'session_id' => $this->session->id,
            'marks' => 95
        ]);

        \App\Models\Mark::create([
            'student_id' => $estudianteA->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'course_id' => $this->course->id,
            'exam_id' => 1,
            'session_id' => $this->session->id,
            'marks' => 88
        ]);

        //notas estudiante b
        \App\Models\Mark::create([
            'student_id' => $estudianteB->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'course_id' => $this->course->id,
            'exam_id' => 1,
            'session_id' => $this->session->id,
            'marks' => 50
        ]);

        // Obtener marks desde relación
        $notasEstudianteA = $estudianteA->marks;
        $notasEstudianteB = $estudianteB->marks;

        // ASSERTS
        $this->assertCount(2, $notasEstudianteA);
        $this->assertCount(1, $notasEstudianteB);

        foreach ($notasEstudianteA as $nota) {
            $this->assertEquals($estudianteA->id, $nota->student_id);
        }

        foreach ($notasEstudianteB as $nota) {
            $this->assertEquals($estudianteB->id, $nota->student_id);
        }
    }

    //Prueba 58 Contraseña hasheada Confirma que la contraseña no se guarda en texto plano y Hash::check funciona.
    /** @test */
    public function la_contrasena_se_guarda_hasheada()
    {
        // Admin autenticado
        $admin = $this->makeAdmin();

        // Contraseña en texto plano que vamos a enviar
        $plainPassword = 'Secret123!';

        // Payload igual que el happy path, cambiando solo el email
        $payload = [
            'first_name' => 'Hash',
            'last_name' => 'Test',
            'email' => 'hash.test@example.com',
            'gender' => 'male',
            'nationality' => 'Costa Rica',
            'phone' => '12345678',
            'address' => 'San José',
            'address2' => 'N/A',
            'city' => 'Alajuela',
            'zip' => '10101',
            'birthday' => '2010-05-10',
            'religion' => 'None',
            'blood_type' => 'O+',
            'password' => $plainPassword,
            'id_card_number' => '5555555',

            // Parents
            'father_name' => 'Padre Hash',
            'father_phone' => '88881111',
            'mother_name' => 'Madre Hash',
            'mother_phone' => '77776666',
            'parent_address' => 'Barrio Hash',

            // Academic related
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'session_id' => $this->session->id,
            'board_reg_no' => 'BRN-HASH-01'
        ];

        $response = $this->actingAs($admin)->post(
            route('school.student.create'),
            $payload
        );

        // Debe redirigir (creación exitosa)
        $response->assertStatus(302);

        // Obtenemos el usuario recién creado
        $student = User::where('email', 'hash.test@example.com')->first();
        $this->assertNotNull($student, 'No se creó el usuario para la prueba de hash.');

        // 1) No debe estar en texto plano
        $this->assertNotEquals($plainPassword, $student->password);

        // 2) Hash::check debe funcionar
        $this->assertTrue(
            Hash::check($plainPassword, $student->password),
            'Hash::check no reconoce la contraseña almacenada.'
        );
    }

    //Prueba 59, Rol correcto al crear profesor: Al crear un profesor se asigna rol teacher y no otro.
    //Este test también falla inesperadamente, debería asignar el rol teacher, pero no lo hace al crear el profesor
    /** @test */
    public function asigna_rol_teacher_al_crear_profesor()
    {
        // Admin autenticado con permisos
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        // Payload para crear un profesor
        $payload = [
            'first_name' => 'Marco',
            'last_name' => 'Jiménez',
            'email' => 'marco.jimenez@example.com',
            'gender' => 'male',
            'nationality' => 'Costa Rica',
            'phone' => '88887777',
            'address' => 'Cartago',
            'address2' => 'N/A',
            'city' => 'Cartago',
            'zip' => '20201',
            'birthday' => '1985-10-10',
            'religion' => 'None',
            'blood_type' => 'A+',
            'password' => 'Secret123!',
            'id_card_number' => '555666',
            'qualification' => 'Master en Matemáticas',
            'experience' => '5 años de docencia',
            'joining_date' => '2022-02-01'
        ];

        // Ejecutar creación
        $response = $this->actingAs($admin)
            ->post(route('school.teacher.create'), $payload);

        $response->assertStatus(302);

        // Obtener el profesor recién creado
        $teacher = User::where('email', 'marco.jimenez@example.com')->first();

        $this->assertNotNull($teacher);

        // ASSERT — Rol asignado correctamente
        $this->assertTrue(
            $teacher->hasRole('teacher'),
            "El usuario no recibió el rol teacher"
        );
    }

    //prueba 60, Usuario no admin, no puede acceder a "add student"
    /** @test */
    public function usuario_teacher_no_puede_ver_pantalla_agregar_estudiante()
    {
        // Crear usuario con rol teacher
        $teacher = User::factory()->create();
        $teacher->assignRole('teacher');

        // Intentar ingresar a pantalla Add Student
        $response = $this->actingAs($teacher)->get('/students/add');

        // ASSERT: Debe impedir acceso
        $response->assertStatus(403);
    }

    //Prueba 61, Usuario no admin no puede crear profesores

    /** @test */
    public function teacher_no_puede_crear_profesor()
    {
        // Usuario autenticado con rol teacher
        $teacher = User::factory()->create();
        $teacher->assignRole('teacher');

        // Payload básico válido
        $payload = [
            'first_name' => 'Daniel',
            'last_name' => 'Valverde',
            'email' => 'nuevo.teacher@example.com',
            'gender' => 'male',
            'nationality' => 'Costa Rica',
            'phone' => '12345678',
            'address' => 'San José',
            'address2' => 'N/A',
            'city' => 'Alajuela',
            'zip' => '10101',
            'birthday' => '1990-05-05',
            'religion' => 'None',
            'blood_type' => 'O+',
            'password' => 'Secret123!',
        ];

        // Teacher intenta crear otro profesor
        $response = $this->actingAs($teacher)
            ->post(route('school.teacher.create'), $payload);

        // Debe bloquear la acción
        $response->assertStatus(403);
    }

    //Prueba 62, Solo admin puede ver la lista de estudiantes totales
    /** @test */
    public function un_admin_si_puede_ver_lista_de_estudiantes()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->get(route('student.list.show'));

        $response->assertStatus(200);
    }

    //Prueba 63, Usuario no admin no puede modificar configuraciones del sistema Complemento de la 43: solo admin modifica settings
    /** @test */
    public function un_teacher_no_puede_modificar_configuraciones_academicas()
    {
        $teacher = User::factory()->create();
        $teacher->assignRole('teacher');

        $payload = [
            'status' => 1
        ];

        $response = $this->actingAs($teacher)
            ->post(route('school.final.marks.submission.status.update'), $payload);

        $response->assertStatus(403); // Esperado => no autorizado
    }

    //Prueba 64, Estudiante no puede ver la pantalla de registro de notas
    /** @test */
    public function estudiante_no_puede_acceder_a_crear_notas()
    {
        $student = User::factory()->create();
        $student->assignRole('student');

        $response = $this->actingAs($student)
            ->get('/marks/create');

        $response->assertStatus(403);
    }

    //Prueba 65, No permitir doble matrícula en la misma clase/sección Mismo estudiante + misma clase/sección dos veces: validación falla.
    /** @test */
    public function no_permite_doble_matricula_en_misma_clase_y_seccion()
    {
        $admin = $this->makeAdmin();

        // Crear estudiante
        $student = User::factory()->create(['email' => 'dup@example.com']);
        $student->assignRole('student');

        // Primera matrícula manual en BD
        \App\Models\StudentAcademicInfo::create([
            'student_id' => $student->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'session_id' => $this->session->id,
            'board_reg_no' => 'REG-001'
        ]);

        // Intento de matrícula duplicada mediante POST simulando formulario
        $payload = [
            'first_name' => 'Luis',
            'last_name' => 'Ramírez',
            'email' => 'dup@example.com', // mismo estudiante
            'gender' => 'male',
            'nationality' => 'Costa Rica',
            'phone' => '88888888',
            'address' => 'Santa Ana',
            'address2' => 'N/A',
            'city' => 'San José',
            'zip' => '10202',
            'birthday' => '2010-03-10',
            'religion' => 'None',
            'blood_type' => 'A+',
            'password' => 'Secret123!',
            'id_card_number' => '555555',

            'father_name' => 'Carlos',
            'father_phone' => '88881111',
            'mother_name' => 'Ana',
            'mother_phone' => '77776666',
            'parent_address' => 'Zapote',

            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'session_id' => $this->session->id,
            'board_reg_no' => 'REG-002'
        ];

        $response = $this
            ->actingAs($admin)
            ->post(route('school.student.create'), $payload);

        // ASSERT → debería fallar (si el sistema lo controlara)
        $response->assertStatus(302);
        $response->assertSessionHasErrors(); // error genérico
    }

    //Prueba 66,
    /** @test */
    public function no_permite_class_o_section_inexistente()
    {
        $admin = $this->makeAdmin();

        // IDs que NO existen en la base de datos
        $idClaseInexistente = 9999;
        $idSeccionInexistente = 8888;

        $payload = [
            'first_name' => 'Marco',
            'last_name' => 'Lopez',
            'email' => 'marcolo@example.com',
            'gender' => 'male',
            'nationality' => 'Costa Rica',
            'phone' => '12345678',
            'address' => 'San José',
            'address2' => 'N/A',
            'city' => 'Alajuela',
            'zip' => '10101',
            'birthday' => '2010-05-10',
            'religion' => 'None',
            'blood_type' => 'O+',
            'password' => 'Secret123!',
            'id_card_number' => '112233',

            'father_name' => 'Pedro',
            'father_phone' => '88881111',
            'mother_name' => 'Ana',
            'mother_phone' => '77776666',
            'parent_address' => 'Barrio Los Ángeles',

            'class_id' => $idClaseInexistente,
            'section_id' => $idSeccionInexistente,
            'session_id' => $this->session->id,
            'board_reg_no' => 'REG-777'
        ];

        $response = $this
            ->actingAs($admin)
            ->post(route('school.student.create'), $payload);

        // ASSERT
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['class_id', 'section_id']);
    }

    //Prueba 67, curso requiere un semestre válido
    /** @test */
    public function no_permite_crear_curso_con_semestre_inexistente()
    {
        $admin = $this->makeAdmin();

        $payload = [
            'course_name' => 'Biología',
            'course_type' => 'main',
            'class_id' => $this->class->id,
            'semester_id' => 9999, // Semestre NO existente
            'session_id' => $this->session->id,
        ];

        $response = $this
            ->actingAs($admin)
            ->post(route('school.course.create'), $payload);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['semester_id']);
    }

    //Prueba 68, Solo admin ve la lista de profesores
    /** @test */
    public function solo_admin_ve_la_lista_de_profesores()
    {
        // Admin
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        // Teacher que NO debería ver la lista
        $teacher = User::factory()->create();
        $teacher->assignRole('teacher');

        // --- Admin SÍ puede acceder ---
        $responseAdmin = $this
            ->actingAs($admin)
            ->get(route('teacher.list.show'));

        $responseAdmin->assertStatus(200); // Puede ver la lista


        // --- Teacher NO debe acceder ---
        $responseTeacher = $this
            ->actingAs($teacher)
            ->get(route('teacher.list.show'));

        $responseTeacher->assertStatus(403); // FORBIDDEN
    }

    //Prueba 69, Asistencia solo para estudiantes de esa clase Rechaza registros de asistencia para estudiantes que no pertenecen a la clase seleccionada
    /** @test */
    public function no_permite_registrar_asistencia_a_estudiante_fuera_de_la_clase()
    {
        // Admin autenticado
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        // Crear una segunda clase distinta
        $otraClase = \App\Models\SchoolClass::create([
            'session_id' => $this->session->id,
            'class_name' => 'Otra Clase'
        ]);

        // Crear sección para esa clase
        $otraSeccion = \App\Models\Section::create([
            'session_id' => $this->session->id,
            'class_id' => $otraClase->id,
            'section_name' => 'Z',
            'room_no' => '999'
        ]);

        // Crear estudiante
        $estudiante = User::factory()->create([
            'email' => 'correcto@example.com'
        ]);
        $estudiante->assignRole('student');

        // Registrar Academic Info en la CLASE CORRECTA (la tuya original, $this->class)
        \App\Models\StudentAcademicInfo::create([
            'student_id' => $estudiante->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'session_id' => $this->session->id,
            'board_reg_no' => 'BRN-X1'
        ]);

        // Payload intentando marcar asistencia en la OTRA clase distinta
        $payload = [
            'student_id' => $estudiante->id,
            'class_id' => $otraClase->id,
            'section_id' => $otraSeccion->id,
            'attendance_date' => now()->format('Y-m-d'),
            'status' => 'present',
            'session_id' => $this->session->id,
            'course_id' => $this->course->id // si attendance lo exige
        ];

        // Act — almacenar asistencia
        $response = $this
            ->actingAs($admin)
            ->post(route('attendances.store'), $payload);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['student_id']);
    }

    //Prueba 70, solo permite registrar asistencia en el rango del semestre
    /** @test */
    public function no_permite_registrar_asistencia_fuera_del_rango_del_semestre()
    {
        // Admin
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        // Crear estudiante
        $estudiante = User::factory()->create(['email' => 'range-test@example.com']);
        $estudiante->assignRole('student');

        // Academic info correcto
        \App\Models\StudentAcademicInfo::create([
            'student_id' => $estudiante->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'session_id' => $this->session->id,
            'board_reg_no' => 'BRN-55'
        ]);

        // FECHA FUERA DEL RANGO — antes del inicio del semestre
        $fechaFuera = now()->subYear()->format('Y-m-d');

        $payload = [
            'student_id' => $estudiante->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'attendance_date' => $fechaFuera,
            'status' => 'present',
            'session_id' => $this->session->id,
            'course_id' => $this->course->id,
        ];

        // ACT — registrar asistencia fuera de rango
        $response = $this
            ->actingAs($admin)
            ->post(route('attendances.store'), $payload);

        // ASSERT
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['attendance_date']);
    }

    //Prueba 71, Nota para estudiante inscrito en la materia No permite guardar nota para estudiante que
    // no está matriculado en el curso/materia correspondiente

    /** @test */
    public function no_permite_registrar_nota_para_estudiante_no_inscrito_en_el_curso()
    {
        // Admin
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        // ---- Estudiante A inscrito correctamente
        $estudianteInscrito = User::factory()->create(['email' => 'inscrito@example.com']);
        $estudianteInscrito->assignRole('student');

        \App\Models\StudentAcademicInfo::create([
            'student_id' => $estudianteInscrito->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'session_id' => $this->session->id,
            'board_reg_no' => 'BRN-555'
        ]);

        // ---- Estudiante B NO inscrito en esa clase
        $estudianteNoInscrito = User::factory()->create(['email' => 'noinscrito@example.com']);
        $estudianteNoInscrito->assignRole('student');

        // Payload para guardar nota
        $payload = [
            'student_id' => $estudianteNoInscrito->id, //No inscrito
            'course_id' => $this->course->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'session_id' => $this->session->id,
            'mark_obtained' => 90,
        ];

        $response = $this
            ->actingAs($admin)
            ->post(route('course.mark.store'), $payload);

        // ASSERT
        $response->assertStatus(302);
        $response->assertSessionHasErrors('student_id'); //debería error
    }



    //Prueba 72, solo profesores dueños de un curso pueden editar sus notas
    /** @test */
    public function solo_profesor_dueno_puede_editar_notas_de_su_curso()
    {
        // ADMIN (lo necesitamos por permisos)
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        //  PROFESOR dueño del curso
        $teacherOwner = User::factory()->create(['email' => 'owner@example.com']);
        $teacherOwner->assignRole('teacher');

        //  Profesor NO dueño
        $teacherIntruder = User::factory()->create(['email' => 'intruder@example.com']);
        $teacherIntruder->assignRole('teacher');

        // Asignar curso al teacherOwner
        \App\Models\AssignedTeacher::create([
            'teacher_id' => $teacherOwner->id,
            'course_id' => $this->course->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'session_id' => $this->session->id,
        ]);

        // Crear estudiante + nota
        $student = User::factory()->create();
        $student->assignRole('student');

        \App\Models\StudentAcademicInfo::create([
            'student_id' => $student->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'session_id' => $this->session->id,
            'board_reg_no' => 'BRX-55'
        ]);

        // Nota registrada previamente
        $nota = \App\Models\Mark::create([
            'student_id' => $student->id,
            'course_id' => $this->course->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'session_id' => $this->session->id,
            'mark_obtained' => 75
        ]);

        // Payload de edición
        $payload = [
            'mark_obtained' => 95
        ];

        //  Acceso  PROFESOR intruso intenta editar
        $response = $this
            ->actingAs($teacherIntruder)
            ->post(route('course.final.mark.submit.store'), array_merge($payload, [
                'student_id' => $student->id,
                'course_id' => $this->course->id,
                'class_id' => $this->class->id,
                'section_id' => $this->section->id,
                'session_id' => $this->session->id,
            ]));

        // ASSERT: Debe fallar
        $response->assertStatus(302);
        $response->assertSessionHasErrors('course_id'); // acceso denegado
    }



    //Prueba 73, el token de restablecimiento de contraseña tiene un solo uso
    /** @test */
    public function token_de_reset_solo_puede_usarse_una_vez()
    {
        // Usuario solicitante
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('OldPass123!')
        ]);

        // Crear token manualmente
        $token = \Illuminate\Support\Facades\Password::createToken($user);

        // Primer uso → válido
        $response1 = $this->post('/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'NewSecurePass#1',
            'password_confirmation' => 'NewSecurePass#1',
        ]);

        $response1->assertStatus(302); // Normal redirección
        $this->assertTrue(\Hash::check('NewSecurePass#1', $user->fresh()->password));

        // Segundo uso con el MISMO token → debería FALLAR
        $response2 = $this->post('/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'AnotherPass22?',
            'password_confirmation' => 'AnotherPass22?',
        ]);

        $response2->assertStatus(302);
        $response2->assertSessionHasErrors(); // ← token inválido
        $this->assertTrue(\Hash::check('NewSecurePass#1', $user->fresh()->password)); // no cambió
    }

    //prueba 74, solo admin puede crear cursos
    /** @test */
    public function solo_admin_puede_crear_cursos()
    {
        // Admin
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        // Teacher (no permitido)
        $teacher = User::factory()->create();
        $teacher->assignRole('teacher');

        $payload = [
            'course_name' => 'Matemáticas II',
            'class_id' => $this->class->id,
            'semester_id' => $this->semester->id,
            'course_type' => 'main',
            'session_id' => $this->session->id,
        ];

        // ADMIN DEBE PODER CREARLO
        $responseAdmin = $this->actingAs($admin)
            ->post(route('school.course.create'), $payload);

        $responseAdmin->assertStatus(302);
        $this->assertDatabaseHas('courses', ['course_name' => 'Matemáticas II']);

        // TEACHER NO DEBE PODER
        $responseTeacher = $this->actingAs($teacher)
            ->post(route('school.course.create'), $payload);

        // Como la app probablemente solo redirige, validamos redirección con error o forbidden
        $responseTeacher->assertStatus(403); // O 302 si redirige a login

        // Asegurar que NO se creó otra entrada duplicada
        $this->assertCount(
            1,
            \App\Models\Course::where('course_name', 'Matemáticas II')->get()
        );
    }



    //Prueba 75, Estudiante no puede acceder al módulo de asistencia
    /** @test */
    public function estudiante_no_puede_acceder_al_modulo_de_asistencia()
    {
        // Crear estudiante
        $student = User::factory()->create();
        $student->assignRole('student');

        // Intentar acceder al módulo de asistencia
        $response = $this->actingAs($student)
            ->get(route('attendance.index'));

        // Assert: No tiene acceso
        $response->assertStatus(403);
    }

}
