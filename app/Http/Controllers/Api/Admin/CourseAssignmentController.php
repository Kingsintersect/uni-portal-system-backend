<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CourseAssignment;
use App\Models\CourseCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class CourseAssignmentController extends Controller
{

    // Assign a collection of courses to a course_category
    public function assign(Request $request)
    {
        // Validate the input data
        $validator = Validator::make($request->all(), [
            'course_category_id' => 'required|integer',
            'assignments' => 'required|array',
            'assignments.*.course_id' => 'required|integer',
            'assignments.*.credit_load' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $course_category_id = $request->course_category_id;
        $assignments = $request->assignments;

        // Get all course_ids already assigned to this category
        $existingAssignments = CourseAssignment::where('course_category_id', $course_category_id)
            ->whereIn('course_id', array_column($assignments, 'course_id'))
            ->get();

        $existingCourseIds = $existingAssignments->pluck('course_id')->toArray();

        // Filter out assignments that are already assigned
        $newAssignments = array_filter($assignments, function ($assignment) use ($existingCourseIds) {
            return !in_array($assignment['course_id'], $existingCourseIds);
        });

        // Update the credit_load for already assigned courses
        foreach ($existingAssignments as $existingAssignment) {
            foreach ($assignments as $assignment) {
                if ($assignment['course_id'] === $existingAssignment->course_id) {
                    $existingAssignment->credit_load = $assignment['credit_load'];
                    $existingAssignment->save();
                    break;
                }
            }
        }

        // Prepare data for bulk insert
        $data = [];
        foreach ($newAssignments as $assignment) {
            $data[] = [
                'course_id' => $assignment['course_id'],
                'course_category_id' => $course_category_id,
                'credit_load' => $assignment['credit_load'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        try {
            // Insert all new records at once within a transaction
            DB::transaction(function () use ($data) {
                if (!empty($data)) {
                    CourseAssignment::insert($data);
                }
            });

            return response()->json([
                'status' => true,
                'message' => 'New course assignments created and existing assignments updated successfully',
                'new_assignments' => $data,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Some course assignments could not be created or updated',
                'error' => $e->getMessage()
            ], 500);
        }
    }



    // Get all course Assignments
    public function allCourseAssignments()
    {
        // Step 1: Get unique course_category_id from course_assignments
        $uniqueCategoryIds = DB::table('course_assignments')
            ->select('course_category_id')
            ->distinct()
            ->pluck('course_category_id');
    
        // Step 2: Retrieve id and short_code from course_category based on unique IDs
        $categories = CourseCategory::whereIn('id', $uniqueCategoryIds)
            ->select('id', 'short_code')
            ->get();
    
        return response()->json([
            'status' => true,
            'data' => $categories
        ], 200);
    }

    // Get all courses under a course category
    public function getCoursesByCategory(Request $request)
    {
        $validator = Validator($request->all(), [
            'course_category_id' => ['required', 'integer']
        ]);
        if($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()
            ], 422);
        }
        
        $course_category = CourseCategory::with(['courses' => function ($query) {
            $query->select('courses.id', 'courses.course_title', 'courses.course_code');
        }])->find($request->course_category_id);
        
        if (!$course_category) {
            return response()->json([
                'status' => false,
                'message' => 'Course Category does not exist'
            ], 422);
        }
        
        $courses = $course_category->courses->map(function ($course) {
            return [
                'id' => $course->id,
                'course_title' => $course->course_title,
                'course_code' => $course->course_code,
                'credit_load' => $course->pivot->credit_load, // Access credit_load from pivot
            ];
        });
        
        return response()->json([
            'status' => true,
            'short_code' => $course_category->short_code,
            'data' => $courses
        ], 200);
    }

    // View all Course Assignment
   

    // Delete a course assignment
    public function delete(Request $request)
    {
        // Validate the input data
        $validator = Validator::make($request->all(), [
            'course_category_id' => 'required|integer',
            'assignments' => 'required|array',
            'assignments.*.course_id' => 'required|integer',
            'assignments.*.credit_load' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $course_category_id = $request->course_category_id;
        $assignments = $request->assignments;

        // Get all course_ids from the assignments provided in the request
        $submittedCourseIds = array_column($assignments, 'course_id');

        // Get the existing assignments for the course category
        $existingCourseIds = CourseAssignment::where('course_category_id', $course_category_id)
            ->pluck('course_id')
            ->toArray();

        // Check if all existing course_ids match the submitted course_ids
        if (empty(array_diff($existingCourseIds, $submittedCourseIds)) && empty(array_diff($submittedCourseIds, $existingCourseIds))) {
            // No changes needed, all assignments are already correct
            return response()->json([
                'status' => true,
                'message' => 'No changes needed, all courses are already assigned to this category.',
            ], 200);
        }

        // Identify assignments to delete: those in DB but not in submitted assignments
        $assignmentsToDelete = CourseAssignment::where('course_category_id', $course_category_id)
            ->whereNotIn('course_id', $submittedCourseIds)
            ->delete();

        return response()->json([
            'status' => true,
            'message' => 'Unsubmitted course assignments deleted successfully.',
            'deleted_assignments' => $assignmentsToDelete,
        ], 200);
    }


}
