<?php

namespace Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Http\Requests\AttendanceStoreRequest;
use App\Http\Requests\AttendanceTypeUpdateRequest;
use App\Http\Requests\CourseStoreRequest;
use App\Http\Requests\ExamRuleStoreRequest;
use App\Http\Requests\ExamStoreRequest;
use App\Http\Requests\GradeRuleStoreRequest;
use App\Http\Requests\GradingSystemStoreRequest;
use App\Http\Requests\NoticeStoreRequest;
use App\Http\Requests\PasswordChangeRequest;
use App\Http\Requests\RoutineStoreRequest;
use App\Http\Requests\SchoolClassStoreRequest;
use App\Http\Requests\SchoolSessionBrowseRequest;
use App\Http\Requests\SchoolSessionStoreRequest;
use App\Http\Requests\SectionStoreRequest;
use App\Http\Requests\SemesterStoreRequest;
use App\Http\Requests\StoreFileRequest;
use App\Http\Requests\StudentStoreRequest;
use App\Http\Requests\TeacherAssignRequest;
use App\Http\Requests\TeacherStoreRequest;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TestCases extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
    }

    /**
     * User can view a login form.
     *
     * @return void
     */
    public function test_unauthenticated_user_can_view_a_login_form(){
        $response = $this->get('/login');
        $response->assertSuccessful();
        $response->assertViewIs('auth.login');
    }

    /**
     * Authenticated user cannot view a login form.
     *
     * @return void
     */
    public function test_authenticated_user_cannot_view_a_login_form(){
        $user = User::factory()->create([
            'role'     => 'admin',
            'password' => bcrypt('secret'),
        ]);

        $this->actingAs($user);

        $response = $this->get('/login');
        $response->assertRedirect('/home');
    }

    /**
     * User can log in successfully.
     *
     * @return void
     */
    public function test_user_can_log_in_successfully(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $user = User::factory()->create([
            'role'     => 'admin',
            'password' => bcrypt('secret'),
        ]);

        $response = $this->from('/login')->post('/login', [
            'email'    => $user->email,
            'password' => 'secret',
        ]);

        $response->assertRedirect('/home');
        $this->assertAuthenticatedAs($user);
    }

    /**
     * Academic session can be created successfully.
     *
     * @return void
     */
    public function test_academic_session_can_be_created_successfully()
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $admin = User::factory()->create([
            'role'    => 'admin',
        ]);

        $this->actingAs($admin);

        $role = Role::findOrCreate('admin', 'web');
        $role->givePermissionTo('create school sessions');

        $admin->assignRole($role);

        $data = [
            'session_name' => 'TestCase - TestCase',
        ];

        $response = $this->post('/school/session/create', $data);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Session creation was successful!');

        $this->assertDatabaseHas('school_sessions', [
            'session_name' => $data['session_name']
        ]);
    }

    /**
     * User without permission cannot authorize the request SchoolSessionStoreRequest.
     *
     * @return void
     */
    public function test_user_without_permission_cannot_authorize_session_store_request()
    {
        Permission::findOrCreate('create school sessions', 'web');

        $user = User::factory()->create();

        Route::post('/test-request', function (SchoolSessionStoreRequest $request) {
            return 'authorized';
        });

        $this->actingAs($user);

        $response = $this->post('/test-request', [
            'session_name' => 'Test'
        ]);

        $response->assertForbidden();
    }

    /**
     * User without permission cannot authorize the request SchoolSessionBrowseRequest.
     *
     * @return void
     */
    public function test_user_without_permission_cannot_authorize_browse_session()
    {
        Permission::findOrCreate('update browse by session', 'web');

        $user = User::factory()->create();

        Route::post('/test-request', function (SchoolSessionBrowseRequest $request) {
            return 'authorized';
        });

        $this->actingAs($user);

        $response = $this->post('/test-request', [
            'session_id' => 1
        ]);

        $response->assertForbidden();
    }

    /**
     * User without permission cannot authorize the request SectionStoreRequest.
     *
     * @return void
     */
    public function test_user_without_permission_cannot_authorize_creating_sections()
    {
        Permission::findOrCreate('create sections', 'web');

        $user = User::factory()->create();

        Route::post('/test-request', function (SectionStoreRequest $request) {
            return 'authorized';
        });

        $this->actingAs($user);

        $response = $this->post('/test-request', [
            'section_name' => 'A',
            'room_no' => '101',
            'class_id' => 1,
            'session_id' => 1
        ]);

        $response->assertForbidden();
    }

    /**
     * User without permission cannot authorize the request SemesterStoreRequest.
     *
     * @return void
     */
    public function test_user_without_permission_cannot_authorize_creating_semesters()
    {
        Permission::findOrCreate('create semesters', 'web');

        $user = User::factory()->create();

        Route::post('/test-request', function (SemesterStoreRequest $request) {
            return 'authorized';
        });

        $this->actingAs($user);

        $response = $this->post('/test-request', [
            'semester_name' => 'Primer Trimestre',
            'start_date' => '2025-03-01',
            'end_date' => '2025-06-01',
            'session_id' => 1,
        ]);

        $response->assertForbidden();
    }

    /**
     * User without either create assignments or create syllabi permission
     * cannot authorize the request.
     *
     * @return void
     */
    public function test_user_without_relevant_permissions_cannot_authorize_file_upload()
    {
        Permission::findOrCreate('create assignments', 'web');
        Permission::findOrCreate('create syllabi', 'web');

        $user = User::factory()->create();

        Route::post('/test-request', function (StoreFileRequest $request) {
            return 'authorized';
        });

        $this->actingAs($user);

        $response = $this->post('/test-request', [
            'file' => UploadedFile::fake()->create('test.pdf', 100)
        ]);

        $response->assertForbidden();
    }

    /**
     * User without permission cannot authorize the request TeacherStoreRequest.
     *
     * @return void
     */
    public function test_user_without_permission_cannot_authorize_creating_teachers()
    {
        Permission::findOrCreate('create users', 'web');

        $user = User::factory()->create();

        Route::post('/test-request', function (TeacherStoreRequest $request) {
            return 'authorized';
        });

        $this->actingAs($user);

        $response = $this->post('/test-request', [
            'first_name'    => 'Juan',
            'last_name'     => 'Pérez',
            'email'         => 'juan.perez@example.com',
            'gender'        => 'M',
            'nationality'   => 'AR',
            'phone'         => '123456789',
            'address'       => 'Calle Falsa 123',
            'city'          => 'Buenos Aires',
            'zip'           => '1000',
            'password'      => 'password123',
        ]);

        $response->assertForbidden();
    }

    /**
     * User without permission cannot authorize the request StudentStoreRequest.
     *
     * @return void
     */
    public function test_user_without_permission_cannot_authorize_creating_students()
    {
        Permission::findOrCreate('create users', 'web');

        $user = User::factory()->create();

        Route::post('/test-request', function (StudentStoreRequest $request) {
            return 'authorized';
        });

        $this->actingAs($user);

        $response = $this->post('/test-request', [
            'first_name'        => 'John',
            'last_name'         => 'Doe',
            'email'             => 'john@doe.com',
            'gender'            => 'M',
            'nationality'       => 'CR',
            'phone'             => '123456789',
            'address'           => 'StreetTest',
            'city'              => 'Alajuela',
            'zip'               => '1000',
            'birthday'          => '2000-05-10',
            'religion'          => 'Católica',
            'blood_type'        => 'O+',
            'password'          => 'password123',

            'father_name'       => 'John Doe',
            'father_phone'      => '123123123',
            'mother_name'       => 'Jane Doe',
            'mother_phone'      => '321321321',
            'parent_address'    => 'test address',

            'class_id'          => 1,
            'section_id'        => 1,
            'session_id'        => 1,
            'id_card_number'    => 'ABC12345',
        ]);

        $response->assertForbidden();
    }

    /**
     * User without permission cannot authorize the request AttendanceTypeUpdateRequest.
     *
     * @return void
     */
    public function test_user_without_permission_cannot_authorize_updating_attendance_type()
    {
        Permission::findOrCreate('update attendances type', 'web');

        $user = User::factory()->create();

        Route::post('/test-request', function (AttendanceTypeUpdateRequest $request) {
            return 'authorized';
        });

        $this->actingAs($user);

        $response = $this->post('/test-request', [
            'attendance_type' => 'Present'
        ]);

        $response->assertForbidden();
    }

    /**
     * User without permission cannot authorize the request TeacherAssignRequest.
     *
     * @return void
     */
    public function test_user_without_permission_cannot_authorize_assigning_teachers()
    {
        Permission::findOrCreate('assign teachers', 'web');

        $user = User::factory()->create();

        Route::post('/test-request', function (TeacherAssignRequest $request) {
            return 'authorized';
        });

        $this->actingAs($user);

        $response = $this->post('/test-request', [
            'course_id'     => 1,
            'semester_id'   => 1,
            'class_id'      => 1,
            'section_id'    => 1,
            'teacher_id'    => 1,
            'session_id'    => 1,
        ]);

        $response->assertForbidden();
    }

    /**
     * User without permission cannot authorize the request AttendanceStoreRequest.
     *
     * @return void
     */
    public function test_user_without_permission_cannot_authorize_storing_attendance()
    {
        Permission::findOrCreate('take attendances', 'web');

        $user = User::factory()->create();

        Route::post('/test-request', function (AttendanceStoreRequest $request) {
            return 'authorized';
        });

        $this->actingAs($user);

        $response = $this->post('/test-request', [
            'course_id'     => 1,
            'class_id'      => 1,
            'section_id'    => 1,
            'student_ids'   => [1, 2, 3],
            'status'        => ['present', 'absent', 'present'],
            'session_id'    => 1,
        ]);

        $response->assertForbidden();
    }

    /**
     * User without permission cannot authorize the request CourseStoreRequest.
     *
     * @return void
     */
    public function test_user_without_permission_cannot_authorize_creating_courses()
    {
        Permission::findOrCreate('create courses', 'web');

        $user = User::factory()->create();

        Route::post('/test-request', function (CourseStoreRequest $request) {
            return 'authorized';
        });

        $this->actingAs($user);

        $response = $this->post('/test-request', [
            'course_name' => 'Matemáticas',
            'course_type' => 'Obligatoria',
            'class_id'    => 1,
            'semester_id' => 1,
            'session_id'  => 1,
        ]);

        $response->assertForbidden();
    }

    /**
     * User without permission cannot authorize the request ExamStoreRequest.
     *
     * @return void
     */
    public function test_user_without_permission_cannot_authorize_creating_exams()
    {
        Permission::findOrCreate('create exams', 'web');

        $user = User::factory()->create();

        Route::post('/test-request', function (ExamStoreRequest $request) {
            return 'authorized';
        });

        $this->actingAs($user);

        $response = $this->post('/test-request', [
            'exam_name'   => 'Examen Trimestral',
            'start_date'  => '2025-03-01',
            'end_date'    => '2025-03-05',
            'semester_id' => 1,
            'class_id'    => 1,
            'course_id'   => 1,
            'session_id'  => 1,
        ]);

        $response->assertForbidden();
    }

    /**
     * User without permission cannot authorize the request ExamRuleStoreRequest.
     *
     * @return void
     */
    public function test_user_without_permission_cannot_authorize_creating_exam_rules()
    {
        Permission::findOrCreate('create exams rule', 'web');

        $user = User::factory()->create();

        Route::post('/test-request', function (ExamRuleStoreRequest $request) {
            return 'authorized';
        });

        $this->actingAs($user);

        $response = $this->post('/test-request', [
            'total_marks'              => 100,
            'pass_marks'               => 40,
            'marks_distribution_note'  => '40% teoría / 60% práctica',
            'exam_id'                  => 1,
            'session_id'               => 1,
        ]);

        $response->assertForbidden();
    }

    /**
     * User without permission cannot authorize the request GradeRuleStoreRequest.
     *
     * @return void
     */
    public function test_user_without_permission_cannot_authorize_creating_grade_rules()
    {
        Permission::findOrCreate('create grading systems rule', 'web');

        $user = User::factory()->create();

        Route::post('/test-request', function (GradeRuleStoreRequest $request) {
            return 'authorized';
        });

        $this->actingAs($user);

        $response = $this->post('/test-request', [
            'point'             => 4.0,
            'grade'             => 'A',
            'start_at'          => 90,
            'end_at'            => 100,
            'grading_system_id' => 1,
            'session_id'        => 1,
        ]);

        $response->assertForbidden();
    }

    /**
     * User without permission cannot authorize the request GradingSystemStoreRequest.
     *
     * @return void
     */
    public function test_user_without_permission_cannot_authorize_creating_grading_systems()
    {
        Permission::findOrCreate('create grading systems', 'web');

        $user = User::factory()->create();

        Route::post('/test-request', function (GradingSystemStoreRequest $request) {
            return 'authorized';
        });

        $this->actingAs($user);

        $response = $this->post('/test-request', [
            'system_name' => 'Sistema de Notas Secundaria',
            'class_id'    => 1,
            'semester_id' => 1,
            'session_id'  => 1,
        ]);

        $response->assertForbidden();
    }

    /**
     * User without permission cannot authorize the request NoticeStoreRequest.
     *
     * @return void
     */
    public function test_user_without_permission_cannot_authorize_creating_notices()
    {
        Permission::findOrCreate('create notices', 'web');

        $user = User::factory()->create();

        Route::post('/test-request', function (NoticeStoreRequest $request) {
            return 'authorized';
        });

        $this->actingAs($user);

        $response = $this->post('/test-request', [
            'notice'     => 'Reunión de padres el lunes a las 9 AM.',
            'session_id' => 1,
        ]);

        $response->assertForbidden();
    }

    /**
     * Authenticated user can authorize the request PasswordChangeRequest.
     *
     * @return void
     */
    public function test_authenticated_user_can_authorize_password_change_request()
    {
        $user = User::factory()->create();

        Route::post('/test-request', function (PasswordChangeRequest $request) {
            return 'authorized';
        });

        $this->actingAs($user);

        $response = $this->post('/test-request', [
            'old_password' => 'OldPassword1!',
            'new_password' => '712%aI]<je,K',
            'new_password_confirmation' => '712%aI]<je,K'
        ]);

        $response->assertOk();
    }

    /**
     * User without permission cannot authorize the request RoutineStoreRequest.
     *
     * @return void
     */
    public function test_user_without_permission_cannot_authorize_creating_routines()
    {
        Permission::findOrCreate('create routines', 'web');

        $user = User::factory()->create();

        Route::post('/test-request', function (RoutineStoreRequest $request) {
            return 'authorized';
        });

        $this->actingAs($user);

        $response = $this->post('/test-request', [
            'start'      => '08:00',
            'end'        => '09:00',
            'weekday'    => 1,
            'class_id'   => 1,
            'section_id' => 1,
            'course_id'  => 1,
            'session_id' => 1,
        ]);

        $response->assertForbidden();
    }

    /**
     * User without permission cannot authorize the request SchoolClassStoreRequest.
     *
     * @return void
     */
    public function test_user_without_permission_cannot_authorize_creating_school_classes()
    {
        Permission::findOrCreate('create classes', 'web');

        $user = User::factory()->create();

        Route::post('/test-request', function (SchoolClassStoreRequest $request) {
            return 'authorized';
        });

        $this->actingAs($user);

        $response = $this->post('/test-request', [
            'class_name' => '1-A',
            'session_id' => 1,
        ]);

        $response->assertForbidden();
    }

    /**
     * Validates old_password during password change.
     *
     * @return void
     */
    public function test_password_change_fails_when_old_password_is_incorrect()
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $user = User::factory()->create([
            'password' => Hash::make('CorrectOldPassword123!')
        ]);

        $this->actingAs($user);

        $response = $this->post(route('password.update'), [
            'old_password' => 'WrongOldPassword123!',
            'new_password' => 'NewPassword123!@',
            'new_password_confirmation' => 'NewPassword123!@',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHas('error', 'Password mismatched!');
    }

    /**
     * Authenticated user can authorize the request PasswordChangeRequest.
     *
     * @return void
     */
    public function test_new_password_fails_if_compromised()
    {
        $user = User::factory()->create();

        Route::post('/test-request', function (PasswordChangeRequest $request) {
            return 'authorized';
        });

        $this->actingAs($user);

        $response = $this->post('/test-request', [
            'old_password' => 'OldPassword1!',
            'new_password' => 'Password1!',
            'new_password_confirmation' => 'Password1!'
        ]);

        $response->assertStatus(302);

        $response->assertSessionHasErrors('new_password');

        $this->assertStringContainsString(
            'has appeared in a data leak',
            session('errors')->first('new_password')
        );
    }

}
