<?php

namespace Database\Factories;

use App\Models\Exam;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExamFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Exam::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'exam_name' => $this->faker->word,
            'start_date' => now(),
            'end_date' => now()->addHours(2),
            'session_id' => 1,
            'semester_id' => 1,
            'class_id' => 1,
            'course_id' => 1
        ];
    }
}
