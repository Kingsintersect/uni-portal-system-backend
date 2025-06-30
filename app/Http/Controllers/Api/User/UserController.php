<?php

namespace App\Http\Controllers\Api\User;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Department;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\ApplicationPayment;
use App\Models\ApplicationForm;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['viewStudents', 'createStudent', 'viewSingleStudent', 'multiUserUpload', 'multiUserPatchName']]);
    }

    public function createStudent(Request $request)
    {

        // dd($request->all());
        $this->validate($request, [
            'first_name' => 'required',
            'last_name' => 'required',
            'email' => 'required',
            'phone' => 'required',
            // 'login' => 'required',
            // 'reg_number' => 'required',
            'level' => 'required',
            'department_id' => 'required',
            /*'password' => [
                'required',
                'confirmed',
                'min:8',
                'regex:/[a-z]/',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
                'regex:/[@$!%*#?&]/',
            ],*/
        ]);

        $exist_reg = User::where(['email' => $request->email])->first();
        if ($exist_reg) {
            return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'Email exists'], 404);
        }

        // Get the student's department
        $department_id = $request->department_id ?? $exist_reg->department_id;

        $department = Department::where(['id' => $department_id])->first();
        if (!$department) {
            return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'Department data invalid'], 404);
        }

        $student_department = $department->department_name;

        $user = new User;
        $user->first_name = $request->input('first_name');
        $user->last_name = $request->input('last_name');
        $user->other_name = $request->input('last_name') ?? null;
        $user->nationality = $request->input('nationality', 'Nigeria') ?? 'Nigeria';
        $user->state = $request->input('state');
        $user->gender = $request->input('gender');
        $user->amount = $request->input('amount', '0') ?? '0';
        $user->role = 'STUDENT';
        $user->program = $request->input('program');
        $user->faculty_id = $department->faculty_id;
        $user->department_id = $department_id;
        $user->email = $request->input('email');
        $user->phone_number = $request->input('phone');
        $user->level = $request->level;
        $user->password = bcrypt('P@55word');
        $user->reference = Carbon::now()->format('YmdHisv') . Str::random(6);

        if (!empty($request->input('acceptance_fee_payment_status'))) $user->acceptance_fee_payment_status = $request->acceptance_fee_payment_status;
        if (!empty($request->input('application_payment_status'))) $user->application_payment_status = $request->application_payment_status;

        $isFullStudent = false;
        if (!empty($request->input('tuition_payment_status')) && $request->input('tuition_payment_status') == 1) {
            $user->tuition_payment_status = 1;
            if (!empty($request->input('tuition_amount_paid')) && floatval($request->input('tuition_amount_paid')) > 0) {
                $user->reg_number = Carbon::now()->format('Y') . '345' . rand(1, 999);
                $reg_number = $user->reg_number;
                $isFullStudent = true;
                $user->tuition_amount_paid = $request->input('tuition_amount_paid');
            }
        }
        $user->save();

        // Save to LMS
        if ($isFullStudent) {
            $apply = User::where(['reg_number' => $reg_number])->first();
            // $application = ApplicationForm::where(['user_id' => $apply->id])->first();

            // Save the user to the second database
            $userId = DB::connection('mysql2')->table(env('LMS_DB_PREFIX', 'mdl_') . 'user')->insertGetId([
                'auth' => 'manual',
                'confirmed' => 1,
                'mnethostid' => 1,
                'username' => $user->reg_number ?? $request->input('reg_number'),
                'password' => '$6$rounds=10000$wUSMMdSKTp.HTT2h$f1gxqp3Zcvw4TatcfZ5ISqygbbTJpelNr7iQ1Pqdr5dxP0MsFgJg4MmGXuMPUltiWrKw7cwnTYlbjRgVDdKP30',
                'idnumber' => $user->reg_number ?? $request->input('reg_number'),
                'firstname' => $request->input('first_name'),
                'lastname' => $request->input('last_name'),
                'email' => $request->input('email'),
                'phone1' => $request->input('phone'),
                'phone2' => $request->input('phone'),
                'institution' => env('INSTITUTION', 'ESUT'),
                // 'department' => $student_department ?? 'N/A',
                'department' => $request->level ?? 'N/A',
                'address' => $request->input('address'),
                'city' => $apply->state,
                'country' => 'NG',
            ]);

            DB::connection('mysql2')->table(env('LMS_DB_PREFIX', 'mdl_') . 'user_info_data')->insert([
                'userid' => $userId,
                'fieldid' => 1,
                'data' => $user->reg_number ?? $request->input('reg_number')
            ]);

            DB::connection('mysql2')->table(env('LMS_DB_PREFIX', 'mdl_') . 'user_info_data')->insert([
                'userid' => $userId,
                'fieldid' => 6,
                'data' => $request->level
            ]);
        }


        return response()->json([
            'message' => 'Registration successful',
            'user' => $user
        ], 201);
    }


    public function viewStudents()
    {
        $users = User::all();
        if ($users->count() > 0) {
            return response()->json(['users' => $users], 200);
        } else {
            return response()->json(['message' => 'No user(s) found'], 404);
        }
    }

    public function viewSingleStudent(Request $request)
    {
        $this->validate($request, [
            'reg_number' => 'required',
        ]);

        $user = User::where('reg_number',  $request->input('reg_number'))
            ->exists();
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        } else {
            $fetched_user = User::where('reg_number',  $request->input('reg_number'))
                ->first();
            return response()->json($fetched_user, 200);
        }
    }



    public function multiUserUpload(Request $request)
    {
        // if (!User::checkAdminAuthority()) {
        //     return response()->json(['status' => 401, 'response' => 'Unauthorized', 'message' => 'Unauthorized User'], 401);
        // }

        $request->validate([
            'file' => 'required|mimes:xlsx,xls'
        ]);

        // try {
        $v = ExcelReadController::import($request);

        if ($v[0] == "success") {
            return response()->json([
                'message' => 'success',
                'data' => "Data inserted successfully!",
                'nonEmailUsers' => $v[1] ?? []
            ], 200);
        } else {
            return response()->json([
                'message' => 'error',
                'data' => "Failed error " . $v[0] ?? ''
            ], 401);
        }
    }

    public function latestMultiUserPatchName(Request $request)
    {
        // if (!User::checkAdminAuthority()) {
        //     return response()->json(['status' => 401, 'response' => 'Unauthorized', 'message' => 'Unauthorized User'], 401);
        // }

        $request->validate([
            'file' => 'required|mimes:xlsx,xls'
        ]);

        // try {
        $v = ExcelReadController::latestMultiUserPatchName($request);

        if ($v[0] == "success") {
            return response()->json([
                'message' => 'success',
                'data' => "Data inserted successfully!",
                'nonEmailUsers' => $v[1] ?? []
            ], 200);
        } else {
            return response()->json([
                'message' => 'error',
                'data' => "Failed error " . $v[0] ?? ''
            ], 401);
        }
    }



    public function multiUserPatchName(Request $request)
    {
        // if (!User::checkAdminAuthority()) {
        //     return response()->json(['status' => 401, 'response' => 'Unauthorized', 'message' => 'Unauthorized User'], 401);
        // }

        $request->validate([
            'file' => 'required|mimes:xlsx,xls'
        ]);

        // try {
        $v = ExcelReadController::patchName($request);

        if ($v[0] == "success") {
            return response()->json([
                'message' => 'success',
                'data' => "Data inserted successfully!",
                'nonEmailUsers' => $v[1] ?? []
            ], 200);
        } else {
            return response()->json([
                'message' => 'error',
                'data' => "Failed error " . $v[0] ?? ''
            ], 401);
        }
        // } catch (\Throwable $th) {
        //     return response()->json([
        //         'message' => 'error',
        //         'data' => "You need to check the correct file order..or contact admin " . $th->getMessage()
        //     ], 401);
        // }
    }





    public function changeRoles(Request $request)
    {
        if (!User::checkAdminAuthority()) {
            return response()->json(['status' => 401, 'response' => 'Unauthorized', 'message' => 'Unauthorized User'], 401);
        }
        $validator = Validator::make($request->all(), [
            'user_id' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'max:255'],
            'role' => ['required', 'string', 'max:50'],
        ]);


        if ($validator->fails()) {
            return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'errors' => $validator->errors()], 422);
        }


        // Check if Roles in array of roles

        if (!in_array(strtoupper($request->role), User::approvedRoles())) {
            return response()->json([
                'type' => "error",
                'message' => 'Invalid role',
            ], 401);
        }

        // Check email
        $userValidation = User::where([
            'id' => $request->user_id,
            'email' => $request->email,
        ])->first();

        if (is_null($userValidation)) {
            return response()->json([
                'type' => "error",
                'message' => 'Verification error',
            ], 401);
        }

        DB::beginTransaction();
        try {

            $admin = User::where('id', $request->user_id)->update([
                // 'user_id' => $request->user_id,
                'role' => $request->role
            ]);
            DB::commit();
            return response()->json([
                'type' => "success",
                'message' => 'Role changed successfully',
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'type' => "error",
                'message' => 'Error: ' . $th->getMessage(),
                'log' => $th
            ], 401);
        }
    }

    public function getRoles(Request $request)
    {
        try {
            return response()->json([
                'message' => 'success',
                'data' => User::approvedRoles() ?? []
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'error: ' . $th->getMessage(),
                'data' => []
            ], 401);
        }
    }

    public function getPrograms(Request $request)
    {
        try {
            return response()->json([
                'message' => 'success',
                'data' => User::approvedPrograms() ?? []
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'error',
                'data' => []
            ], 401);
        }
    }


    public function allUsers(Request $request)
    {
        if (!User::checkAdminAuthority()) {
            return response()->json(['status' => 401, 'response' => 'Unauthorized', 'message' => 'Unauthorized User'], 401);
        }
        try {
            $users = User::all();
            return response()->json([
                'message' => 'success',
                'data' => $users
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'error',
                'data' => []
            ], 401);
        }
    }


    public function singleUser(Request $request)
    {
        $user_id = $request->route('user_id');

        try {
            $user = User::where(['id' => $user_id])->first();
            return response()->json([
                'message' => 'success',
                'data' => $user
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'error',
                'data' => []
            ], 401);
        }
    }


    public function updateUser(Request $request)
    {
        $escapeUpdate = ['id'];

        $userReference = $request->input('id');
        $fieldsToUpdate = [];
        $formFields = $request->keys();

        foreach ($formFields as $k) {
            if (in_array($k, $escapeUpdate)) continue;
            $fieldsToUpdate[$k] = $request->{$k};
        }

        if (empty($request->input('id'))) {
            return response()->json(['status' => 401, 'response' => 'Id is empty', 'message' => 'User id is key for this update'], 401);
        }

        if (!User::checkAdminAuthority()) {
            return response()->json(['status' => 401, 'response' => 'Unauthorized', 'message' => 'Unauthorized User'], 401);
        }

        if (empty($fieldsToUpdate)) {
            return response()->json(['status' => 401, 'response' => 'Unproccessable content', 'message' => 'Fields should not be empty'], 401);
        }

        try {
            $user = User::where("id", $userReference)->first();
            if (!$user) {
                return response()->json(['status' => 422, 'response' => 'Not Found', 'message' => 'Not Found!'], 422);
            }

            foreach ($fieldsToUpdate as $k => $v) {
                $user->$k = $v;
            }
            if (empty($user->reference)) {
                $user->reference = Carbon::now()->format('YmdHisv') . Str::random(6);
            }
            $user->save();

            return response()->json([
                'message' => 'success',
                'data' => $user
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'error: ' . $th->getMessage(),
                'data' => []
            ], 422);
        }
    }

    public function allStudents(Request $request)
    {
        if (!User::checkAdminAuthority()) {
            return response()->json(['status' => 401, 'response' => 'Unauthorized', 'message' => 'Unauthorized User'], 401);
        }
        try {
            $students = User::where(['role' => 'STUDENT'])->get();
            return response()->json([
                'message' => 'success',
                'data' => $students
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'error',
                'data' => []
            ], 401);
        }
    }

    public function allTeachers(Request $request)
    {
        if (!User::checkAdminAuthority()) {
            return response()->json(['status' => 401, 'response' => 'Unauthorized', 'message' => 'Unauthorized User'], 401);
        }
        try {
            $_data = User::where(['role' => 'TEACHER'])->get();
            return response()->json([
                'message' => 'success',
                'data' => $_data
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'error',
                'data' => []
            ], 401);
        }
    }

    public function allManagers(Request $request)
    {
        if (!User::checkAdminAuthority()) {
            return response()->json(['status' => 401, 'response' => 'Unauthorized', 'message' => 'Unauthorized User'], 401);
        }
        try {
            $_data = User::where(['role' => 'MANAGER'])->get();
            return response()->json([
                'message' => 'success',
                'data' => $_data
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'error',
                'data' => []
            ], 401);
        }
    }

    public function allAdmin(Request $request)
    {
        if (!User::checkAdminAuthority()) {
            return response()->json(['status' => 401, 'response' => 'Unauthorized', 'message' => 'Unauthorized User'], 401);
        }
        try {
            $_data = User::where(['role' => 'ADMIN'])->get();
            return response()->json([
                'message' => 'success',
                'data' => $_data
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'error',
                'data' => []
            ], 401);
        }
    }

    public function generateUserReference(Request $request)
    {
        if (!User::checkAdminAuthority()) {
            return response()->json(['status' => 401, 'response' => 'Unauthorized', 'message' => 'Unauthorized User'], 401);
        }
        $requireds = ['reference', 'reg_number'];

        if (empty($request->input('refKey'))) {
            return response()->json(['status' => 401, 'response' => 'refKey is empty', 'message' => 'refKey is required'], 401);
        }

        if (!in_array($request->input('refKey'), $requireds)) {
            return response()->json(['status' => 401, 'response' => 'refKey invalid', 'message' => 'refKey should be either reference or reg_number'], 401);
        }

        $generated = ($request->input('refKey') == 'reference')
            ? Carbon::now()->format('YmdHisv') . Str::random(6)
            : Carbon::now()->format('Y') . '345' . rand(1, 999);

        return response()->json([
            'message' => 'success',
            'data' => $request->input('refKey') . ': ' . $generated
        ], 200);
    }
}
