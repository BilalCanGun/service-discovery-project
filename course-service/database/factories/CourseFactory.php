<?php

namespace Database\Factories;

use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_code' => 'CENG' . $this->faker->unique()->numberBetween(100, 500),
            'name' => $this->faker->sentence(3),
            'credits' => $this->faker->randomElement([3, 5, 7]),
            'quota' => $this->faker->numberBetween(20, 50),
        ];
    }
}