<?php

//Se agruparon los UnitTest
// Source: AuthTest.php
namespace Tests\Feature {
    use Illuminate\Foundation\Testing\RefreshDatabase;
    use Illuminate\Foundation\Testing\WithFaker;
    use Illuminate\Support\Facades\Hash;
    use Tests\TestCase;
    use App\Models\User;

    class AuthTest extends TestCase
    {
        use RefreshDatabase;
        /**
         * @test
         *
         * Test whether user can view a login form if they visit a login route
         */
        public function user_can_view_a_login_form()
        {
            $response = $this->get('/login');
            $response->assertSuccessful();
            $response->assertViewIs('auth.login');
        }

        /**
         * @test
         *
         * Test if user cannot view a login page when they are authenticated(logged In)
         */
        public function user_cannot_view_login_form_when_authenticated()
        {
            $user = User::factory()->make();
            $response = $this->actingAs($user)->get('/login');
            $response->assertRedirect('/home');

        }

        /**
         * @test
         *
         * Test if user can successfully log in with correct credentials
         */
        public function user_can_login_successfully_with_correct_credentials()
        {
            $password = '::password::';
            $user = User::factory()->create([
                'password' => Hash::make($password),
            ]);

            $response = $this->post('/login', [
                'email' => $user->email,
                'password' => $password,
            ]);

            $response->assertRedirect('/home');
            $this->assertAuthenticatedAs($user);
        }


        /**
         * @test
         *
         * Test if user cannot log in with incorrect password.
         *
         */
        public function user_cannot_login_with_incorrect_password()
        {
            $password = '::password::';
            $user = User::factory()->create([
                'password' => Hash::make($password),
            ]);

            $response = $this->from('/login')->post('/login', [
                'email' => $user->email,
                'password' => '::incorrect-password::',
            ]);

            $response->assertRedirect('/login');
            $response->assertSessionHasErrors('email');
    //        $this->assertAuthenticatedAs($user);
        }
    }
}

// Source: StudentBirthDateValidationTest.php
namespace Tests\Feature {
    use Tests\TestCase;
    use App\Models\User;
    use Illuminate\Foundation\Testing\RefreshDatabase;

    class StudentBirthDateValidationTest extends TestCase
    {
        use RefreshDatabase;

        /** @test */
        public function it_rejects_invalid_birth_date_format()
        {
            $admin = User::factory()->create();

            $this->actingAs($admin);

            $response = $this->post('/students', [
                'first_name' => 'Ana',
                'last_name' => 'López',
                'nationality' => 'Costarricense',
                'birth_date' => '2025-13-40',
                'gender' => 'female',
                'classroom_id' => 1,
            ]);

            $response->assertSessionHasErrors(['birth_date']);
        }
    }
}

// Source: StudentClassroomValidationTest.php
namespace Tests\Feature {
    use Tests\TestCase;
    use App\Models\User;
    use Illuminate\Foundation\Testing\RefreshDatabase;

    class StudentClassroomValidationTest extends TestCase
    {
        use RefreshDatabase;

        /** @test */
        public function it_requires_classroom_id_to_create_student()
        {
            $admin = User::factory()->create();

            $this->actingAs($admin);

            $response = $this->post('/students', [
                'first_name' => 'María',
                'last_name' => 'Soto',
                'nationality' => 'Costarricense',
                'birth_date' => '2012-03-15',
                'gender' => 'female',
                'classroom_id' => null, // Sin aula
            ]);

            $response->assertSessionHasErrors(['classroom_id']);
        }
    }
}

// Source: StudentFirstNameValidationTest.php
namespace Tests\Feature {
    use Tests\TestCase;
    use App\Models\User;
    use Illuminate\Foundation\Testing\RefreshDatabase;

    class StudentFirstNameValidationTest extends TestCase
    {
        use RefreshDatabase;

        /** @test */
        public function it_rejects_numbers_in_first_name_field()
        {
            $admin = User::factory()->create([
                'email' => 'admin@ut.com',
                'password' => bcrypt('password'),
                'role' => 'admin',
            ]);

            $this->actingAs($admin);
            $response = $this->post('/students', [
                'first_name' => 'Juan123',
                'last_name' => 'Pérez',
                'nationality' => 'Costarricense',
                'birth_date' => '2010-05-10',
                'gender' => 'male',
                'classroom_id' => 1,
            ]);
            $response->assertSessionHasErrors(['first_name']);
        }
    }
}

// Source: StudentGenderValidationTest.php
namespace Tests\Feature {
    use Tests\TestCase;
    use App\Models\User;
    use Illuminate\Foundation\Testing\RefreshDatabase;

    class StudentGenderValidationTest extends TestCase
    {
        use RefreshDatabase;

        /** @test */
        public function it_rejects_invalid_gender_value()
        {
            $admin = User::factory()->create();

            $this->actingAs($admin);

            $response = $this->post('/students', [
                'first_name' => 'Luis',
                'last_name' => 'Gómez',
                'nationality' => 'Costarricense',
                'birth_date' => '2011-04-12',
                'gender' => 'robot', // Valor inválido
                'classroom_id' => 1,
            ]);

            $response->assertSessionHasErrors(['gender']);
        }
    }
}

// Source: StudentNationalityValidationTest.php
namespace Tests\Feature {
    use Tests\TestCase;
    use App\Models\User;
    use Illuminate\Foundation\Testing\RefreshDatabase;

    class StudentNationalityValidationTest extends TestCase
    {
        use RefreshDatabase;

        /** @test */
        public function it_rejects_numbers_in_nationality_field()
        {
            $admin = User::factory()->create([
                'email' => 'admin@ut.com',
                'password' => bcrypt('password'),
                'role' => 'admin',
            ]);

            $this->actingAs($admin);
            $response = $this->post('/students', [
                'first_name' => 'Carlos',
                'last_name' => 'Méndez',
                'nationality' => 'Costa123Rica',
                'birth_date' => '2010-05-10',
                'gender' => 'male',
                'classroom_id' => 1,
            ]);
            $response->assertSessionHasErrors(['nationality']);
        }
    }
}

// Source: AttendanceCreationSuccessTest.php
namespace Tests\Feature\Attendance {
    use Tests\TestCase;
    use App\Models\User;
    use Illuminate\Foundation\Testing\RefreshDatabase;

    class AttendanceCreationSuccessTest extends TestCase
    {
        use RefreshDatabase;

        /** @test */
        public function it_creates_attendance_with_valid_status()
        {
            $admin = User::factory()->create();
            $this->actingAs($admin);

            $response = $this->post('/attendance', [
                'student_id' => 1,
                'status' => 'present',
            ]);

            $response->assertRedirect('/attendance');
            $this->assertDatabaseHas('attendances', ['student_id' => 1, 'status' => 'present']);
        }
    }
}

// Source: AttendanceStatusValidationTest.php
namespace Tests\Feature\Attendance {
    use Tests\TestCase;
    use App\Models\User;
    use Illuminate\Foundation\Testing\RefreshDatabase;

    class AttendanceStatusValidationTest extends TestCase
    {
        use RefreshDatabase;

        /** @test */
        public function it_rejects_invalid_attendance_status()
        {
            $admin = User::factory()->create();
            $this->actingAs($admin);

            $response = $this->post('/attendance', [
                'student_id' => 1,
                'status' => 'desconocido',
            ]);

            $response->assertSessionHasErrors(['status']);
        }
    }
}

// Source: AttendanceStudentRequiredTest.php
namespace Tests\Feature\Attendance {
    use Tests\TestCase;
    use App\Models\User;
    use Illuminate\Foundation\Testing\RefreshDatabase;

    class AttendanceStudentRequiredTest extends TestCase
    {
        use RefreshDatabase;

        /** @test */
        public function it_requires_student_for_attendance()
        {
            $admin = User::factory()->create();
            $this->actingAs($admin);

            $response = $this->post('/attendance', [
                'student_id' => null,
                'status' => 'present',
            ]);

            $response->assertSessionHasErrors(['student_id']);
        }
    }
}

// Source: LoginUnregisteredEmailTest.php
namespace Tests\Feature\Auth {
    use Tests\TestCase;
    use Illuminate\Foundation\Testing\RefreshDatabase;

    class LoginUnregisteredEmailTest extends TestCase
    {
        use RefreshDatabase;

        /** @test */
        public function user_cannot_login_with_unregistered_email()
        {
            $response = $this->post('/login', [
                'email' => 'fake@correo.com',
                'password' => 'password',
            ]);

            $response->assertSessionHasErrors();
            $this->assertGuest();
        }
    }
}

// Source: LoginValidCredentialsTest.php
namespace Tests\Feature\Auth {
    use Tests\TestCase;
    use App\Models\User;
    use Illuminate\Foundation\Testing\RefreshDatabase;

    class LoginValidCredentialsTest extends TestCase
    {
        use RefreshDatabase;

        /** @test */
        public function user_can_login_with_valid_credentials()
        {
            $user = User::factory()->create([
                'email' => 'admin@ut.com',
                'password' => bcrypt('password'),
            ]);

            $response = $this->post('/login', [
                'email' => 'admin@ut.com',
                'password' => 'password',
            ]);

            $response->assertRedirect('/dashboard');
            $this->assertAuthenticatedAs($user);
        }
    }
}

// Source: LoginWrongPasswordTest.php
namespace Tests\Feature\Auth {
    use Tests\TestCase;
    use App\Models\User;
    use Illuminate\Foundation\Testing\RefreshDatabase;

    class LoginWrongPasswordTest extends TestCase
    {
        use RefreshDatabase;

        /** @test */
        public function user_cannot_login_with_wrong_password()
        {
            User::factory()->create([
                'email' => 'admin@ut.com',
                'password' => bcrypt('password'),
            ]);

            $response = $this->post('/login', [
                'email' => 'admin@ut.com',
                'password' => 'wrongpassword',
            ]);

            $response->assertSessionHasErrors();
            $this->assertGuest();
        }
    }
}

// Source: LogoutTest .php
namespace Tests\Feature\Auth {
    use Tests\TestCase;
    use App\Models\User;
    use Illuminate\Foundation\Testing\RefreshDatabase;

    class LogoutTest extends TestCase
    {
        use RefreshDatabase;

        /** @test */
        public function user_can_logout_successfully()
        {
            $user = User::factory()->create();
            $this->actingAs($user);

            $response = $this->post('/logout');

            $response->assertRedirect('/login');
            $this->assertGuest();
        }
    }
}

// Source: GradeNegativeScoreTest .php
namespace Tests\Feature\Grades {
    use Tests\TestCase;
    use App\Models\User;
    use Illuminate\Foundation\Testing\RefreshDatabase;

    class GradeNegativeScoreTest extends TestCase
    {
        use RefreshDatabase;

        /** @test */
        public function it_rejects_negative_score_in_grade()
        {
            $admin = User::factory()->create();
            $this->actingAs($admin);

            $response = $this->post('/grades', [
                'student_id' => 1,
                'subject_id' => 1,
                'score' => -10,
            ]);

            $response->assertSessionHasErrors(['score']);
        }
    }
}

// Source: GradeScoreRangeTest.php
namespace Tests\Feature\Grades {
    use Tests\TestCase;
    use App\Models\User;
    use Illuminate\Foundation\Testing\RefreshDatabase;

    class GradeScoreRangeTest extends TestCase
    {
        use RefreshDatabase;

        /** @test */
        public function it_rejects_score_above_100()
        {
            $admin = User::factory()->create();
            $this->actingAs($admin);

            $response = $this->post('/grades', [
                'student_id' => 1,
                'subject_id' => 1,
                'score' => 120,
            ]);

            $response->assertSessionHasErrors(['score']);
        }
    }
}

// Source: GradeScoreTextTest .php
namespace Tests\Feature\Grades {
    use Tests\TestCase;
    use App\Models\User;
    use Illuminate\Foundation\Testing\RefreshDatabase;

    class GradeScoreTextTest extends TestCase
    {
        use RefreshDatabase;

        /** @test */
        public function it_rejects_text_in_score_field()
        {
            $admin = User::factory()->create();
            $this->actingAs($admin);

            $response = $this->post('/grades', [
                'student_id' => 1,
                'subject_id' => 1,
                'score' => 'noventa',
            ]);

            $response->assertSessionHasErrors(['score']);
        }
    }
}

// Source: GradeStudentRequiredTest.php
namespace Tests\Feature\Grades {
    use Tests\TestCase;
    use App\Models\User;
    use Illuminate\Foundation\Testing\RefreshDatabase;

    class GradeStudentRequiredTest extends TestCase
    {
        use RefreshDatabase;

        /** @test */
        public function it_requires_student_for_grade()
        {
            $admin = User::factory()->create();
            $this->actingAs($admin);

            $response = $this->post('/grades', [
                'student_id' => null,
                'subject_id' => 1,
                'score' => 90,
            ]);

            $response->assertSessionHasErrors(['student_id']);
        }
    }
}

// Source: GradeSubjectRequiredTest.php
namespace Tests\Feature\Grades {
    use Tests\TestCase;
    use App\Models\User;
    use Illuminate\Foundation\Testing\RefreshDatabase;

    class GradeSubjectRequiredTest extends TestCase
    {
        use RefreshDatabase;

        /** @test */
        public function it_requires_subject_for_grade()
        {
            $admin = User::factory()->create();
            $this->actingAs($admin);

            $response = $this->post('/grades', [
                'student_id' => 1,
                'subject_id' => null,
                'score' => 90,
            ]);

            $response->assertSessionHasErrors(['subject_id']);
        }
    }
}

// Source: StudentAddressOptionalTest.php
namespace Tests\Feature\Students {
    use Tests\TestCase;
    use App\Models\User;
    use App\Models\Classroom;
    use Illuminate\Foundation\Testing\RefreshDatabase;

    class StudentAddressOptionalTest extends TestCase
    {
        use RefreshDatabase;

        /** @test */
        public function it_allows_empty_address()
        {
            $admin = User::factory()->create();
            $classroom = Classroom::factory()->create();

            $this->actingAs($admin);

            $response = $this->post('/students', [
                'first_name' => 'Laura',
                'last_name' => 'Jiménez',
                'nationality' => 'Costarricense',
                'birth_date' => '2010-01-01',
                'gender' => 'female',
                'classroom_id' => $classroom->id,
                'address' => '',
            ]);

            $response->assertSessionDoesntHaveErrors(['address']);
        }
    }
}

// Source: StudentBirthDateRequiredTest.php
namespace Tests\Feature\Students {
    use Tests\TestCase;
    use App\Models\User;
    use Illuminate\Foundation\Testing\RefreshDatabase;

    class StudentBirthDateRequiredTest extends TestCase
    {
        use RefreshDatabase;

        /** @test */
        public function it_requires_birth_date_for_student()
        {
            $admin = User::factory()->create();
            $this->actingAs($admin);

            $response = $this->post('/students', [
                'first_name' => 'Lucía',
                'last_name' => 'Vargas',
                'nationality' => 'Costarricense',
                'birth_date' => null,
                'gender' => 'female',
                'classroom_id' => 1,
            ]);

            $response->assertSessionHasErrors(['birth_date']);
        }
    }
}

// Source: StudentEmailUniquenessTest.php
namespace Tests\Feature\Students {
    use Tests\TestCase;
    #use App\Models\User;
    use App\Models\Student;
    use Illuminate\Foundation\Testing\RefreshDatabase;

    class StudentEmailUniquenessTest extends TestCase
    {
        use RefreshDatabase;

        /** @test */
        public function it_rejects_duplicate_email()
        {
            $admin = User::factory()->create();
            $this->actingAs($admin);

            Student::factory()->create(['email' => 'test@correo.com']);

            $response = $this->post('/students', [
                'first_name' => 'Ana',
                'last_name' => 'Soto',
                'nationality' => 'Costarricense',
                'birth_date' => '2010-01-01',
                'gender' => 'female',
                'classroom_id' => 1,
                'email' => 'test@correo.com',
            ]);

            $response->assertSessionHasErrors(['email']);
        }
    }
}

// Source: StudentLastNameValidationTest.php
namespace Tests\Feature\Students {
    use Tests\TestCase;
    use App\Models\User;
    use Illuminate\Foundation\Testing\RefreshDatabase;

    class StudentLastNameValidationTest extends TestCase
    {
        use RefreshDatabase;

        /** @test */
        public function it_requires_last_name()
        {
            $admin = User::factory()->create();
            $this->actingAs($admin);

            $response = $this->post('/students', [
                'first_name' => 'Luis',
                'last_name' => '',
                'nationality' => 'Costarricense',
                'birth_date' => '2010-01-01',
                'gender' => 'male',
                'classroom_id' => 1,
            ]);

            $response->assertSessionHasErrors(['last_name']);
        }
    }
}

// Source: StudentPhoneValidationTest.php
namespace Tests\Feature\Students {
    use Tests\TestCase;
    use App\Models\User;
    use Illuminate\Foundation\Testing\RefreshDatabase;

    class StudentPhoneValidationTest extends TestCase
    {
        use RefreshDatabase;

        /** @test */
        public function it_rejects_invalid_phone_format()
        {
            $admin = User::factory()->create();
            $this->actingAs($admin);

            $response = $this->post('/students', [
                'first_name' => 'Carlos',
                'last_name' => 'Ramírez',
                'nationality' => 'Costarricense',
                'birth_date' => '2010-01-01',
                'gender' => 'male',
                'classroom_id' => 1,
                'phone' => 'abc123',
            ]);

            $response->assertSessionHasErrors(['phone']);
        }
    }
}

// Source: SubjectNameRequiredTest.php
namespace Tests\Feature\Subjects {
    use Tests\TestCase;
    use App\Models\User;
    use Illuminate\Foundation\Testing\RefreshDatabase;

    class SubjectNameRequiredTest extends TestCase
    {
        use RefreshDatabase;

        /** @test */
        public function it_requires_subject_name()
        {
            $admin = User::factory()->create();
            $this->actingAs($admin);

            $response = $this->post('/subjects', [
                'name' => '',
            ]);

            $response->assertSessionHasErrors(['name']);
        }
    }
}

// Source: SubjectNameUniquenessTest.php
namespace Tests\Feature\Subjects {
    use Tests\TestCase;
    use App\Models\User;
    use App\Models\Subject;
    use Illuminate\Foundation\Testing\RefreshDatabase;

    class SubjectNameUniquenessTest extends TestCase
    {
        use RefreshDatabase;

        /** @test */
        public function it_rejects_duplicate_subject_name()
        {
            $admin = User::factory()->create();
            $this->actingAs($admin);

            Subject::factory()->create(['name' => 'Matemáticas']);

            $response = $this->post('/subjects', [
                'name' => 'Matemáticas',
                'teacher_id' => 1,
            ]);

            $response->assertSessionHasErrors(['name']);
        }
    }
}

// Source: SubjectTeacherRequiredTest.php
namespace Tests\Feature\Subjects {
    use Tests\TestCase;
    use App\Models\User;
    use Illuminate\Foundation\Testing\RefreshDatabase;

    class SubjectTeacherRequiredTest extends TestCase
    {
        use RefreshDatabase;

        /** @test */
        public function it_requires_teacher_for_subject()
        {
            $admin = User::factory()->create();
            $this->actingAs($admin);

            $response = $this->post('/subjects', [
                'name' => 'Física',
                'teacher_id' => null,
            ]);

            $response->assertSessionHasErrors(['teacher_id']);
        }
    }
}

// Source: TeacherEmailFormatTest.php
namespace Tests\Feature\Teachers {
    use Tests\TestCase;
    use App\Models\User;
    use Illuminate\Foundation\Testing\RefreshDatabase;

    class TeacherEmailFormatTest extends TestCase
    {
        use RefreshDatabase;

        /** @test */
        public function it_rejects_invalid_email_format_for_teacher()
        {
            $admin = User::factory()->create();
            $this->actingAs($admin);

            $response = $this->post('/teachers', [
                'name' => 'Mario',
                'email' => 'profesor.com',
            ]);

            $response->assertSessionHasErrors(['email']);
        }
    }
}

// Source: TeacherEmailRequiredTest.php
namespace Tests\Feature\Teachers {
    use Tests\TestCase;
    use App\Models\User;
    use Illuminate\Foundation\Testing\RefreshDatabase;

    class TeacherEmailRequiredTest extends TestCase
    {
        use RefreshDatabase;

        /** @test */
        public function it_requires_email_for_teacher()
        {
            $admin = User::factory()->create();
            $this->actingAs($admin);

            $response = $this->post('/teachers', [
                'name' => 'Sofía Jiménez',
                'email' => '',
            ]);

            $response->assertSessionHasErrors(['email']);
        }
    }
}

// Source: TeacherNameValidationTest.php
namespace Tests\Feature\Teachers {
    use Tests\TestCase;
    use App\Models\User;
    use Illuminate\Foundation\Testing\RefreshDatabase;

    class TeacherNameValidationTest extends TestCase
    {
        use RefreshDatabase;

        /** @test */
        public function it_rejects_numbers_in_teacher_name()
        {
            $admin = User::factory()->create();
            $this->actingAs($admin);

            $response = $this->post('/teachers', [
                'name' => 'Prof3Mario',
                'email' => 'mario@ut.com',
            ]);

            $response->assertSessionHasErrors(['name']);
        }
    }
}

// Source: TeacherPhoneLengthTest .php
namespace Tests\Feature\Teachers {
    use Tests\TestCase;
    use App\Models\User;
    use Illuminate\Foundation\Testing\RefreshDatabase;

    class TeacherPhoneLengthTest extends TestCase
    {
        use RefreshDatabase;

        /** @test */
        public function it_rejects_short_phone_number_for_teacher()
        {
            $admin = User::factory()->create();
            $this->actingAs($admin);

            $response = $this->post('/teachers', [
                'name' => 'Mario',
                'email' => 'mario@ut.com',
                'phone' => '12345',
            ]);

            $response->assertSessionHasErrors(['phone']);
        }
    }
}

// Source: TeacherPhoneValidationTest.php
namespace Tests\Feature\Teachers {
    use Tests\TestCase;
    use App\Models\User;
    use Illuminate\Foundation\Testing\RefreshDatabase;

    class TeacherPhoneValidationTest extends TestCase
    {
        use RefreshDatabase;

        /** @test */
        public function it_rejects_non_numeric_phone_for_teacher()
        {
            $admin = User::factory()->create();
            $this->actingAs($admin);

            $response = $this->post('/teachers', [
                'name' => 'Mario',
                'email' => 'mario@ut.com',
                'phone' => '123abc',
            ]);

            $response->assertSessionHasErrors(['phone']);
        }
    }
}

// Source: TeacherSubjectRequiredTest.php
namespace Tests\Feature\Teachers {
    use Tests\TestCase;
    use App\Models\User;
    use Illuminate\Foundation\Testing\RefreshDatabase;

    class TeacherSubjectRequiredTest extends TestCase
    {
        use RefreshDatabase;

        /** @test */
        public function it_requires_subject_for_teacher()
        {
            $admin = User::factory()->create();
            $this->actingAs($admin);

            $response = $this->post('/teachers', [
                'name' => 'Mario',
                'email' => 'mario@ut.com',
                'subject_id' => null,
            ]);

            $response->assertSessionHasErrors(['subject_id']);
        }
    }
}

namespace {
    use PHPUnit\Framework\TestCase;
    use PHPUnit\Framework\TestSuite;

    final class AlissonUnitTest
    {
        public static function suite(): TestSuite
        {
            $suite = new TestSuite('AlissonUnitTest');

            foreach (get_declared_classes() as $class) {
                if ($class === self::class) continue;

                if (!is_subclass_of($class, TestCase::class)) continue;

                $ref = new \ReflectionClass($class);
                if ($ref->getFileName() !== __FILE__) continue;

                if (!preg_match('/Test$/', $ref->getName())) continue;

                $suite->addTestSuite($class);
            }

            return $suite;
        }
    }
}
