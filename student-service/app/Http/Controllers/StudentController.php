<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    public function getProfile($student_number)
    {
        $student = Student::where('student_number', $student_number)->first();

        if (!$student) {
            return response()->json(['error' => 'Öğrenci bulunamadı.'], 404);
        }

        return response()->json(['data' => $student]);
    }
}