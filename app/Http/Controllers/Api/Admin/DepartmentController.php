<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Faculty;
use App\Models\Department;

class DepartmentController extends Controller
{
    public function __construct()
    {
        // $this->middleware('auth:admin')->except(['all_departments','department', 'departmentInFaculty']);
        $this->middleware('auth:api')->except(['all_departments', 'department', 'departmentInFaculty']);
    }

    public function create_department(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'department_name' => ['required'],
            'faculty_id' => ['required', 'exists:faculties,id']
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'errors' => $validator->errors()], 422);
        }

        try {
            // Create a new department record
            $department = new Department();
            $department->faculty_id = $request->input('faculty_id');
            $department->department_name = $request->input('department_name');
            $department->save();

            // Return a success response
            return response()->json([
                'status' => 201,
                'response' => 'department created successfully',
                'data' => $department
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


    public function all_departments(Request $request)
    {
        // Validate faculty_id if provided
        $validator = Validator::make($request->all(), [
            'faculty_id' => 'nullable|exists:faculties,id', // Check if faculty_id exists in the faculties table
        ]);

        // Return validation errors if any
        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'response' => 'Validation Error',
                'message' => $validator->errors()
            ], 422);
        }

        // Fetch departments based on faculty_id if provided, otherwise fetch all departments
        $departments = $request->faculty_id
            ? Department::where('faculty_id', $request->faculty_id)->get()
            : Department::all();

        // Check if the collection is empty
        /*if ($departments->isEmpty()) {
            return response()->json([
                'status' => 404,
                'response' => 'Not Found',
                'message' => 'No department(s) found'
            ], 404);
        }*/

        // Return a successful response with the data
        return response()->json([
            'status' => 200,
            'response' => 'Successful',
            "message" => "All department(ies) fetched successfully",
            "data" => $departments
        ], 200);
    }


    // Get all departments in a Faculty
    public function departmentInFaculty(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'faculty_id' => ['required', 'integer']
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Find faculty with only 'id' and 'department_name' fields from departments
        $selected_faculty = Faculty::with(['departments' => function ($query) {
            $query->select('id', 'department_name', 'faculty_id');
        }])->find($request->faculty_id);

        if (!$selected_faculty) {
            return response()->json([
                'status' => false,
                'error' => 'Faculty not found or no departments available for the selected faculty.'
            ], 404);
        }

        // Hide 'faculty_id' from each department in the response
        $selected_faculty->departments->makeHidden('faculty_id');

        return response()->json([
            'status' => true,
            'faculty_name' => $selected_faculty->faculty_name,
            'data' => $selected_faculty->departments
        ], 200);
    }


    public function department(Request $request)
    {
        $department = Department::find($request->department_id);

        // Check if the collection is empty
        if (!$department) {
            return response()->json([
                'status' => 404,
                'response' => 'Not Found',
                'message' => 'department not found'
            ], 404);
        }

        // Return a successful response with the data
        return response()->json([
            'status' => 200,
            'response' => 'Successful',
            "message" => "department fetched successfully",
            "data" => $department
        ], 200);
    }

    public function update_department(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'department_name' => ['string', 'max:255'],
            'status' => ['boolean']
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'errors' => $validator->errors()], 422);
        }
        $department = Department::find($request->department_id);
        if (!$department) {
            return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'Not Found!'], 404);
        }
        $department->department_name = $request->department_name ?? $department->department_name;
        $department->status = $request->status ?? $department->status;
        $department->save();

        return response()->json([
            'message' => 'Department updated successful',
            'data' => $department
        ], 200);
    }

    public function delete_department(Request $request)
    {
        $department = Department::find($request->department_id);
        if (!$department) {
            return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'Not Found!'], 404);
        }

        // Delete the department itself
        $department->delete();

        // Return a success response
        return response()->json(['status' => 200, 'response' => 'Success', 'message' => 'department and associated departments deleted successfully'], 200);
    }
}
