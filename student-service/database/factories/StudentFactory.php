<?php

namespace Database\Factories;

use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_number' => $this->faker->unique()->numberBetween(2500001211, 2500003000),
            'name' => $this->faker->name(),
            'department' => 'Bilgisayar Mühendisliği',
            'status' => 'active',
            'max_credits' => 30,
            'used_credits' => $this->faker->numberBetween(0, 15),
        ];
    }
}