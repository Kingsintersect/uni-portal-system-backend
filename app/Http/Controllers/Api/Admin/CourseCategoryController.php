<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\Faculty;
use App\Models\Department;



class CourseCategoryController extends Controller
{
    public function __construct()
    {
        // $this->middleware('auth:admin');
        $this->middleware('auth:api');

        //$this->middleware('auth:api')->only(['all_course_categories','course_category']);
    }

    public function create_course_category(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'faculty_id' => ['required', 'exists:faculties,id'],
            'department_id' => ['required', 'exists:departments,id'],
            'level' => ['required', 'regex:/^[1-6]00$/'],
            'semester' => ['required']
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'errors' => $validator->errors()], 422);
        }

        $faculty = Faculty::find($request->faculty_id);
        $department = Department::find($request->department_id);
        $facultyCode;
        $departmentCode;

        if ($faculty) {
            $facultyCode = strtoupper(substr($faculty->faculty_name, 0, 3));
        } else {
            // Handle case where department is not found
            return response()->json(['error' => 'Faculty not found'], 404);
        }

        if ($department) {
            $departmentCode = strtoupper(substr($department->department_name, 0, 3));
        } else {
            // Handle case where department is not found
            return response()->json(['error' => 'Department not found'], 404);
        }

        $short_code = $facultyCode . '-' . $departmentCode . '-' . $request->level . '-' . $request->semester;
        // Check if the short_code already exists
        $category_exists = CourseCategory::where('short_code', $short_code)->exists();
        if ($category_exists) {
            return response()->json([
                'status' => false,
                'error' => 'Course Category already exists'
            ]);
        }


        try {
            // Create a new course record
            $course_category = new CourseCategory();
            $course_category->faculty_id = $request->faculty_id;
            $course_category->department_id = $request->department_id;
            $course_category->level = $request->level;
            $course_category->semester = $request->semester;
            $course_category->short_code = $short_code;
            $course_category->save();

            // Return a success response
            return response()->json([
                'status' => 201,
                'response' => 'Course Category created successfully',
                'data' => $course_category
            ], 201);
        } catch (\Exception $e) {
            // Handle any errors during save
            return response()->json([
                'status' => 500,
                'response' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function all_course_categories(Request $request)
    {
        $course_categories = CourseCategory::with(['faculty', 'department'])->get();

        // Return a successful response with the data
        return response()->json([
            'status' => 200,
            'response' => 'Successful',
            "message" => "All course category(s) fetched successfully",
            "data" => $course_categories
        ], 200);
    }

    public function course_category(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'course_category_id' => ['required'],
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'errors' => $validator->errors()], 422);
        }

        $course_category = CourseCategory::find($request->course_category_id);

        // Check if the collection is empty
        if (!$course_category) {
            return response()->json([
                'status' => 404,
                'response' => 'Not Found',
                'message' => 'course not found'
            ], 404);
        }

        // Return a successful response with the data
        return response()->json([
            'status' => 200,
            'response' => 'Successful',
            "message" => "Course category fetched successfully",
            "data" => $course_category
        ], 200);
    }

    public function update_course_category(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'faculty_id' => ['required', 'exists:faculties,id'],
            'department_id' => ['required', 'exists:departments,id'],
            'level' => ['required', 'regex:/^[1-6]00$/'],
            'semester' => ['required']
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'errors' => $validator->errors()], 422);
        }

        $faculty = Faculty::find($request->faculty_id);
        $department = Department::find($request->department_id);
        $facultyCode;
        $departmentCode;

        if ($faculty) {
            $facultyCode = strtoupper(substr($faculty->faculty_name, 0, 3));
        } else {
            // Handle case where department is not found
            return response()->json(['error' => 'Faculty not found'], 404);
        }

        if ($department) {
            $departmentCode = strtoupper(substr($department->department_name, 0, 3));
        } else {
            // Handle case where department is not found
            return response()->json(['error' => 'Department not found'], 404);
        }

        $course_category = CourseCategory::find($request->course_category_id);
        if (!$course_category) {
            return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'Not Found!'], 404);
        }
        $course_category->update([
            'faculty_id' => $request->faculty_id,
            'department_id' => $request->department_id,
            'level' => $request->level,
            'semester' => $request->semester,
            'short_code' => $facultyCode . '-' . $departmentCode . '-' . $request->level . '-' . $request->semester,
        ]);
        return response()->json([
            'message' => 'course updated successful',
            'data' => $course_category
        ], 201);
    }

    public function delete_course_category(Request $request)
    {
        $course_category = CourseCategory::find($request->course_category_id);
        if (!$course_category) {
            return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'Not Found!'], 404);
        }

        // Delete the course itself
        $course_category->delete();

        // Return a success response
        return response()->json(['status' => 200, 'response' => 'Success', 'message' => 'course deleted successfully'], 200);
    }
}
