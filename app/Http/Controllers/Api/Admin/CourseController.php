<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Course;
// use App\Traits\ImageUpload;

class CourseController extends Controller
{
    public function __construct()
    {
        //$this->middleware('auth:admin');

        //$this->middleware('auth:api')->only(['all_courses','course']);
    }

    // use ImageUpload; // Use the ImageUpload Trait

    public function create_course(Request $request)
    {

        // Validate the input
        $validator = Validator::make($request->all(), [
            'course_title' => ['required', 'string', 'max:255'],
            // 'course_code' => ['required', 'unique:courses,course_code', 'regex:/^(ESUT-)?[A-Z]{3}\s[0-9]{3}$/'],
            'course_code' => ['required', 'unique:courses,course_code'],
            'description' => ['nullable', 'string'],
            // 'photo' => ['nullable', 'mimes:jpg,png,jpeg', 'max:25000'], // Optional image
        ], [
            'course_code.regex' => 'The course code format is invalid. It should be like "CSC 101", "ECO 102", "MAT 201", or "ESUT-ECO 102".',
            'photo.mimes' => 'The photo must be a file of type: jpg, png, jpeg.',
            // 'photo.max' => 'The photo size must not exceed 25MB.',
        ]);

        // Return validation errors if validation fails
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'response' => 'Validation Error',
                'errors' => $validator->errors()
            ], 422);
        }

        // try {
        //     // Initialize course fields
        //     $course = new Course();
        //     $course->course_title = $request->course_title;
        //     $course->course_code = $request->course_code;
        //     $course->description = $request->description;

        //     // Handle optional image upload
        //     // if(!$request->hasFile('photo')) {
        //     //     $course->image_url = null; // Default to null if no image is uploaded
        //     // }

        //     // Save course record to the database
        //     // $file_path = $this->upload($request); // Use ImageUpload Trait
        //     // $course->image_url = url($file_path);
        //     $course->save();

        //     // Return a success response
        //     return response()->json([
        //         'status' => true,
        //         'response' => 'Course created successfully',
        //         'data' => $course
        //     ], 201);
        // } catch (\Exception $e) {
        //     // Log the error for debugging
        //     \Log::error('Error creating course:', ['exception' => $e]);

        //     // Handle internal server error
        //     return response()->json([
        //         'status' => false,
        //         'response' => 'Internal Server Error',
        //         'error' => 'An error occurred while creating the course. Please try again.'
        //     ], 500);

        $course = Course::create([
            'course_title' => $request->input('course_title'),
            'course_code' => $request->input('course_code'),
            'description' => $request->input('description')
        ], 500);

        if (!$course) {
            return response()->json([
                'status' => false,
                'response' => 'Internal Server Error',
                'error' => 'An error occurred while creating the course. Please try again.'
            ]);
        }

        return response()->json([
            'status' => true,
            'response' => 'Course created successfully',
            'data' => $course
        ], 201);
    }


    public function all_courses(Request $request)
    {
        $courses = Course::get();

        // Return a successful response with the data
        return response()->json([
            'status' => 200,
            'response' => 'Successful',
            "message" => "All course(s) fetched successfully",
            "data" => $courses
        ], 200);
    }

    public function course(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'course_id' => ['required'],
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'errors' => $validator->errors()], 422);
        }

        $course = Course::find($request->course_id);

        // Check if the collection is empty
        if (!$course) {
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
            "message" => "course fetched successfully",
            "data" => $course
        ], 200);
    }

    public function update_course(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'course_title' => ['required', 'string', 'max:255'],
            'course_code' => ['required', 'regex:/^(ESUT-)?[A-Z]{3}\s[0-9]{3}$/'],
            'description' => ['nullable'],
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'errors' => $validator->errors()], 422);
        }
        $course = Course::find($request->course_id);
        if (!$course) {
            return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'Not Found!'], 404);
        }
        $course->update([
            'course_title' => $request->course_title,
            'course_code' => $request->course_code,
            'description' => $request->description,
        ]);
        return response()->json([
            'message' => 'course updated successful',
            'data' => $course
        ], 201);
    }

    public function delete_course(Request $request)
    {
        $course = Course::find($request->course_id);
        if (!$course) {
            return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'Not Found!'], 404);
        }

        // Delete the course itself
        $course->delete();

        // Return a success response
        return response()->json(['status' => 200, 'response' => 'Success', 'message' => 'course deleted successfully'], 200);
    }
}
