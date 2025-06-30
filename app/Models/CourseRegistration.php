<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CourseRegistration extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'course_id', 'credit_load'];
    protected $table = 'student_course_enrolments';
    
}
