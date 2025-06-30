<?php

namespace App\Http\Controllers\Api\User;

use App\Models\CourseCategory;
use App\Models\Department;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class CourseRegistrationController extends Controller
{
    // List all the courses belonging to the student's course department
    // api/v1/course-enrolment/courses
    public function getCourses(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'level' => ['required', 'string']
        ]);

        if($validator->fails()) {
            return response()->json([
                'status' => false,
                'error' => $validator->errors()
            ]);
        }
        // return response()->json('Code reached here');
        $level = $request->level; // 'SOC-ECO-100-1SM'

        // Get the course category attributed to the department
        $course_category = CourseCategory::where('short_code', $level)
        ->with(['faculty' => function ($query) {
            $query->select('id', 'faculty_name');
        }])
        ->with(['department' => function ($query) {
            $query->select('id', 'department_name');
        }])
        ->with(['courses' => function ($query) {
            $query->select('courses.id', 'course_title', 'course_code')
                  ->withPivot('credit_load');
        }])
        ->first();  
        // course table - add image_url - cloudinary

        
        if(!$course_category) {
            return response()->json([
                'status' => false,
                'response' => 'Course Category does not exist'
            ], 404);
        }

        $faculty_name = $course_category->faculty->faculty_name;
        $department_name = $course_category->department->department_name;

        $courses = $course_category->courses->map(function ($course) {
            return [
                'course_id' => $course->id,
                'course_title' => $course->course_title,
                'course_code' => $course->course_code,
                'credit_load' => $course->pivot->credit_load,
            ];
        });
        
        return response()->json([
            'status' => true,
            'faculty' => $faculty_name,
            'department' => $department_name,
            'course_category' => $request->level,
            'Level' => $course_category->level,
            'semester' => $course_category->semester,
            'data' => $courses
        ], 200);
    }
}
