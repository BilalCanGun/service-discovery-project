<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Enrollment extends Model
{
    protected $fillable = ['student_number', 'course_id', 'enrolled_at'];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }
}