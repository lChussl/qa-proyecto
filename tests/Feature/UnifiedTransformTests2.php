<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TestEstudianteCreacion extends TestCase
{
    use RefreshDatabase;



    
    
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


    /**
     * 
     * 
     * 75. Estudiante no puede acceder al módulo de asistencia > <
    */


    public function test_estudiante_no_puede_acceder_modulo_asistencia()
    {
        $student = User::factory()->create([
            'role' => 'student'
        ]);

        $response = $this->actingAs($student)->get('/attendance');

        $response->assertForbidden();
    }

    /**
     * 
     * 
     * 76. Que un usuario no autenticado pueda ver el formulario de log in.
    */


    public function test_usuario_no_autenticado_puede_ver_login()
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    /**
     * 
     * 
     * 77. Que un usuario autenticado no pueda ver la ruta de “login”, 
     * este debe ser redirigido a la ruta de “home”.
    */


    public function test_usuario_autenticado_no_puede_ver_login()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/login');

        $response->assertRedirect('/home');
    }

    /**
     * 
     * 
     * 78. Que un usuario pueda iniciar sesión y sea redirigido a la ruta de “home”.
    */


    public function test_usuario_puede_iniciar_sesion_y_ir_home()
    {
        $user = User::factory()->create([
            'password' => bcrypt('secret123')
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'secret123'
        ]);

        $response->assertRedirect('/home');
    }

    /**
     * 
     * 
     * 79. Que se pueda crear una sesión académica.
    */


    public function test_academic_session_can_be_created_succesfully()
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->acatingAs($admin);

        $role = Role::findOrCreate('admin', 'web');
        $role->givePermissionTo('create school sessions');

        $admin->assignRole($role);

        $data = [
            'session_name' => 'TestCase - TestCase',
        ];

        $response = $this->post('/school/session/create', $data);

        $response-> assertRedirect();
        $response->assertSessionHas('status', 'Session creation was succesful!');

        $this->assertDatabaseHas('school_sessions', [
            'session_name' => $data['session_name']
        ]);
        

       
    }

    /**
     * 
     * 
     * 80. Probar que el FormRequest SchoolSessionStoreRequest 
     * falle si el usuario que se está utilizando no tiene el 
     * permiso “create school sessions”.
    */


    public function test_user_without_permission_cannot_authorize_session_store_request()
    {
        Permission::findOrCreate('create school sessions', 'web');

        $user = User::factory()->create();

        Route::post('/test-request', function(SchoolSessionStoreRequest $request){
            return 'authorized';
        });

        $this->actingAs($user);

        $response = $this->post('/test-request', [
            'session_name' => 'Test'
        ]);

        $response->assertForbidden();



    }

    /**
     * 
     * 
     * 81. Probar que el FormRequest SchoolSessionBrowseRequest falle si el 
     * usuario que se está utilizando no tiene el permiso “update browse by session”.
    */


    public function test_user_without_permission_cannot_authorize_browse_session()
    {
        Permission::findOrCreate('update browse by session', 'web');

        $user = User::factory()->create();

        Route::post('/test-request', function(SchoolSessionBrowseRequest $request){
            return 'authorized';
        });

        $this->actingAs($user);

        $response = $this->post('/test-request', [
            'session_id' => 1
        ]);

        $response->assertForbidden();
    }



    /**
     * 
     * 
     * 82. Probar que el FormRequest SectionStoreRequest falle si el usuario que 
     * se está utilizando no tiene el permiso “update browse by session”.
    */


    public function test_falla_si_usuario_no_tiene_permiso_update_browse_by_session()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/sections', [
            'name' => 'A'
        ]);

        $response->assertForbidden();
    }

    /**
     * 
     * 
     * 83. Probar que el FormRequest SemesterStoreRequest falle si el 
     * usuario que se está utilizando no tiene el permiso “create semesters”.
    */


    public function test_falla_si_usuario_no_tiene_permiso_create_semesters()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/semesters', [
            'name' => 'Semester 1'
        ]);

        $response->assertForbidden();
    }

    /**
     * 
     * 
     * 84. Probar que el FormRequest StoreFileRequest falle si el usuario que se está 
     * utilizando no tiene el permiso “create assignments" y “create syllabi”.
    */


    public function test_falla_si_no_tiene_permisos_para_crear_archivos()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/files', [
            'file' => 'dummy.pdf'
        ]);

        $response->assertForbidden();
    }

    /**
     * 
     * 
     * 85. Probar que el FormRequest TeacherStoreRequest falle si el 
     * usuario que se está utilizando no tiene el permiso “create users".
    */


    public function test_falla_si_usuario_no_tiene_permiso_create_users()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/teachers', [
            'name' => 'John Doe'
        ]);

        $response->assertForbidden();
    }

    /**
     * 
     * 
     * 86. Probar que el FormRequest 
     * StudentStoreRequest falle si el usuario que se está utilizando no tiene el permiso “create users".
    */


    public function test_falla_si_usuario_no_tiene_permiso_create_users2()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/students', [
            'name' => 'Student A'
        ]);

        $response->assertForbidden();
    }

    /**
     * 
     * 
     * 87. Probar que el FormRequest AttendanceTypeUpdateRequest 
     * falle si el usuario que se está utilizando no tiene el permiso “update attendances type".
    */


    public function test_falla_si_usuario_no_tiene_permiso_update_attendances_type()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->put('/attendance-types/1', [
            'name' => 'Present'
        ]);

        $response->assertForbidden();
    }

    /**
     * 
     * 
     * 88. Probar que el FormRequest TeacherAssignRequest 
     * falle si el usuario que se está utilizando no tiene el permiso “assign teachers".
    */


    public function test_falla_si_usuario_no_tiene_permiso_assign_teachers()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/teacher-assign', [
            'teacher_id' => 1,
            'class_id' => 1
        ]);

        $response->assertForbidden();
    }

    /**
     * 
     * 
     * 89. Probar que el FormRequest AttendanceStoreRequest 
     * falle si el usuario que se está utilizando no tiene el permiso “take attendances".
    */


    public function test_falla_si_no_tiene_permiso_take_attendances()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/attendance', [
            'student_id' => 1
        ]);

        $response->assertForbidden();
    }

    /**
     * 
     * 
     * 90. Probar que el FormRequest CourseStoreRequest 
     * falle si el usuario que se está utilizando no tiene el permiso “create courses".
    */


    public function test_falla_si_usuario_no_tiene_permiso_create_courses()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/courses', [
            'name' => 'Mathematics'
        ]);

        $response->assertForbidden();
    }

    /**
     * 
     * 
     * 91. Probar que el FormRequest ExamStoreRequest 
     * falle si el usuario que se está utilizando no tiene el permiso “create exams".
    */


    public function test_falla_si_usuario_no_tiene_permiso_create_exams()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/exams', [
            'name' => 'Midterm'
        ]);

        $response->assertForbidden();
    }

    /**
     * 
     * 
     * 92. Probar que el FormRequest ExamRuleStoreRequest 
     * falle si el usuario que se está utilizando no tiene el permiso “create exams rule".
    */


    public function test_falla_si_usuario_no_tiene_permiso_create_exams_rule()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/exam-rules', [
            'rule' => 'Some rule'
        ]);

        $response->assertForbidden();
    }

    /**
     * 
     * 
     * 93. Probar que el FormRequest GradeRuleStoreRequest falle 
     * si el usuario que se está utilizando no tiene el permiso “create grading systems rule".
    */


    public function test_falla_si_no_tiene_permiso_create_grading_systems_rule()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/grade-rules', [
            'rule' => 'Rule A'
        ]);

        $response->assertForbidden();
    }

    /**
     * 
     * 
     * 94. Probar que el FormRequest GradingSystemStoreRequest falle 
     * si el usuario que se está utilizando no tiene el permiso “create grading systems".
    */


    public function test_falla_si_no_tiene_permiso_create_grading_systems()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/grading-systems', [
            'name' => 'System A'
        ]);

        $response->assertForbidden();
    }

    /**
     * 
     * 
     * 95. Probar que el FormRequest NoticeStoreRequest 
     * falle si el usuario que se está utilizando no tiene el permiso “create notices".
    */


    public function test_falla_si_usuario_no_tiene_permiso_create_notices()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/notices', [
            'title' => 'Notice A'
        ]);

        $response->assertForbidden();
    }

    /**
     * 
     * 
     * 96. Probar que el FormRequest PasswordChangeRequest falle si el usuario no está autenticado.
    */


    public function test_falla_si_usuario_no_autenticado()
    {
        $response = $this->post('/password/change', [
            'old_password' => '123',
            'new_password' => 'new123'
        ]);

        $response->assertRedirect('/login');
    }

    /**
     * 
     * 
     * 97. Probar que el FormRequest RoutineStoreRequest 
     * falle si el usuario que se está utilizando no tiene el permiso “create routines".
    */


    public function test_falla_si_usuario_no_tiene_permiso_create_routines()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/routines', [
            'name' => 'Routine A'
        ]);

        $response->assertForbidden();
    }

    /**
     * 
     * 
     * 98. Probar que el FormRequest SchoolClassStoreRequest 
     * falle si el usuario que se está utilizando no tiene el permiso “create classes".
    */


    public function test_falla_si_usuario_no_tiene_permiso_create_classes()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/classes', [
            'name' => 'Class A'
        ]);

        $response->assertForbidden();
    }

    /**
     * 
     * 
     * 99. Verificar que al cambiar la contraseña si se ingresa la 
     * contraseña anterior incorrectamente, el sistema devuelve el error de “Password mismatched!”
    */


    public function test_error_password_mismatch()
    {
        $user = User::factory()->create([
            'password' => bcrypt('correcta')
        ]);

        $response = $this->actingAs($user)->post('/password/change', [
            'old_password' => 'incorrecta',
            'new_password' => 'new1234'
        ]);

        $response->assertSessionHasErrors('old_password');
    }

    /**
     * 
     * 
     * 100. El FormRequest de PasswordChangeRequest valida correctamente que una nueva contraseña no se 
     * encuentre comprometida, asegurando que los usuarios tengan que ingresar contraseñas más seguras.
    */


    public function test_contrasena_nueva_no_comprometida()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/password/change', [
            'old_password' => '123456',
            'new_password' => 'password' // password comprometida
        ]);

        $response->assertSessionHasErrors('new_password');
    }
}
