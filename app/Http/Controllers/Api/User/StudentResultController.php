<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\ResultResource;
use App\Models\Course;
use App\Models\StudentResult;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StudentResultController extends Controller
{
    public function __construct()
    {
        // You can add middleware or other initializations here if needed
        $this->middleware('auth:api');
    }

    public function gradingResults(Request $request)
    {
        try {
            $moodleBaseUrl = "https://moodletest.qverselearning.org";

            // Get all grades from Moodle (without filtering by specific user)
            $grades = DB::connection('mysql2')->select("
            SELECT
                u.id AS student_id,
                u.username,
                u.email,
                c.id AS course_id,
                c.shortname AS course_code,
                c.fullname AS course_name,
                gi.itemname AS activity_name,
                gi.itemmodule AS activity_type,
                gg.finalgrade,
                gi.grademax,
                gi.grademin
            FROM mdl_grade_grades gg
            JOIN mdl_grade_items gi ON gg.itemid = gi.id
            JOIN mdl_user u ON gg.userid = u.id
            JOIN mdl_course c ON gi.courseid = c.id
            WHERE gi.itemtype = 'mod' 
              AND gg.finalgrade IS NOT NULL
              AND c.id != 1  -- Skip frontpage course
            ORDER BY c.id, u.id
        ");

            if (empty($grades)) {
                return response()->json(['status' => 404, 'message' => 'No grades found']);
            }

            // Group grades by course and student
            $groupedGrades = collect($grades)->groupBy(['course_id', 'student_id']);

            // Get all unique course IDs for additional data
            $courseIds = array_unique(array_column($grades, 'course_id'));

            // Get course metadata (credit load, etc.)
            $coursesMetadata = DB::table('courses as c')
                ->join('course_assignments as ca', 'ca.course_id', '=', 'c.id')
                ->whereIn(
                    'c.course_code',
                    array_unique(array_column($grades, 'course_code'))
                )
                ->select([
                    'c.id as course_id',
                    'c.course_code',
                    'ca.credit_load'
                ])
                ->get()
                ->keyBy('course_code');

            // Prepare final response structure
            $result = [];

            foreach ($groupedGrades as $courseId => $students) {
                // Get course context for images and instructors
                $context = DB::connection('mysql2')
                    ->table('mdl_context')
                    ->where('contextlevel', 50) // 50 = course
                    ->where('instanceid', $courseId)
                    ->first();

                // Get course image
                $courseImage = null;
                if ($context) {
                    $file = DB::connection('mysql2')
                        ->table('mdl_files')
                        ->where('component', 'course')
                        ->where('filearea', 'overviewfiles')
                        ->where('contextid', $context->id)
                        ->where('filename', '!=', '.')
                        ->orderByDesc('id')
                        ->first();

                    $courseImage = $file ? $moodleBaseUrl . '/pluginfile.php/' .
                        $file->contextid . '/course/overviewfiles/' .
                        $file->filename : null;
                }

                // Get instructors
                $instructors = collect();
                if ($context) {
                    $instructors = DB::connection('mysql2')
                        ->table('mdl_role_assignments as ra')
                        ->join('mdl_user as u', 'ra.userid', '=', 'u.id')
                        ->join('mdl_role as r', 'ra.roleid', '=', 'r.id')
                        ->where('ra.contextid', $context->id)
                        ->whereIn('r.shortname', ['editingteacher', 'teacher'])
                        ->select('u.id', 'u.firstname', 'u.lastname', 'u.email')
                        ->get();
                }

                // Process each student in this course
                $courseStudents = [];
                foreach ($students as $studentId => $studentGrades) {
                    $firstGrade = $studentGrades->first();

                    // Calculate overall course grade (average of all activities)
                    $totalScore = $studentGrades->sum('finalgrade');
                    $totalMax = $studentGrades->sum('grademax');
                    $courseGrade = $totalMax > 0 ? round(($totalScore / $totalMax) * 100, 2) : 0;

                    // Map to letter grade
                    [$gradeLetter, $qualityPoint] = $this->mapScoreToGrade($courseGrade);

                    // Get credit load from metadata
                    $creditLoad = $coursesMetadata[$firstGrade->course_code]->credit_load ?? 3;
                    $qualityPoints = $qualityPoint * $creditLoad;

                    $courseStudents[] = [
                        'student_id' => $studentId,
                        'student_email' => $firstGrade->email,
                        'student_username' => $firstGrade->username,
                        'final_grade' => $courseGrade,
                        'letter_grade' => $gradeLetter,
                        'quality_points' => $qualityPoints,
                        'credit_load' => $creditLoad,
                        'activities' => $studentGrades->map(function ($grade) {
                            return [
                                'activity_name' => $grade->activity_name,
                                'type' => $grade->activity_type,
                                'grade' => $grade->finalgrade,
                                'max_grade' => $grade->grademax
                            ];
                        })->toArray()
                    ];
                }

                $result[] = [
                    'course_id' => $courseId,
                    'course_code' => $firstGrade->course_code,
                    'course_name' => $firstGrade->course_name,
                    'course_image_url' => $courseImage,
                    'instructors' => $instructors,
                    'students' => $courseStudents
                ];
            }

            return response()->json([
                'status' => 200,
                'data' => $result
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => 500,
                'message' => 'Server error',
                'error' => $th->getMessage()
            ], 500);
        }
    }


    public function courseGradingResults(Request $request, $courseId)
    {
        try {
            $moodleBaseUrl = "https://moodletest.qverselearning.org";

            // Get all grades from Moodle for a specific course
            $grades = DB::connection('mysql2')->select("
            SELECT
                u.id AS student_id,
                u.username,
                u.email,
                c.id AS course_id,
                c.shortname AS course_code,
                c.fullname AS course_name,
                gi.itemname AS activity_name,
                gi.itemmodule AS activity_type,
                gg.finalgrade,
                gi.grademax,
                gi.grademin
            FROM mdl_grade_grades gg
            JOIN mdl_grade_items gi ON gg.itemid = gi.id
            JOIN mdl_user u ON gg.userid = u.id
            JOIN mdl_course c ON gi.courseid = c.id
            WHERE gi.itemtype = 'mod'
              AND gg.finalgrade IS NOT NULL
              AND c.id = ? -- filter by specific course ID
            ORDER BY c.id, u.id
        ", [$courseId]);

            if (empty($grades)) {
                return response()->json(['status' => 404, 'message' => 'No grades found for this course']);
            }

            // Group grades by student (no need to group by course anymore since it's filtered)
            $groupedGrades = collect($grades)->groupBy('student_id');

            $courseCode = $grades[0]->course_code ?? null;

            // Fetch credit load
            $courseMetadata = DB::table('courses as c')
                ->join('course_assignments as ca', 'ca.course_id', '=', 'c.id')
                ->where('c.course_code', $courseCode)
                ->select('c.id as course_id', 'c.course_code', 'ca.credit_load')
                ->first();

            // Get Moodle context for course image and instructors
            $context = DB::connection('mysql2')
                ->table('mdl_context')
                ->where('contextlevel', 50)
                ->where('instanceid', $courseId)
                ->first();

            // Get course image
            $courseImage = null;
            if ($context) {
                $file = DB::connection('mysql2')
                    ->table('mdl_files')
                    ->where('component', 'course')
                    ->where('filearea', 'overviewfiles')
                    ->where('contextid', $context->id)
                    ->where('filename', '!=', '.')
                    ->orderByDesc('id')
                    ->first();

                $courseImage = $file ? $moodleBaseUrl . '/pluginfile.php/' . $file->contextid . '/course/overviewfiles/' . $file->filename : null;
            }

            // Get instructors
            $instructors = collect();
            if ($context) {
                $instructors = DB::connection('mysql2')
                    ->table('mdl_role_assignments as ra')
                    ->join('mdl_user as u', 'ra.userid', '=', 'u.id')
                    ->join('mdl_role as r', 'ra.roleid', '=', 'r.id')
                    ->where('ra.contextid', $context->id)
                    ->whereIn('r.shortname', ['editingteacher', 'teacher'])
                    ->select('u.id', 'u.firstname', 'u.lastname', 'u.email')
                    ->get();
            }

            // Process each student
            $courseStudents = [];
            foreach ($groupedGrades as $studentId => $studentGrades) {
                $firstGrade = $studentGrades->first();

                $totalScore = $studentGrades->sum('finalgrade');
                $totalMax = $studentGrades->sum('grademax');
                $courseGrade = $totalMax > 0 ? round(($totalScore / $totalMax) * 100, 2) : 0;

                [$gradeLetter, $qualityPoint] = $this->mapScoreToGrade($courseGrade);

                $creditLoad = $courseMetadata->credit_load ?? 3;
                $qualityPoints = $qualityPoint * $creditLoad;

                $courseStudents[] = [
                    'student_id' => $studentId,
                    'student_email' => $firstGrade->email,
                    'student_username' => $firstGrade->username,
                    'final_grade' => $courseGrade,
                    'letter_grade' => $gradeLetter,
                    'quality_points' => $qualityPoints,
                    'credit_load' => $creditLoad,
                    'activities' => $studentGrades->map(function ($grade) {
                        return [
                            'activity_name' => $grade->activity_name,
                            'type' => $grade->activity_type,
                            'grade' => $grade->finalgrade,
                            'max_grade' => $grade->grademax
                        ];
                    })->toArray()
                ];
            }

            return response()->json([
                'status' => 200,
                'data' => [
                    'course_id' => $courseId,
                    'course_code' => $grades[0]->course_code,
                    'course_name' => $grades[0]->course_name,
                    'course_image_url' => $courseImage,
                    'instructors' => $instructors,
                    'students' => $courseStudents
                ]
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 500,
                'message' => 'Server error',
                'error' => $th->getMessage()
            ], 500);
        }
    }



    public function processAndStoreCourseGrades(Request $request, $courseId)
    {
        try {
            // First, retrieve the grades from Moodle
            $moodleBaseUrl = "https://moodletest.qverselearning.org";

            $validated = $request->validate([
                'bonus' => 'nullable|numeric',
            ]);

            $bonus = $validated['bonus'] ?? 0;

            // Get all grades from Moodle for the specific course
            $grades = DB::connection('mysql2')->select("
            SELECT
                u.id AS student_id,
                u.username,
                u.email,
                c.id AS course_id,
                c.shortname AS course_code,
                c.fullname AS course_name,
                gi.itemname AS activity_name,
                gi.itemmodule AS activity_type,
                gg.finalgrade,
                gi.grademax,
                gi.grademin
            FROM mdl_grade_grades gg
            JOIN mdl_grade_items gi ON gg.itemid = gi.id
            JOIN mdl_user u ON gg.userid = u.id
            JOIN mdl_course c ON gi.courseid = c.id
            WHERE gi.itemtype = 'mod'
              AND gg.finalgrade IS NOT NULL
              AND c.id = ?
            ORDER BY c.id, u.id
        ", [$courseId]);

            if (empty($grades)) {
                return response()->json(['status' => 404, 'message' => 'No grades found for this course']);
            }

            // Group grades by student
            $groupedGrades = collect($grades)->groupBy('student_id');
            $courseCode = $grades[0]->course_code ?? null;

            // Fetch credit load from local database
            $courseMetadata = DB::table('courses as c')
                ->join('course_assignments as ca', 'ca.course_id', '=', 'c.id')
                ->where('c.course_code', $courseCode)
                ->select('c.id as course_id', 'c.course_code', 'c.course_title', 'ca.credit_load')
                ->first();

            if (!$courseMetadata) {
                return response()->json(['status' => 404, 'message' => 'Course metadata not found']);
            }

            // Process each student and store their results
            $createdResults = [];
            $skippedUsers = [];
            // $currentSession = date('Y'); // Or get from request/settings

            foreach ($groupedGrades as $studentId => $studentGrades) {
                try {



                    $firstGrade = $studentGrades->first();
                    $user = User::find($studentId);
                    if (!$user) {
                        $skippedUsers[] = [
                            'student_id' => $studentId,
                            'reason' => 'User not found'
                        ];
                        continue;
                    }


                    // Initialize activity scores
                    $assignmentScore = 0;
                    $quizScore = 0;
                    $examScore = 0;
                    $assignmentMax = 10;
                    $quizMax = 20;
                    $examMax = 70;

                    foreach ($studentGrades as $activity) {
                        if ($activity->activity_type === 'quiz' && (float) $activity->grademax == 70) {
                            $examScore = (float) $activity->finalgrade;
                            $examMax = (float) $activity->grademax;
                        } elseif ($activity->activity_type === 'assign') {
                            $assignmentScore = (float) $activity->finalgrade;
                            $assignmentMax = (float) $activity->grademax;
                        } elseif ($activity->activity_type === 'quiz') {
                            $quizScore = (float) $activity->finalgrade;
                            $quizMax = (float) $activity->grademax;
                        }
                    }

                    // Apply bonus points logic
                    if ($bonus > 0) {
                        if ($examScore < $examMax) {
                            // Add bonus to exam if it's less than max
                            $examScore = min($examScore + $bonus, $examMax);
                        } else {
                            // Otherwise distribute bonus to quiz and assignment
                            $remainingBonus = $bonus;

                            // First add to quiz
                            $quizNeeded = $quizMax - $quizScore;
                            $quizAdd = min($remainingBonus, $quizNeeded);
                            $quizScore += $quizAdd;
                            $remainingBonus -= $quizAdd;

                            // Then add to assignment if there's remaining bonus
                            if ($remainingBonus > 0) {
                                $assignNeeded = $assignmentMax - $assignmentScore;
                                $assignAdd = min($remainingBonus, $assignNeeded);
                                $assignmentScore += $assignAdd;
                            }
                        }
                    }

                    // // Calculate final grade
                    // $totalScore = $studentGrades->sum('finalgrade');
                    // $totalMax = $studentGrades->sum('grademax');
                    // $courseGrade = $totalMax > 0 ? round(($totalScore / $totalMax) * 100, 2) : 0;

                    // // Map score to letter grade and quality points
                    // [$gradeLetter, $qualityPoint] = $this->mapScoreToGrade($courseGrade);
                    // $creditLoad = $courseMetadata->credit_load ?? 3;
                    // $qualityPoints = $qualityPoint * $creditLoad;


                    // Calculate total scores
                    $totalScore = $assignmentScore + $quizScore + $examScore;
                    $totalMax = $assignmentMax + $quizMax + $examMax;
                    $courseGrade = $totalMax > 0 ? round(($totalScore / $totalMax) * 100, 2) : 0;

                    // Map score to letter grade and quality points
                    [$gradeLetter, $qualityPoint] = $this->mapScoreToGrade($courseGrade);
                    $creditLoad = $courseMetadata->credit_load ?? 3;
                    $qualityPoints = $qualityPoint * $creditLoad;

                    // Prepare data for storage
                    $resultData = [
                        'user_id' => $studentId,
                        'course_id' => $courseId,
                        'course_code' => $courseMetadata->course_code,
                        'course_title' => $courseMetadata->course_title,
                        'credit_load' => $creditLoad,
                        'quality_point' => $qualityPoints,
                        'level' => $user->academic_level,
                        'session' => $user->academic_session,
                        'semester' => $user->academic_semester,
                        'assignment' => $assignmentScore,
                        'quiz' => $quizScore,
                        'exam' => $examScore,
                        'score' => $courseGrade,
                        'grade' => $gradeLetter,
                        // 'remarks' => 'Automatically imported from Moodle',
                        'status' => 'published',
                        'date_of_result' => now(),
                        'bonus_points_applied' => $bonus > 0 ? $bonus : null,
                    ];

                    $result = StudentResult::updateOrCreate(
                        [
                            'user_id' => $studentId,
                            'course_id' => $courseId,
                            'session' => $user->academic_session,
                            'semester' => $user->academic_semester
                        ],
                        $resultData
                    );

                    $createdResults[] = $result;


                    // $existingResult = StudentResult::where('user_id', $studentId)
                    //     ->where('course_id', $courseMetadata->course_id)
                    //     ->where('session', $user->academic_session)
                    //     ->where('semester', $user->academic_semester)
                    //     ->first();

                    // if ($existingResult) {
                    //     // Update existing record
                    //     $existingResult->update($resultData);
                    //     $createdResults[] = $existingResult;
                    // } else {
                    //     // Create new record
                    //     $result = StudentResult::create($resultData);
                    //     $createdResults[] = $result;
                    // }
                } catch (\Throwable $th) {
                    $skippedUsers[] = [
                        'student_id' => $studentId,
                        'reason' => $th->getMessage()
                    ];
                    continue;
                }
            }

            return response()->json([
                'message' => 'Grades processed and stored successfully',
                'stats' => [
                    'total_users_processed' => count($groupedGrades),
                    'results_created_or_updated' => count($createdResults),
                    'users_skipped' => count($skippedUsers),
                ],
                'skipped_users' => $skippedUsers, // This will show details of all skipped users
                'processed_results' => $createdResults
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => 500,
                'message' => 'Server error',
                'error' => $th->getMessage(),
                // 'trace' => $th->getTraceAsString()
            ], 500);
        }
    }

    public function getAllUserResults(Request $request)
    {
        try {
            $user = auth('api')->user();
            if (!$user) {
                return response()->json([
                    'message' => 'Unauthenticated',
                    'error' => 'User is not logged in or token is invalid',
                ], 401);
            }
            $results = StudentResult::where('user_id', $user->id)->with(['course', 'user'])->get();
            return response()->json([
                "message" => "Results fetched successfully",
                "data" => resultResource::collection($results),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error fetching results',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function getUserResult(Request $request)
    {
        $validated = request()->validate([
            "semester" => "required",
            "session" => "required",
        ]);

        try {
            $user = auth('api')->user();
            if (!$user) {
                return response()->json([
                    'message' => 'Unauthenticated',
                    'error' => 'User is not logged in or token is invalid',
                ], 401);
            }
            $results = StudentResult::where('user_id', $user->id)
                ->where('semester', $validated['semester'])
                ->where('session', $validated['session'])
                ->with(['course', 'user'])
                ->get();

            return response()->json([
                "message" => "Results fetched successfully",
                "data" => resultResource::collection($results),
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Error fetching results',
                'error' => $th->getMessage(),
                // 'trace' => $th->getTraceAsString()
            ], 500);
        }
    }



    public function adminViewUserResults(Request $request, $user_id)
    {
        $validated = request()->validate([
            "semester" => "required",
            "session" => "required",
        ]);

        try {
            $user = User::findOrFail($user_id);
            $results = StudentResult::where('user_id', $user->id)
                ->where('semester', $validated['semester'])
                ->where('session', $validated['session'])
                ->with(['course', 'user'])
                ->get();

            return response()->json([
                "message" => "Results fetched successfully",
                "data" => resultResource::collection($results),
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Error fetching results',
                'error' => $th->getMessage(),
                // 'trace' => $th->getTraceAsString()
            ], 500);
        }
    }




    // public function create(Request $request)
    // {

    //     $validatedData = request()->validate([
    //         'course_id' => 'required|exists:courses,id',
    //     ]);

    //     // $validatedData = $request->validate([
    //     //     'user_id' => 'required|exists:users,id',
    //     //     'course_id' => 'required|exists:courses,id',
    //     //     'course_code' => 'nullable|string|max:255',
    //     //     'course_title' => 'nullable|string|max:255',
    //     //     'credit_load' => 'required|integer|min:0',
    //     //     'quality_point' => 'nullable|string|max:255',
    //     //     'session' => 'required|string|max:255',
    //     //     'score' => 'required|numeric|min:0|max:100',
    //     //     'grade' => 'required|string',
    //     //     'remarks' => 'nullable|string|max:255',
    //     //     // 'status' => 'nullable|string|in:published,pending,approved',
    //     //     'status' => 'nullable|string',
    //     //     'date_of_result' => 'nullable|date',
    //     // ]);

    //     try {
    //         $result = StudentResult::create($validatedData);
    //         return response()->json([
    //             'message' => 'Result created successfully',
    //             'data' => $result
    //         ], 201);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'message' => 'Error creating result',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }








    // public function importMoodleGrades()
    // {
    //     $currentSession = request()->validate([
    //         'session' => 'required|string|max:255',
    //     ]);
    //     // Get all grades in a single optimized query
    //     $grades = DB::connection('mysql2')->select("
    //     SELECT
    //         u.id AS moodle_user_id,
    //         u.username,
    //         u.email,
    //         c.id AS course_id,
    //         c.shortname AS course_code,
    //         c.fullname AS course_title,
    //         gi.itemname,
    //         gg.finalgrade,
    //         gi.grademax,
    //         gi.grademin
    //     FROM mdl_grade_grades gg
    //     JOIN mdl_grade_items gi ON gg.itemid = gi.id
    //     JOIN mdl_user u ON gg.userid = u.id
    //     JOIN mdl_course c ON gi.courseid = c.id
    //     WHERE gi.itemtype = 'mod' 
    //       AND gg.finalgrade IS NOT NULL
    //       AND c.id != 1  -- Skip frontpage course
    //     ORDER BY c.id, u.id
    // ");

    //     if (empty($grades)) {
    //         return response()->json(['status' => "fail", 'message' => 'No grades found']);
    //     }

    //     // Pre-fetch all needed users and courses in single queries
    //     $emails = array_unique(array_column($grades, 'email'));
    //     $users = User::whereIn('email', $emails)->get()->keyBy('email');

    //     $courseIds = array_unique(array_column($grades, 'course_id'));
    //     $courses = Course::whereIn('id', $courseIds)->get()->keyBy('id');

    //     // $currentSession = $this->determineCurrentSession();
    //     // $currentSession = '2024/2025'; // Replace with your session logic

    //     $importDate = Carbon::now()->format('Y-m-d');
    //     $batchId = uniqid(); // For tracking this import batch

    //     $processed = 0;
    //     $skipped = 0;

    //     foreach ($grades as $grade) {
    //         if (!isset($users[$grade->email]) || !isset($courses[$grade->course_id])) {
    //             $skipped++;
    //             continue;
    //         }

    //         $user = $users[$grade->email];
    //         $course = $courses[$grade->course_id];

    //         $score = $this->calculateNormalizedScore($grade->finalgrade, $grade->grademax);
    //         [$gradeLetter, $qualityPoint] = $this->mapScoreToGrade($score);

    //         // Calculate quality points based on course credit load
    //         $qualityPoints = $qualityPoint * ($course->credit_load ?? 3);

    //         try {
    //             StudentResult::updateOrCreate(
    //                 [
    //                     'user_id' => $user->id,
    //                     'course_id' => $course->id,
    //                     'session' => $currentSession,
    //                 ],
    //                 [
    //                     'course_code' => $course->code ?? $grade->course_code,
    //                     'course_title' => $course->title ?? $grade->course_title,
    //                     'credit_load' => $course->credit_load ?? 3,
    //                     'quality_point' => $qualityPoints,
    //                     'score' => $score,
    //                     'grade' => $gradeLetter,
    //                     'remarks' => $this->getRemarks($score),
    //                     'status' => 'published',
    //                     'date_of_result' => $importDate,
    //                     'import_batch' => $batchId,
    //                     'updated_at' => now(),
    //                 ]
    //             );
    //             $processed++;
    //         } catch (\Exception $e) {
    //             $skipped++;
    //             // Log error if needed
    //         }
    //     }

    //     return response()->json([
    //         'success' => true,
    //         'message' => "Grade import completed",
    //         'stats' => [
    //             'processed' => $processed,
    //             'skipped' => $skipped,
    //             'batch_id' => $batchId,
    //         ]
    //     ]);
    // }



    // public function importMoodleGrades()
    // {
    //     $moodleBaseUrl = "https://moodletest.qverselearning.org";

    //     $grades = DB::connection('mysql2')->select("
    //     SELECT
    //         u.id AS moodle_user_id,
    //         u.username,
    //         u.email,
    //         c.id AS course_id,
    //         c.shortname AS course_code,
    //         c.fullname AS course_title,
    //         gi.itemname,
    //         gg.finalgrade,
    //         gi.grademax,
    //         gi.grademin
    //     FROM mdl_grade_grades gg
    //     JOIN mdl_grade_items gi ON gg.itemid = gi.id
    //     JOIN mdl_user u ON gg.userid = u.id
    //     JOIN mdl_course c ON gi.courseid = c.id
    //     WHERE gi.itemtype = 'mod' 
    //       AND gg.finalgrade IS NOT NULL
    //       AND c.id != 1  -- Skip frontpage course
    //     ORDER BY c.id, u.id
    // ");

    //     if (empty($grades)) {
    //         return response()->json(['status' => "fail", 'message' => 'No grades found']);
    //     }

    //     // Pre-fetch all needed users and courses in single queries
    //     $emails = array_unique(array_column($grades, 'email'));
    //     $users = User::whereIn('email', $emails)->get()->keyBy('email');

    //     $courseIds = array_unique(array_column($grades, 'course_id'));
    //     $courses = Course::whereIn('id', $courseIds)->get()->keyBy('id');

    //     // $currentSession = $this->determineCurrentSession();
    //     // $currentSession = '2024/2025'; // Replace with your session logic

    //     $importDate = Carbon::now()->format('Y-m-d');
    //     $batchId = uniqid(); // For tracking this import batch

    //     $processed = 0;
    //     $skipped = 0;

    //     foreach ($grades as $grade) {
    //         if (!isset($users[$grade->email]) || !isset($courses[$grade->course_id])) {
    //             $skipped++;
    //             continue;
    //         }

    //         $user = $users[$grade->email];
    //         $course = $courses[$grade->course_id];

    //         $score = $this->calculateNormalizedScore($grade->finalgrade, $grade->grademax);
    //         [$gradeLetter, $qualityPoint] = $this->mapScoreToGrade($score);

    //         // Calculate quality points based on course credit load
    //         $qualityPoints = $qualityPoint * ($course->credit_load ?? 3);

    //         try {


    //             $processed++;
    //         } catch (\Exception $e) {
    //             $skipped++;
    //             // Log error if needed
    //         }
    //     }

    //     return response()->json([
    //         'success' => true,
    //         'message' => "Grade import completed",
    //         'stats' => [
    //             'processed' => $processed,
    //             'skipped' => $skipped,
    //             'batch_id' => $batchId,
    //         ]
    //     ]);
    // }

    // private function calculateNormalizedScore($finalgrade, $grademax): float
    // {
    //     if ($grademax <= 0)
    //         return 0;
    //     return round(($finalgrade / $grademax) * 100, 2);
    // }

    private function mapScoreToGrade($score): array
    {
        if ($score >= 70)
            return ['A', 5];
        if ($score >= 60)
            return ['B', 4];
        if ($score >= 50)
            return ['C', 3];
        if ($score >= 45)
            return ['D', 2];
        if ($score >= 40)
            return ['E', 1];
        return ['F', 0];
    }

    private function getRemarks($score): string
    {
        if ($score >= 70)
            return 'Excellent';
        if ($score >= 60)
            return 'Very Good';
        if ($score >= 50)
            return 'Good';
        if ($score >= 45)
            return 'Pass';
        return 'Fail';
    }

    // private function determineCurrentSession(): string
    // {
    //     // Implement your session determination logic
    //     $month = date('n');
    //     $year = date('Y');

    //     // Example: Academic year runs from August to July
    //     if ($month >= 8) {
    //         return $year . '/' . ($year + 1);
    //     } else {
    //         return ($year - 1) . '/' . $year;
    //     }
    // }



}
