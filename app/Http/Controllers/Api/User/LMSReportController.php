<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\CourseCategory;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use stdClass;

class LMSReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.any:api,admin')
            ->except(['getMoodleCategories', 'getMoodleCategoriesAndCourses', 'getMoodleCohortAndCategories', 'getMoodleCohortAndCategoriesAndCourses']);
        // cohorts and categories, cohorts categories and courses
    }
    public function grading(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'student_email' => ['required', 'email'],
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'errors' => $validator->errors()], 422);
        }


        try {
            $moodleBaseUrl = "https://moodletest.qverselearning.org";
            $courses = DB::connection('mysql2')
                ->table('mdl_user_enrolments as ue')
                ->join('mdl_enrol as e', 'ue.enrolid', '=', 'e.id')
                ->join('mdl_course as c', 'e.courseid', '=', 'c.id')
                ->join('mdl_user as u', 'ue.userid', '=', 'u.id')
                ->where('u.email', $request->student_email)
                ->select([
                    'c.id as course_id',
                    'c.fullname as course_name',
                    'c.shortname as course_code',
                    'u.id as student_id'
                ])
                ->get();

            if ($courses->isNotEmpty()) {
                $courseCodesPack = $courses->pluck('course_code')->toArray();
                $course_category = DB::table('courses as c')
                    ->join('course_assignments as ca', 'ca.course_id', '=', 'c.id')
                    ->whereIn('course_code', $courseCodesPack)
                    ->select([
                        'c.id as course_id',
                        'c.course_code',
                        'ca.credit_load'
                    ])
                    ->get();

                $refurbHolder = [];
                if ($course_category->isNotEmpty()) {
                    foreach ($course_category as $cat) {
                        $refurbHolder[$cat->course_code] = $cat->credit_load;
                    }
                }

                foreach ($courses as $course) {
                    if (array_key_exists($course->course_code, $refurbHolder)) {
                        $course->credit_load = $refurbHolder[$course->course_code];
                    }
                    // // Get course images and instructors
                    // $courseImage = DB::connection('mysql2')
                    //     ->table('mdl_files')
                    //     ->where('component', 'course')
                    //     ->where('filearea', 'overviewfiles')
                    //     ->where('itemid', $course->course_id)
                    //     ->where('filename', '!=', '.')
                    //     ->orderByDesc('id')
                    //     ->first();

                    // if ($courseImage) {
                    //     $imageUrl = $moodleBaseUrl . '/pluginfile.php/' .
                    //         $courseImage->contextid . '/course/overviewfiles/' .
                    //         $courseImage->filename;
                    //     $course->course_image_url = $imageUrl;
                    // } else {
                    //     $course->course_image_url = null;
                    // }

                    $context = DB::connection('mysql2')
                        ->table('mdl_context')
                        ->where('contextlevel', 50) // 50 = course
                        ->where('instanceid', $course->course_id)
                        ->first();

                    if ($context) {
                        $file = DB::connection('mysql2')
                            ->table('mdl_files')
                            ->where('component', 'course')
                            ->where('filearea', 'overviewfiles')
                            ->where('contextid', $context->id)
                            ->where('filename', '!=', '.')
                            ->orderByDesc('id')
                            ->first();
                        $course->course_image_url = ($file) ?   (env('LMS_WS_SERVER', $moodleBaseUrl)) . '/pluginfile.php/' .
                            $file->contextid . '/course/overviewfiles/' .
                            $file->filename : null;

                        $teachers = DB::connection('mysql2')
                            ->table('mdl_role_assignments as ra')
                            ->join('mdl_user as u', 'ra.userid', '=', 'u.id')
                            ->join('mdl_role as r', 'ra.roleid', '=', 'r.id')
                            ->where('ra.contextid', $context->id)
                            ->whereIn('r.shortname', ['editingteacher', 'teacher']) // both kinds of teachers
                            ->select('u.id', 'u.firstname', 'u.lastname', 'u.email')
                            ->get();

                        $course->instructors = $teachers->isNotEmpty() ? $teachers : $course->instructors = collect();
                    } else {
                        $course->course_image_url = null;
                        $course->instructors = $course->instructors = collect();
                    }



                    $grades = DB::connection('mysql2')
                        ->table('mdl_grade_grades as g')
                        ->join('mdl_user as u', 'g.userid', '=', 'u.id')
                        ->join('mdl_grade_items as gi', 'g.itemid', '=', 'gi.id')
                        ->join('mdl_course as c', 'gi.courseid', '=', 'c.id')
                        ->where('gi.itemtype', 'course')
                        ->where('c.id', $course->course_id)
                        ->where('u.id', $course->student_id)
                        ->select([
                            'c.id as course_id',
                            'g.finalgrade'
                        ])
                        ->get();

                    $grades2 = DB::connection('mysql2')
                        ->table('mdl_grade_grades as g')
                        ->join('mdl_user as u', 'g.userid', '=', 'u.id')
                        ->join('mdl_grade_items as gi', 'g.itemid', '=', 'gi.id')
                        ->join('mdl_course as c', 'gi.courseid', '=', 'c.id')
                        ->where('gi.itemtype', 'mod')
                        ->whereIn('gi.itemmodule', ['quiz', 'assign'])
                        ->where('c.id', $course->course_id)
                        ->where('u.id', $course->student_id)
                        ->select([
                            'gi.itemname as activity_name',
                            'gi.itemmodule as type',
                            'g.finalgrade as grade'
                        ])
                        ->get();

                    $course->finalgrade = $grades->isNotEmpty() ? $grades[0]->finalgrade : null;
                    $course->activities = $grades2;
                }
            }


            // Goal	What to use
            // All activity grades	->where('gi.itemtype', 'mod')
            // Only assignments	->where('gi.itemmodule', 'assign')
            // Only quizzes/exams	->where('gi.itemmodule', 'quiz')
            // Only forum grades	->where('gi.itemmodule', 'forum')
            // Only course total	->where('gi.itemtype', 'course')

            return response()->json([
                'status' => 200,
                'data' => $courses
            ], 200);
        } catch (\Throwable $th) {
            return response()->json(['status' => 404, 'response' => '501 Error', 'message' => 'Something went wrong. ' . $th->getMessage()], 404);
        }
    }

    public function studentCourses(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'student_email' => ['required', 'email'],
            // 'course_id' => ['required', 'numeric'],
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'errors' => $validator->errors()], 422);
        }


        try {
            // 1. Get all courses
            $courses = DB::connection('mysql2')
                ->table('mdl_user_enrolments as ue')
                ->join('mdl_enrol as e', 'ue.enrolid', '=', 'e.id')
                ->join('mdl_course as c', 'e.courseid', '=', 'c.id')
                ->join('mdl_user as u', 'ue.userid', '=', 'u.id')
                ->where('u.email', $request->student_email)
                ->select([
                    'c.id as course_id',
                    'c.fullname as course_name',
                    'c.shortname as course_code'
                ])
                ->get();
            return response()->json(['status' => 200, 'data' => $courses], 200);
        } catch (\Throwable $th) {
            return response()->json(['status' => 404, 'response' => '501 Error', 'message' => 'Something went wrong. ' . $th->getMessage()], 404);
        }
    }


    public function getMoodleUserId(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'string']
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'errors' => $validator->errors()], 422);
        }

        $field = filter_var($request->email, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $user = DB::connection('mysql2')
            ->table('mdl_user')
            ->where($field, $request->email)
            ->first();

        if ($user) {
            return response()->json([
                'user' => [
                    'id' => $user->id,
                    'firstname' => $user->firstname,
                    'lastname' => $user->lastname,
                    'email' => $user->email,
                    'username' => $user->username,
                ]
            ]);
        } else {
            return response()->json(['error' => 'User not found'], 404);
        }
    }


    public function getMoodleCategories(Request $request)
    {
        $searchString = $request->input('search', '');         // ?search=math
        $limit        = $request->input('limit', 50);      // ?limit=20 (default 10)
        $page         = $request->input('page', 1);        // ?page=2 (default 1)

        $dbPrefix = env('LMS_DB_PREFIX', 'mdl_');

        $categories = DB::connection('mysql2')->table($dbPrefix . 'course_categories')
            // ->select('id', 'name', 'description', 'parent', 'sortorder', 'visible')
            ->select('id', 'name', 'parent', 'sortorder')
            ->when($searchString, function ($query, $searchString) {
                return $query->where('name', 'like', "%{$searchString}%");
            })
            ->orderBy('sortorder')
            ->paginate($limit, ['*'], 'page', $page);

        if ($categories) {
            return response()->json($categories);
        } else {
            return response()->json(['error' => 'Empty'], 404);
        }
    }

    public function getMoodleCategoriesAndCourses()
    {
        $dbPrefix = env('LMS_DB_PREFIX', 'mdl_');
        $results = DB::connection('mysql2')->select("
            SELECT
                c.id AS course_id,
                c.fullname AS course_name,
                cc.id AS category_id,
                cc.name AS category_name
            FROM pgs_course c
            JOIN pgs_course_categories cc ON c.category = cc.id
        ");

        if ($results) {
            return response()->json(
                $results
            );
        } else {
            return response()->json(['error' => 'Empty'], 404);
        }
    }


    public function getMoodleCohortAndCategories()
    {
        $dbPrefix = env('LMS_DB_PREFIX', 'mdl_');
        $results = DB::connection('mysql2')->select("
            SELECT
                ch.id AS cohort_id,
                ch.name AS cohort_name,
                ctx.instanceid AS category_id,
                cc.name AS category_name
            FROM {$dbPrefix}cohort ch
            JOIN {$dbPrefix}context ctx ON ch.contextid = ctx.id
            JOIN {$dbPrefix}course_categories cc ON ctx.instanceid = cc.id
            WHERE ctx.contextlevel = 40;
        ");

        if ($results) {
            return response()->json(
                $results
            );
        } else {
            return response()->json(['error' => 'Empty'], 404);
        }
    }

    public function getMoodleCohortAndCategoriesAndCourses()
    {
        $dbPrefix = env('LMS_DB_PREFIX', 'mdl_');
        $results = DB::connection('mysql2')->select("
            SELECT
                ch.id AS cohort_id,
                ch.name AS cohort_name,
                cc.id AS category_id,
                cc.name AS category_name,
                c.id AS course_id,
                c.fullname AS course_name
            FROM {$dbPrefix}cohort ch
            JOIN {$dbPrefix}context ctx            ON ch.contextid = ctx.id
            JOIN {$dbPrefix}course_categories cc   ON ctx.instanceid = cc.id AND ctx.contextlevel = 40
            LEFT JOIN {$dbPrefix}course c          ON c.category = cc.id
            ORDER BY cc.id, ch.id, c.id
        ");

        if ($results) {
            return response()->json(
                $results
            );
        } else {
            return response()->json(['error' => 'Empty'], 404);
        }
    }


    public function getMoodleUsersInfo()
    {
        $dbPrefix = env('LMS_DB_PREFIX', 'mdl_');
        $results = DB::connection('mysql2')->select("
            SELECT user_id, firstname, lastname, email, department, role_shortname, role_name
                FROM (
                    SELECT 
                        u.id AS user_id,
                        u.firstname,
                        u.lastname,
                        u.email,
                        u.department,
                        r.shortname AS role_shortname,
                        r.name AS role_name,
                        ROW_NUMBER() OVER (PARTITION BY u.id ORDER BY r.id) AS rn
                    FROM {$dbPrefix}user u
                    JOIN {$dbPrefix}role_assignments ra ON ra.userid = u.id
                    JOIN {$dbPrefix}role r              ON r.id = ra.roleid
                    JOIN {$dbPrefix}context ctx         ON ctx.id = ra.contextid
                    WHERE u.deleted = 0
                ) t
                WHERE t.rn = 1
                ORDER BY user_id
        ");

        if ($results) {
            return response()->json(
                $results
            );
        } else {
            return response()->json(['error' => 'Empty'], 404);
        }
    }

    public function getMoodleSingleUsersInfo(Request $request)
    {
        $email = $request->input('email');
        if (empty($email)) {
            return response()->json(['error' => 'Provide Email'], 422);
        }

        $dbPrefix = env('LMS_DB_PREFIX', 'mdl_');
        $results = DB::connection('mysql2')->select("
            SELECT user_id, firstname, lastname, email, department, role_shortname, role_name
                FROM (
                    SELECT 
                        u.id AS user_id,
                        u.firstname,
                        u.lastname,
                        u.email,
                        u.department,
                        r.shortname AS role_shortname,
                        r.name AS role_name,
                        ROW_NUMBER() OVER (PARTITION BY u.id ORDER BY r.id) AS rn
                    FROM {$dbPrefix}user u
                    JOIN {$dbPrefix}role_assignments ra ON ra.userid = u.id
                    JOIN {$dbPrefix}role r              ON r.id = ra.roleid
                    JOIN {$dbPrefix}context ctx         ON ctx.id = ra.contextid
                    WHERE u.deleted = 0 and email='{$email}'
                ) t
                WHERE t.rn = 1
                ORDER BY user_id
        ");

        if ($results) {
            return response()->json(
                $results
            );
        } else {
            return response()->json(['error' => 'Empty'], 404);
        }
    }

    public function getMoodleUsersInfo2()
    {
        $dbPrefix = env('LMS_DB_PREFIX', 'mdl_');
        $results = DB::connection('mysql2')->select("
            SELECT user_id, firstname, lastname, email, department, role_shortname, role_name
                FROM (
                    SELECT 
                        u.id AS user_id,
                        u.firstname,
                        u.lastname,
                        u.email,
                        u.department,
                        r.shortname AS role_shortname,
                        r.name AS role_name,
                        ROW_NUMBER() OVER (PARTITION BY u.id ORDER BY r.id) AS rn
                    FROM {$dbPrefix}user u
                    JOIN {$dbPrefix}role_assignments ra ON ra.userid = u.id
                    JOIN {$dbPrefix}role r              ON r.id = ra.roleid
                    JOIN {$dbPrefix}context ctx         ON ctx.id = ra.contextid
                    WHERE u.deleted = 0
                ) t
                WHERE t.rn = 1
                ORDER BY user_id
        ");

        if ($results) {
            return response()->json(
                $results
            );
        } else {
            return response()->json(['error' => 'Empty'], 404);
        }
    }




    public function getMoodleCourses(Request $request)
    {
        $search = $request->input('search');
        $limit  = $request->input('limit', 50);
        $page   = $request->input('page', 1);
        $dbPrefix = env('LMS_DB_PREFIX', 'mdl_');

        $courses = DB::connection('mysql2')->table($dbPrefix . 'course')
            ->select('id', 'fullname', 'shortname', 'category', 'visible', 'startdate', 'summary')
            ->when($search, function ($query, $search) {
                $query->where('fullname', 'like', "%{$search}%")
                    ->orWhere('shortname', 'like', "%{$search}%");
            })
            ->orderBy('startdate', 'desc')
            ->paginate($limit, ['*'], 'page', $page);

        return response()->json($courses);
    }



    public static function moodle_hash(string $password): string
    {
        // Generate a random salt (Moodle uses 20-24 chars)
        $salt = substr(md5(uniqid(rand(), true)), 0, 8);

        // Default Moodle hashing (salt + password)
        $hashed = md5($salt . $password);

        // Return salt + hashed password
        return $salt . $hashed;
    }


    public static function moodle_verify(string $password, string $moodleHash): bool
    {
        if (strlen($moodleHash) < 8) {
            return false;
        }

        $salt = substr($moodleHash, 0, 8);
        $expectedHash = $salt . md5($salt . $password);

        return hash_equals($moodleHash, $expectedHash);
    }
}
