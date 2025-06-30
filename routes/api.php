<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Free\FreeController;
use App\Http\Controllers\Api\User\UserController;
use App\Http\Controllers\Api\Admin\AdminController;
use App\Http\Controllers\Api\User\EnrollmentController;
use App\Http\Controllers\Api\Application\ApplicationController;
use App\Http\Controllers\Api\Admin\FacultyController;
use App\Http\Controllers\Api\Admin\DepartmentController;
use App\Http\Controllers\Api\Admin\CourseController;
use App\Http\Controllers\Api\Admin\LgaController;
use App\Http\Controllers\Api\Admin\StateController;
use App\Http\Controllers\Api\Admin\CourseCategoryController;
use App\Http\Controllers\Api\Admin\CourseAssignmentController;
use App\Http\Controllers\Api\User\CourseRegistrationController;
use App\Http\Controllers\Api\User\LMSReportController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });

Route::prefix('v1')->group(function () {
    Route::middleware('api')->group(function () {
        //Application Route
        Route::prefix('application')->group(function () {
            Route::post('purchase', [ApplicationController::class, 'initializePayment'])->name('initializePayment');
            Route::get('verify-purchase', [ApplicationController::class, 'verifyPayment'])->name('verifyPayment');
            Route::post('login', [ApplicationController::class, 'login'])->name('login');
            Route::post('upload-passport', [ApplicationController::class, 'uploadPassport'])->name('uploadPassport');
            Route::post('upload-first-sitting-result', [ApplicationController::class, 'uploadFirstSittingResult'])->name('uploadFirstSittingResult');
            Route::post('upload-second-sitting-result', [ApplicationController::class, 'uploadSecondSittingResult'])->name('uploadSecondSittingResult');
            Route::post('application-form', [ApplicationController::class, 'applicationForm'])->name('applicationForm');
            Route::post('acceptance-fee-payment', [ApplicationController::class, 'initializeAcceptancePayment'])->name('initializeAcceptancePayment');
            Route::get('verify-acceptance', [ApplicationController::class, 'verifyAcceptancePayment'])->name('verifyAcceptancePayment');
            Route::post('tuition-fee-payment', [ApplicationController::class, 'initializeTuitionPayment'])->name('initializeTuitionPayment');
            Route::get('verify-tuition', [ApplicationController::class, 'verifyTuitionPayment'])->name('verifyTuitionPayment');
            Route::get('application-data', [ApplicationController::class, 'applicationData'])->name('applicationData');
            Route::get('profile', [ApplicationController::class, 'profile'])->name('profile');
            Route::post('change-password', [ApplicationController::class, 'changePassword']);
            Route::get('/generate-user-reference', [UserController::class, 'generateUserReference']);

            Route::post('/erase-application-table', [ApplicationController::class, 'truncate']);
        });
        //Application Route Ends

    });

    Route::prefix('account')->group(function () {
        Route::get('/all-students', [UserController::class, 'viewStudents']);
        Route::get('/single-student', [UserController::class, 'viewSingleStudent']);
        Route::post('/create-student', [UserController::class, 'createStudent']);
        Route::any('/enroll-student', [EnrollmentController::class, 'initialzePayment']);
        Route::any('/verify-payment', [EnrollmentController::class, 'verifyPayment']);
        Route::post('/multi-user-upload', [UserController::class, 'multiUserUpload']);
        Route::post('/multi-user-patchname', [UserController::class, 'multiUserPatchName']);
        Route::post('/latest-multi-user-upload', [UserController::class, 'latestMultiUserPatchName']);

        Route::get('/user/{user_id}', [UserController::class, 'singleUser']);
        Route::put('/user/{user_id}', [UserController::class, 'updateUser']);
        Route::get('/allusers', [UserController::class, 'allUsers']);
        Route::get('/allstudents', [UserController::class, 'allStudents']);
        Route::get('/allteachers', [UserController::class, 'allTeachers']);
        Route::get('/alladmin', [UserController::class, 'allAdmin']);
        Route::get('/allmanagers', [UserController::class, 'allManagers']);
        Route::post('/setroles', [UserController::class, 'changeRoles']);
        Route::get('/getroles', [UserController::class, 'getRoles']);
        Route::get('/getprograms', [UserController::class, 'getPrograms']);
        Route::post('/user-update', [UserController::class, 'updateUser']);
    });


    Route::get('/single-state', [StateController::class, 'single_state']); // Fetch the details of a single state
    Route::get('/all-states', [FreeController::class, 'allState']);

    Route::get('/lga', [FreeController::class, 'localgovernment']);
    Route::get('/single-lga', [LgaController::class, 'single_lga']);

    Route::get('/faculties', [FacultyController::class, 'all_faculties']);
    Route::get('/faculty', [FacultyController::class, 'faculty']);
    Route::get('/faculties/active', [FacultyController::class, 'active_faculties']);
    Route::get('/active-faculties/depts', [Facultycontroller::class, 'all_departments_in_an_active_faculty']);


    Route::get('/departments', [DepartmentController::class, 'all_departments']);
    Route::get('/department', [DepartmentController::class, 'department']);
    Route::get('/faculty-departments', [DepartmentController::class, 'departmentInFaculty']);


    Route::get('/courses', [CourseController::class, 'all_courses']);
    Route::get('/course', [CourseController::class, 'course']);

    Route::get('/course-categories', [CourseCategoryController::class, 'all_course_categories']);
    Route::get('/course-category', [CourseCategoryController::class, 'course_category']);

    Route::prefix('course-enrolment')->group(function () {
        Route::get('/courses', [CourseRegistrationController::class, 'getCourses']);
    });

    Route::prefix('odl')->group(function () {
        Route::get('/categories-and-courses', [LMSReportController::class, 'getMoodleCategoriesAndCourses']);
        Route::get('/cohorts-and-categories', [LMSReportController::class, 'getMoodleCohortAndCategories']);
        Route::get('/cohorts-and-categories-and-courses', [LMSReportController::class, 'getMoodleCohortAndCategoriesAndCourses']);
        Route::get('/users-info', [LMSReportController::class, 'getMoodleUsersInfo']);
        Route::get('/user', [LMSReportController::class, 'getMoodleSingleUsersInfo']);

        Route::get('/categories', [LMSReportController::class, 'getMoodleCategories']);
        Route::get('/courses', [LMSReportController::class, 'getMoodleCourses']);
    });

    Route::get('/test-mail', [ApplicationController::class, 'testMail'])->name('test_mail');
});
