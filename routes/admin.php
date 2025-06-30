<?php

use App\Http\Controllers\Api\Admin\AdminController;
use App\Http\Controllers\Api\Admin\CourseAssignmentController;
use App\Http\Controllers\Api\Admin\CourseCategoryController;
use App\Http\Controllers\Api\Admin\CourseController;
use App\Http\Controllers\Api\Admin\DepartmentController;
use App\Http\Controllers\Api\Admin\FacultyController;
use App\Http\Controllers\Api\Admin\LgaController;
use App\Http\Controllers\Api\Admin\StateController;
use App\Http\Controllers\Api\Application\ApplicationController;
use App\Http\Controllers\Api\Free\FreeController;
use App\Http\Controllers\Api\User\EnrollmentController;
use App\Http\Controllers\Api\User\LMSReportController;
use App\Http\Controllers\Api\User\StudentResultController;
use App\Http\Controllers\Api\User\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


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
        //Admin Route
        Route::prefix('admin')->group(function () {
            Route::post('register', [AdminController::class, 'register'])->name('register');
            Route::post('admin-login', [AdminController::class, 'login']);
            Route::get('all-applications', [AdminController::class, 'allApplication'])->name('allApplication');
            Route::get('single-application', [AdminController::class, 'singleApplication'])->name('singleApplication');
            Route::get('approved-applicants', [AdminController::class, 'approved_applicants'])->name('approved_applicants');
            Route::get('rejected-applicants', [AdminController::class, 'rejected_applicants'])->name('rejected_applicants');
            Route::get('applied-students', [AdminController::class, 'applied_students'])->name('applied_students');
            Route::get('unapplied-students', [AdminController::class, 'unapplied_students'])->name('unapplied_students');
            Route::post('approve-application', [AdminController::class, 'approveAdmission'])->name('approveAdmission');
            Route::delete('reject-application', [AdminController::class, 'denyAdmission'])->name('denyAdmission');
            Route::post('get-lms-user', [LMSReportController::class, 'getMoodleUserId']);
            // Refurb
            Route::get('all-sessions', [AdminController::class, 'fetchdAllAcademicSessions']);
            Route::post('add-session', [AdminController::class, 'addAcademicSession']);
            Route::patch('modify-session', [AdminController::class, 'updateAcademicSession']);
            Route::post('update-session', [AdminController::class, 'academicSessionUpdate']);
            Route::post('upgrade-semester', [AdminController::class, 'academicSemesterUpdate']);
            Route::get('init-students-sessions-updates', [AdminController::class, 'reinitiateStudentSessionUpdate']);


            Route::prefix('state')->group(function () {
                Route::post('add-state', [StateController::class, 'add_state']);
                Route::get('all-states', [StateController::class, 'all_state']);
                Route::put('update-state', [StateController::class, 'update_state']);
                Route::delete('delete-state', [StateController::class, 'delete_state']);
            });
            Route::prefix('lga')->group(function () {
                Route::post('add-lga', [LgaController::class, 'add_lga']);
                Route::get('all-lga', [LgaController::class, 'all_lga']);
                Route::put('update-lga', [LgaController::class, 'update_lga']);
                Route::delete('delete-lga', [LgaController::class, 'delete_lga']);
            });
            Route::prefix('faculty')->group(function () {
                Route::post('create-faculty', [FacultyController::class, 'create_faculty']);
                Route::patch('update-faculty', [FacultyController::class, 'update_faculty']);
                Route::delete('delete-faculty', [FacultyController::class, 'delete_faculty']);
            });
            Route::prefix('department')->group(function () {
                Route::post('create-department', [DepartmentController::class, 'create_department']);
                Route::patch('update-department', [DepartmentController::class, 'update_department']);
                Route::delete('delete-department', [DepartmentController::class, 'delete_department']);
            });
            Route::prefix('course')->group(function () {
                Route::post('create-course', [CourseController::class, 'create_course']);
                Route::put('update-course', [CourseController::class, 'update_course']);
                Route::delete('delete-course', [CourseController::class, 'delete_course']);
                Route::get('grading', [LMSReportController::class, 'grading']);
                Route::get('get-lms-courses', [LMSReportController::class, 'studentCourses']);

                Route::get('course-gradings/{course_id}', [StudentResultController::class, 'courseGradingResults']);
                Route::put('process-gradings/{course_id}', [StudentResultController::class, 'processAndStoreCourseGrades']);
                Route::get('view-user-results/{user_id}', [StudentResultController::class, 'adminViewUserResults']);
            });

            Route::prefix('course-category')->group(function () {
                Route::post('create-course-category', [CourseCategoryController::class, 'create_course_category']);
                Route::put('update-course-category', [CourseCategoryController::class, 'update_course_category']);
                Route::delete('delete-course-category', [CourseCategoryController::class, 'delete_course_category']);
            });

            Route::prefix('course-assignment')->group(function () {
                Route::get('/all', [CourseAssignmentController::class, 'allCourseAssignments']);
                Route::get('/get-courses', [CourseAssignmentController::class, 'getCoursesByCategory']);
                Route::post('/create', [CourseAssignmentController::class, 'assign']);
                Route::delete('/delete', [CourseAssignmentController::class, 'delete']);
            });
        });
        //Admin Route Ends
    });
});
