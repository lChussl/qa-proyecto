<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Mark;
use App\Models\Course;
use App\Models\Semester;
use App\Models\StudentAcademicInfo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Spatie\Permission\Models\Role;

class UnifiedtransformTest extends TestCase
{
    use RefreshDatabase;

    protected $session;
    protected $class;
    protected $section;

    protected function setUp(): void
    {
        parent::setUp();

        // Evitar CSRF
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        // Seed del sistema
        $this->seed();

        // Crear sesión escolar *con los campos obligatorios*
        $this->session = \App\Models\SchoolSession::factory()->create([
            'session_name'   => 'Session Test ' . uniqid(),
            'starting_date'  => now()->toDateString(),
            'ending_date'    => now()->addMonths(6)->toDateString(),
        ]);

        // Crear clase asociada
        $this->class = \App\Models\SchoolClass::factory()->create([
            'session_id' => $this->session->id,
        ]);

        // Crear sección asociada
        $this->section = \App\Models\SchoolClassSection::factory()->create([
            'class_id' => $this->class->id,
        ]);
    }

    protected function makeAdmin(): User
    {
        $u = User::factory()->create();
        $u->assignRole('Admin');
        return $u;
    }

    protected function makeTeacher(): User
    {
        $u = User::factory()->create();
        $u->assignRole('Teacher');
        return $u;
    }

    protected function makeStudent(): User
    {
        $u = User::factory()->create();
        $u->assignRole('Student');
        return $u;
    }

    /* ================================================================
       50. Crear estudiante válido
       ================================================================ */
    public function test_crear_estudiante_valido_happy_path()
{
    $admin = $this->makeAdmin();
    // darle permiso necesario
    $admin->givePermissionTo('view users');

    // crear una sesión escolar (dependiendo cómo lo maneje tu sistema)
    $session = // ... crear o factory de school session

    $payload = [
        'first_name'   => 'Juan',
        'last_name'    => 'Pérez',
        'email'        => 'juanp@example.com',
        'gender'       => 'male',
        'phone'        => '12345678',
        'address'      => 'San José',
        'birthday'     => '2010-05-10',
        'religion'     => 'None',
        'blood_type'   => 'O+',
        'session_id'   => $this->session->id,
        'class_id'     => $this->class->id,
        'section_id'   => $this->section->id,
        'father_name'  => 'Padre Pérez',
        'father_phone' => '88881111',
    ];

    $response = $this->actingAs($admin)
         ->post(route('school.student.create'), $payload);

    $response->assertStatus(302);
    $this->assertDatabaseHas('users', [
        'email' => 'juanp@example.com',
    ]);
    $student = User::where('email','juanp@example.com')->first();
    $this->assertTrue($student->hasRole('student'));
}


    /* ================================================================
       51. No permite email duplicado
       ================================================================ */
    public function test_no_permite_email_duplicado()
    {
        $admin = $this->makeAdmin();

        User::factory()->create(['email' => 'juanp@example.com']);

        $payload = [
            'name' => 'Otro',
            'email' => 'juanp@example.com', // duplicado
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
            'birthday' => '2010-05-10',
            'blood_group' => 'O+',
            'id_card_number' => '99999',
            'class_id' => 1,
            'section_id' => 1,
        ];

        $response = $this->actingAs($admin)
            ->post(route('school.student.create'), $payload);

        $response->assertSessionHasErrors('email');
    }

    /* ================================================================
       52. Rechaza estudiante menor a edad mínima
       ================================================================ */
    public function test_no_permite_estudiante_menor_a_edad_minima()
    {
        $admin = $this->makeAdmin();

        $payload = [
            'name' => 'Peque',
            'email' => 'peque@example.com',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
            'birthday' => now()->subYears(3)->format('Y-m-d'), // muy joven
            'blood_group' => 'A+',
            'id_card_number' => '123',
            'class_id' => 1,
            'section_id' => 1,
        ];

        $response = $this->actingAs($admin)
            ->post(route('school.student.create'), $payload);

        $response->assertSessionHasErrors('birthday');
    }

    /* ================================================================
       53. Formato inválido de id_card_number es rechazado
       ================================================================ */
    public function test_formato_invalido_id_card_number_es_rechazado()
    {
        $admin = $this->makeAdmin();

        $payload = [
            'name' => 'Juan',
            'email' => 'invalidid@example.com',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
            'birthday' => '2010-01-01',
            'blood_group' => 'A+',
            'id_card_number' => '###??', // inválido
            'class_id' => 1,
            'section_id' => 1,
        ];

        $response = $this->actingAs($admin)
            ->post(route('school.student.create'), $payload);

        $response->assertSessionHasErrors('id_card_number');
    }

    /* ================================================================
       54. Tipo de sangre fuera de catálogo es rechazado
       ================================================================ */
    public function test_blood_group_fuera_de_catalogo_rechazado()
    {
        $admin = $this->makeAdmin();

        $payload = [
            'name' => 'Juan',
            'email' => 'blood@example.com',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
            'birthday' => '2010-01-01',
            'blood_group' => 'Z9', // no existe
            'id_card_number' => '123',
            'class_id' => 1,
            'section_id' => 1,
        ];

        $response = $this->actingAs($admin)
            ->post(route('school.student.create'), $payload);

        $response->assertSessionHasErrors('blood_group');
    }

    /* ================================================================
       55. Crear estudiante asigna academic info correctamente
       ================================================================ */
    public function test_crear_estudiante_asigna_academic_info()
    {
        $admin = $this->makeAdmin();

        $payload = [
            'name' => 'Juan',
            'email' => 'academic@example.com',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
            'birthday' => '2010-01-01',
            'blood_group' => 'A+',
            'id_card_number' => '123',
            'class_id' => 1,
            'section_id' => 1,
        ];

        $this->actingAs($admin)
            ->post(route('school.student.create'), $payload);

        $student = User::where('email','academic@example.com')->first();

        $this->assertNotNull(
            StudentAcademicInfo::where('student_id', $student->id)->first()
        );
    }

    /* ================================================================
       56. Marks devuelve solo notas del estudiante correcto
       ================================================================ */
    public function test_marks_devuelve_solo_notas_del_estudiante()
    {
        $student = $this->makeStudent();

        Mark::factory()->count(2)->create(['student_id' => $student->id]);
        Mark::factory()->count(3)->create(['student_id' => 999]); // no deben aparecer

        $marks = Mark::where('student_id', $student->id)->get();

        $this->assertCount(2, $marks);
    }

    /* ================================================================
       57. Password se guarda hasheado
       ================================================================ */
    public function test_password_se_guarda_hasheado()
    {
        $admin = $this->makeAdmin();

        $payload = [
            'name' => 'Hashed',
            'email' => 'hash@example.com',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
            'birthday' => '2010-01-01',
            'blood_group' => 'A+',
            'id_card_number' => '123',
            'class_id' => 1,
            'section_id' => 1,
        ];

        $this->actingAs($admin)
            ->post(route('school.student.create'), $payload);

        $student = User::where('email','hash@example.com')->first();

        $this->assertTrue(Hash::check('Secret123!', $student->password));
    }

    /* ================================================================
       58. Crear profesor asigna rol teacher
       ================================================================ */
    public function test_crear_profesor_asigna_rol_teacher()
    {
        $admin = $this->makeAdmin();

        $payload = [
            'name' => 'Profe',
            'email' => 'profe@example.com',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ];

        $this->actingAs($admin)
            ->post(route('school.teacher.create'), $payload);

        $teacher = User::where('email','profe@example.com')->first();
        $this->assertTrue($teacher->hasRole('teacher'));
    }

    /* ================================================================
       59. Teacher no puede ver crear estudiante
       ================================================================ */
    public function test_teacher_no_puede_ver_form_crear_estudiante()
    {
        $teacher = $this->makeTeacher();

        $response = $this->actingAs($teacher)
            ->get(route('student.create.show'));

        $response->assertStatus(403);
    }

    /* ================================================================
       60. Teacher NO puede crear profesor
       ================================================================ */
    public function test_teacher_no_puede_crear_profesor()
    {
        $teacher = $this->makeTeacher();

        $payload = [
            'name' => 'Otro Profe',
            'email' => 'otro@example.com',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ];

        $response = $this->actingAs($teacher)
            ->post(route('school.teacher.create'), $payload);

        $response->assertStatus(403);
    }

    /* ================================================================
       61. Solo admin puede ver lista de estudiantes
       ================================================================ */
    public function test_solo_admin_puede_ver_lista_estudiantes()
    {
        $teacher = $this->makeTeacher();

        $response = $this->actingAs($teacher)
            ->get(route('student.list.show'));

        $response->assertStatus(403);
    }

    /* ================================================================
       62. Solo admin puede modificar configuraciones
       ================================================================ */
    public function test_solo_admin_puede_modificar_configuraciones()
    {
        $teacher = $this->makeTeacher();

        $response = $this->actingAs($teacher)
            ->post(route('school.session.create'), []);

        $response->assertStatus(403);
    }

    /* ================================================================
       63. Estudiante no puede crear ni editar notas
       ================================================================ */
    public function test_estudiante_no_puede_crear_o_editar_notas()
    {
        $student = $this->makeStudent();

        $response = $this->actingAs($student)
            ->post(route('mark.store'), []);

        $response->assertStatus(403);
    }

    /* ================================================================
       64. No permite doble matrícula en clase y sección
       ================================================================ */
    public function test_no_permite_doble_matricula_misma_clase_y_seccion()
    {
        $admin = $this->makeAdmin();
        $student = $this->makeStudent();

        StudentAcademicInfo::factory()->create([
            'student_id' => $student->id,
            'class_id'   => 1,
            'section_id' => 1,
        ]);

        $payload = [
            'student_id' => $student->id,
            'class_id'   => 1,
            'section_id' => 1,
        ];

        $response = $this->actingAs($admin)
            ->post(route('school.student.update'), $payload);

        $response->assertSessionHasErrors();
    }

    /* ================================================================
       65. No permite clase o sección inexistente
       ================================================================ */
    public function test_no_permite_clase_o_seccion_inexistente()
    {
        $admin = $this->makeAdmin();

        $payload = [
            'name' => 'Juan',
            'email' => 'claseX@example.com',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
            'birthday' => '2010-01-01',
            'blood_group' => 'A+',
            'id_card_number' => '123',
            'class_id' => 999,
            'section_id' => 999,
        ];

        $response = $this->actingAs($admin)
            ->post(route('school.student.create'), $payload);

        $response->assertSessionHasErrors(['class_id','section_id']);
    }

    /* ================================================================
       66. No crea curso con semestre inválido
       ================================================================ */
    public function test_no_crea_curso_con_semestre_invalido()
    {
        $admin = $this->makeAdmin();

        $payload = [
            'name' => 'Curso malo',
            'semester_id' => 999, // inválido
        ];

        $response = $this->actingAs($admin)
            ->post(route('school.course.create'), $payload);

        $response->assertSessionHasErrors('semester_id');
    }

    /* ================================================================
       67. Solo admin puede ver lista profesores
       ================================================================ */
    public function test_solo_admin_puede_ver_lista_profesores()
    {
        $teacher = $this->makeTeacher();

        $response = $this->actingAs($teacher)
            ->get(route('teacher.list.show'));

        $response->assertStatus(403);
    }

    /* ================================================================
       68. No registra asistencia estudiante fuera de clase
       ================================================================ */
    public function test_no_registra_asistencia_estudiante_fuera_de_clase()
    {
        $teacher = $this->makeTeacher();
        $student = $this->makeStudent();

        $payload = [
            'student_id' => $student->id,
            'class_id' => 999, // no pertenece
        ];

        $response = $this->actingAs($teacher)
            ->post(route('attendance.store'), $payload);

        $response->assertSessionHasErrors();
    }

    /* ================================================================
       69. No registra asistencia fuera de rango semestre
       ================================================================ */
    public function test_no_registra_asistencia_fuera_de_rango_semestre()
    {
        $teacher = $this->makeTeacher();
        $student = $this->makeStudent();

        $semester = Semester::factory()->create([
            'start_date' => '2024-01-01',
            'end_date'   => '2024-03-01'
        ]);

        $payload = [
            'student_id' => $student->id,
            'semester_id'=> $semester->id,
            'date' => '2024-12-01', // fuera de rango
        ];

        $response = $this->actingAs($teacher)
            ->post(route('attendance.store'), $payload);

        $response->assertSessionHasErrors('date');
    }

    /* ================================================================
       70. No crea nota para estudiante no inscrito en curso
       ================================================================ */
    public function test_no_crea_nota_para_estudiante_no_inscrito()
    {
        $teacher = $this->makeTeacher();
        $student = $this->makeStudent();
        $course = Course::factory()->create();

        $payload = [
            'course_id' => $course->id,
            'student_id'=> $student->id,
            'value' => 95
        ];

        $response = $this->actingAs($teacher)
            ->post(route('mark.store'), $payload);

        $response->assertSessionHasErrors();
    }

    /* ================================================================
       71. Solo profesor dueño puede editar notas
       ================================================================ */
    public function test_solo_profesor_dueno_puede_editar_notas()
    {
        $teacher = $this->makeTeacher();
        $other = $this->makeTeacher();

        $course = Course::factory()->create([
            'teacher_id' => $teacher->id
        ]);

        $mark = Mark::factory()->create([
            'course_id' => $course->id
        ]);

        $payload = ['value' => 80];

        $response = $this->actingAs($other)
            ->post(route('mark.update', $mark->id), $payload);

        $response->assertStatus(403);
    }

    /* ================================================================
       72. Token reset password es de un solo uso
       ================================================================ */
    public function test_token_reset_password_es_de_un_solo_uso()
    {
        $user = User::factory()->create(['email'=>'reset@example.com']);

        $token = Password::createToken($user);

        $this->post(route('password.update'), [
            'email' => 'reset@example.com',
            'token' => $token,
            'password' => 'Newpass123!',
            'password_confirmation' => 'Newpass123!'
        ])->assertStatus(302);

        // segundo uso → no debería funcionar
        $this->post(route('password.update'), [
            'email' => 'reset@example.com',
            'token' => $token,
            'password' => 'Otherpass123!',
            'password_confirmation' => 'Otherpass123!'
        ])->assertSessionHasErrors();
    }

    /* ================================================================
       73. Solo admin puede crear curso
       ================================================================ */
    public function test_solo_admin_puede_crear_curso()
    {
        $teacher = $this->makeTeacher();

        $payload = [
            'name' => 'Curso',
            'semester_id' => 1,
        ];

        $response = $this->actingAs($teacher)
            ->post(route('school.course.create'), $payload);

        $response->assertStatus(403);
    }

    /* ================================================================
       74. Estudiante NO puede acceder módulo de asistencia
       ================================================================ */
    public function test_estudiante_no_puede_ver_modulo_asistencia()
    {
        $student = $this->makeStudent();

        $response = $this->actingAs($student)
            ->get(route('attendance.index'));

        $response->assertStatus(403);
    }
}
