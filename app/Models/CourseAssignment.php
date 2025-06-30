<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourseAssignment extends Model
{
    use HasFactory;

    protected $fillable = ['course_id', 'course_category_id', 'credit_load'];
    protected $table = 'course_assignments';
    
    public function courses()
    {
        return $this->belongsTo(Course::class,'course_id','id');
    }
}
