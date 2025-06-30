<?php

namespace App\Http\Controllers\Api\Admin;

use Carbon\Carbon;
use App\Models\Admin;
use App\Models\User;
use App\Models\CourseCategory;
use App\Models\Faculty;
use App\Models\Department;
use App\Mail\AdmissionMail;
use App\Services\PDFService;
use Illuminate\Http\Request;
use App\Models\ApplicationPayment;
use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;


class AdminController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:user')->except(['register', 'login', 'sendPDF', 'academicSessionUpdate']);
    }
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:admins'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'errors' => $validator->errors()], 422);
        }
        $admin = Admin::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => bcrypt($request->password)
        ]);
        return response()->json([
            'message' => 'Registration successful',
            'user' => $admin
        ], 201);
    }


    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        if (!$token = Auth::guard('user')->attempt($credentials)) {
            return response()->json(['status' => 403, 'response' => 'Unauthorized', 'message' => 'Unauthorized User'], 403);
        }
        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json(['status' => 403, 'response' => 'Not Found', 'message' => 'User Not Found'], 403);
        }

        return $this->createToken($token);
    }

    public function createToken($token)
    {
        $user = User::find(auth('user')->user()->id);
        return response()->json([
            'status' => 200,
            'response' => 'Successful',
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('admin')->factory()->getTTL() * 60,
            'user' => $user
        ]);
    }

    public function allApplication()
    {
        // Fetch all payments with their associated application forms
        $applications = User::get();

        // Check if the collection is empty
        if ($applications->isEmpty()) {
            return response()->json([
                'status' => 404,
                'response' => 'Not Found',
                'message' => 'No Application(s) found'
            ], 404);
        }

        // Return a successful response with the data
        return response()->json([
            'status' => 200,
            'response' => 'Successful',
            "message" => "All Application(s) fetched successfully",
            "data" => $applications
        ], 200);
    }

    public function approved_applicants()
    {
        // Fetch all payments with their associated application forms
        $applications = User::where(['admission_status' => 'admitted'])->get();

        // Check if the collection is empty
        if ($applications->isEmpty()) {
            return response()->json([
                'status' => 404,
                'response' => 'Not Found',
                'message' => 'No Application(s) found',
                'data' => $applications
            ], 200);
        }

        // Return a successful response with the data
        return response()->json([
            'status' => 200,
            'response' => 'Successful',
            "message" => "All Approved Applicant(s) fetched successfully",
            "data" => $applications
        ], 200);
    }

    public function rejected_applicants()
    {
        // Fetch all payments with their associated application forms
        $applications = User::where(['admission_status' => 'not admitted'])->get();

        // Check if the collection is empty
        if ($applications->isEmpty()) {
            return response()->json([
                'status' => 404,
                'response' => 'Not Found',
                'message' => 'No Application(s) found',
                'data' => $applications
            ], 200);
        }

        // Return a successful response with the data
        return response()->json([
            'status' => 200,
            'response' => 'Successful',
            "message" => "All rejected Applicant(s) fetched successfully",
            "data" => $applications
        ], 200);
    }

    public function applied_students()
    {
        // Fetch all payments with their associated application forms
        $applications = User::where(['is_applied' => true, 'admission_status' => 'pending'])->get();

        // Check if the collection is empty
        if ($applications->isEmpty()) {
            return response()->json([
                'status' => 404,
                'response' => 'Not Found',
                'message' => 'No Application(s) found',
                'data' => $applications
            ], 200);
        }

        // Return a successful response with the data
        return response()->json([
            'status' => 200,
            'response' => 'Successful',
            "message" => "All applied student(s) fetched successfully",
            "data" => $applications
        ], 200);
    }

    public function unapplied_students()
    {
        // Fetch all payments with their associated application forms
        $applications = User::where(['is_applied' => false, 'admission_status' => 'pending'])->get();

        // Check if the collection is empty
        if ($applications->isEmpty()) {
            return response()->json([
                'status' => 404,
                'response' => 'Not Found',
                'message' => 'No Application(s) found',
                'data' => $applications
            ], 200);
        }

        // Return a successful response with the data
        return response()->json([
            'status' => 200,
            'response' => 'Successful',
            "message" => "All Unapplied student(s) fetched successfully",
            "data" => $applications
        ], 200);
    }

    public function singleApplication(Request $request)
    {
        // Fetch all payments with their associated application forms
        $application = User::where(['id' => $request->id])->with('application')->first();

        // Check if the collection is empty
        if (!$application) {
            return response()->json([
                'status' => 404,
                'response' => 'Not Found',
                'message' => 'No Application(s) found'
            ], 404);
        }

        // Return a successful response with the data
        return response()->json([
            'status' => 200,
            'response' => 'Successful',
            "message" => "Application Detail fetched successfully",
            "data" => $application
        ], 200);
    }


    public function approveAdmission(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'application_id' => ['required', 'exists:application_payments,id'],
            'faculty_id' => ['required', 'integer', 'exists:faculties,id'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'semester' => ['required', 'string']
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'errors' => $validator->errors()], 422);
        }

        $user = User::findOrFail($request->application_id);

        //#1. Get the short code
        $course_category = CourseCategory::where([
            'faculty_id' => $request->faculty_id,
            'department_id' => $request->department_id,
            'semester' => $request->semester
        ])->select('short_code')->first();

        //#2. Approve the user or update the application status
        $user->update([
            'admission_status' => 'admitted',
            'level' => $course_category->short_code
        ]);


        $currentYear = Carbon::now()->format('Y');
        $previousYear = Carbon::now()->subYear()->format('Y');
        $academic_year = $previousYear . '-' . $currentYear;
        $deadline_date = Carbon::now()->addWeeks(2)->format('m-d-Y');

        // Prepare admission mail data
        $faculty = Faculty::find($request->faculty_id);
        $department = Department::find($request->department_id);
        $mailData = [
            'name' => $user->first_name . ' ' . $user->last_name,
            'faculty' => $faculty->faculty_name,
            'department' => $department->department_name,
            'school_name' => env('APP_NAME', 'Esut'),
            'academic_year' => $academic_year,
            'deadline_date' => $deadline_date,
            'portal_link' => env('FRONTEND_BASE_URL', 'https://odl-esut.qverselearning.org') . '/auth/student',
            'contact_info' => 'support.odl@esut.edu.ng',
            'sender_name' => 'Admin Name',
            'sender_position' => 'Admissions Office',
            //'date' => Carbon::now(),
        ];

        $pdfService = new PDFService();
        $pdfPath = $pdfService->generatePDF($mailData);


        // Send admission email
        return new AdmissionMail($mailData, $pdfPath);
        Mail::to($user->email)->send(new AdmissionMail($mailData, $pdfPath));


        return response()->json(['message' => 'Admission approved and email sent successfully!']);
    }


    public function denyAdmission(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'application_id' => ['required', 'exists:application_payments,id'],
            'reason' => ['required']
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'errors' => $validator->errors()], 422);
        }

        $user = User::findOrFail($request->application_id);

        if (!$user) {
            return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'Apllication not found'], 404);
        }

        if ($user->admission_status == 'admitted') {
            return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'message' => 'Student has already been admitted'], 422);
        }

        // Approve the user or update the application status
        $user->update(['admission_status' => 'not admitted', 'reason_for_denial' => $request->reason]);

        return response()->json(['message' => 'Admission rejected!']);
    }


    public function fetchdAllAcademicSessions(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'status' => ['sometimes'],
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'errors' => $validator->errors()], 422);
        }

        // Insert validated data
        if (!empty($request->input('status'))) {
            $all = AcademicSession::where("status", $request->status)->first();
        } else {
            $all = AcademicSession::get();
        }

        return response()->json([
            'status' => 200,
            'data' => $all ?? []
        ]);
    }

    public function addAcademicSession(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'unique:academic_session,name'],
            'status' => ['sometimes', 'string', 'in:active'],
        ], [
            'status.in' => 'Status should be ACTIVE if you mean to pass it'
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'errors' => $validator->errors()], 422);
        }

        // Insert validated data
        AcademicSession::insert($validator->validated());

        if ($request->input('status') == 'ACTIVE') {
            AcademicSession::where("status", "ACTIVE")->update([
                'status' => null
            ]);
            AcademicSession::where("name", $request->name)->update([
                'status' => 'ACTIVE'
            ]);
        }

        return response()->json([
            'status' => 200,
            'response' => 'Academic session created successfully'
        ]);
    }

    public function updateAcademicSession(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => ['required', 'exists:academic_session,id'],
            'name' => ['sometimes', 'string'],
            'status' => ['sometimes', 'string', 'in:ACTIVE'],
        ], [
            'status.in' => 'Status should be ACTIVE if you mean to pass it'
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'errors' => $validator->errors()], 422);
        }

        try {
            \DB::beginTransaction();

            if ($request->input('status') == 'ACTIVE') {
                AcademicSession::where("id", "!=", $request->id)->update([
                    'status' => null
                ]);
            }
            AcademicSession::where("id", $request->id)->update($validator->validated());
            \DB::commit();

            return response()->json([
                'status' => 200,
                'message' => 'Academic session Updated',
                'data' => AcademicSession::find($request->id)
            ]);
        } catch (\Throwable $th) {
            \DB::rollBack();
            return response()->json([
                'status' => 500,
                'message' => 'Failed to update academic session',
                'error' => $th->getMessage()
            ], 500);
        }
    }


    public function academicSessionUpdate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'to_session' => ['required', 'exists:academic_session,id'],
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'errors' => $validator->errors()], 422);
        }
        try {
            $isActiveSession = AcademicSession::where('status', 'ACTIVE')->first();
            if ($isActiveSession->id != $request->to_session) {
                return response()->json(['status' => 422, 'response' => 'No Active Session, Set an Active Session', 'errors' => $validator->errors()], 422);
            }

            User::where('role', 'STUDENT')
                ->where('level', 'REGEXP', '^[A-Z]+-[A-Z]+-[0-9]{3}-[0-9]SM$')
                ->update([
                    'academic_session' => $request->to_session,
                    'level' => \DB::raw("
                    CONCAT(
                        SUBSTRING_INDEX(level, '-', 2),
                        '-',
                        CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(level, '-', 3), '-', -1) AS UNSIGNED) + 100,
                        '-1SM'
                    )
                ")
                ]);
            return response()->json([
                'status' => 200,
                'response' => 'Academics session updated successfully'
            ]);
        } catch (\Throwable $th) {
            return response()->json(['status' => false, 'error' => $th->getMessage()], 401);
        }
    }


    public function academicSemesterUpdate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'to_next_semester' => ['sometimes', 'in:TRUE'],
        ], [
            'to_next_semester.in' => 'to_next_semester should be TRUE if you mean to pass it'
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'errors' => $validator->errors()], 422);
        }

        try {
            User::where('role', 'STUDENT')
                ->where('level', 'REGEXP', '^[A-Z]+-[A-Z]+-[0-9]{3}-[12]SM$')
                ->update([
                    'academic_semester' => '2',
                    'level' => \DB::raw("
                    CONCAT(
                        SUBSTRING_INDEX(level, '-', 3),
                        '-',
                        IF(level LIKE '%-1SM', '2SM', '1SM')
                    )
                ")
                ]);

            return response()->json([
                'status' => 200,
                'response' => 'Academic Semester upgraded'
            ]);
        } catch (\Throwable $th) {
            return response()->json(['status' => false, 'error' => $th->getMessage()], 401);
        }
    }

    public function reinitiateStudentSessionUpdate(Request $request)
    {
        User::where('is_moved', '1')->update(['is_moved' => '0']);
        return response()->json([
            'status' => 200,
            'response' => 'Initiation resetted successfully'
        ]);
    }
}
