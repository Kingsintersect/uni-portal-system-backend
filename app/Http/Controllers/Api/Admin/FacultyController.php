<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Faculty;
use App\Models\Department;

class FacultyController extends Controller
{
    public function __construct()
    {
        // $this->middleware('auth:admin')->except(['all_faculties','faculty', 'active_faculties', 'all_departments_in_an_active_faculty']);
        $this->middleware('auth:api')->except(['all_faculties', 'faculty', 'active_faculties', 'all_departments_in_an_active_faculty']);
    }

    public function create_faculty(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'faculty_name' => ['required'],
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'errors' => $validator->errors()], 422);
        }

        try {
            // Create a new Faculty record
            $faculty = new Faculty();
            $faculty->faculty_name = $request->input('faculty_name');
            $faculty->save();

            // Return a success response
            return response()->json([
                'status' => 201,
                'response' => 'Faculty created successfully',
                'data' => $faculty
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


    public function all_faculties(Request $request)
    {
        $faculties = Faculty::get();

        // Check if the collection is empty
        if ($faculties->isEmpty()) {
            return response()->json([
                'status' => 404,
                'response' => 'Not Found',
                'message' => 'No Faculty(s) found'
            ], 404);
        }

        // Return a successful response with the data
        return response()->json([
            'status' => 200,
            'response' => 'Successful',
            "message" => "All Faculty(ies) fetched successfully",
            "data" => $faculties
        ], 200);
    }


    public function active_faculties(Request $request)
    {
        $faculties = Faculty::where('status', true)->get();

        // Check if the collection is empty
        if ($faculties->isEmpty()) {
            return response()->json([
                'status' => false,
                'response' => 'Not Found',
                'message' => 'No Active Faculty(s) found'
            ], 404);
        }

        // Return a successful response with the data
        return response()->json([
            'status' => true,
            'response' => 'Successful',
            'message' => "All Active Faculty(ies) fetched successfully",
            'data' => $faculties
        ], 200);
    }


    // Get all departments in an active faculty
    public function all_departments_in_an_active_faculty(Request $request)
    {
        $faculties = Faculty::where('status', true)
            ->with(['departments' => function ($query) {
                $query->select('id', 'faculty_id', 'department_name', 'status')
                    ->where('status', true); // Apply status filter to departments
            }])
            ->get();


        if ($faculties->isEmpty()) {
            return response()->json([
                'status' => false,
                'response' => 'Not Found',
                'message' => 'No Active Faculty(s) found'
            ], 404);
        }

        // Hide some fields
        $faculties->makeHidden(['created_at', 'updated_at']);
        // Return a successful response with the data
        return response()->json([
            'status' => true,
            'data' => $faculties
        ], 200);
    }


    public function faculty(Request $request)
    {
        $faculty = Faculty::find($request->faculty_id);

        // Check if the collection is empty
        if (!$faculty) {
            return response()->json([
                'status' => 404,
                'response' => 'Not Found',
                'message' => 'Faculty not found'
            ], 404);
        }

        // Return a successful response with the data
        return response()->json([
            'status' => 200,
            'response' => 'Successful',
            "message" => "Faculty fetched successfully",
            "data" => $faculty
        ], 200);
    }

    public function update_faculty(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'faculty_name' => ['string', 'max:255'],
            'status' => ['boolean']
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'errors' => $validator->errors()], 422);
        }
        $faculty = Faculty::find($request->faculty_id);
        if (!$faculty) {
            return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'Not Found!'], 404);
        }
        $faculty->update([
            'faculty_name' => $request->faculty_name ?? $faculty->faculty_name,
            'status' => $request->status ?? $faculty->status
        ]);

        return response()->json([
            'message' => 'Faculty updated successful',
            'data' => $faculty
        ], 201);
    }

    public function delete_faculty(Request $request)
    {
        $faculty = Faculty::find($request->faculty_id);
        if (!$faculty) {
            return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'Not Found!'], 404);
        }

        // Delete the LGA records where the faculty_id matches the faculty's ID
        Department::where('faculty_id', $faculty->id)->delete();

        // Delete the faculty itself
        $faculty->delete();

        // Return a success response
        return response()->json(['status' => 200, 'response' => 'Success', 'message' => 'faculty and associated departments deleted successfully'], 200);
    }
}
