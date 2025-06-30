<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Course extends Model
{
    use HasFactory;

    protected $fillable = ['course_title',	'course_code', 'description', 'image_url'];
    protected $table = 'courses';

    public function categories()
    {
        return $this->belongsToMany(CourseCategory::class, 'course_assignments', 'course_id', 'course_category_id');
    }
    
    public function users()
    {
        return $this->belongsToMany(User::class, 'student_course_enrolments', 'course_id', 'user_id');
    }
}
