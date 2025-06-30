<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourseCategory extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'faculty_id',
        'department_id',
        'level',
        'semester',
        'short_code',
    ];
    
    protected $table = 'course_categories';
    
    public function faculty()
    {
        return $this->belongsTo(Faculty::class,'faculty_id','id');
    }
    
    public function department()
    {
        return $this->belongsTo(Department::class,'department_id','id');
    }
    
    public function courses()
    {
        return $this->belongsToMany(Course::class, 'course_assignments', 'course_category_id', 'course_id')
        ->withPivot('credit_load');    
    }
}

