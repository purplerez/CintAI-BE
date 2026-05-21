<?php

use App\Http\Controllers\AI\AIReviewController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Coding\CodingController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Exam\ExamController;
use App\Http\Controllers\Logic\LogicBuilderController;
use App\Http\Controllers\Project\ProjectController;
use App\Http\Controllers\Admin\ClassController;
use Illuminate\Support\Facades\Route;

// ── Public ────────────────────────────────────────────────────
Route::post('/auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:10,1');

Route::post('/auth/register', [AuthController::class, 'register'])
    ->middleware('throttle:5,1');

// ── Authenticated ─────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'activity.logger'])->group(function () {

    // Auth
    Route::prefix('auth')->group(function () {
        Route::post('/logout',  [AuthController::class, 'logout']);
        Route::get('/me',       [AuthController::class, 'me']);
        Route::post('/profile', [AuthController::class, 'updateProfile']);
    });

    // ── Classes ───────────────────────────────────────────────
    Route::prefix('classes')->group(function () {
        // Siswa: join & view own classes
        Route::post('/join',    [ClassController::class, 'join']);
        Route::get('/mine',     [ClassController::class, 'myClasses']);
        Route::get('/{class}/students', [ClassController::class, 'students']);

        // Guru/Admin: manage
        Route::middleware('role:guru|admin')->group(function () {
            Route::get('/',                                    [ClassController::class, 'index']);
            Route::post('/',                                   [ClassController::class, 'store']);
            Route::put('/{class}',                             [ClassController::class, 'update']);
            Route::delete('/{class}',                          [ClassController::class, 'destroy']);
            Route::delete('/{class}/students/{studentId}',     [ClassController::class, 'removeStudent']);
            Route::get('/{class}/gradebook',                   [\App\Http\Controllers\Admin\GradebookController::class, 'index']);
        });
    });

    // ── Coding Problems ───────────────────────────────────────
    Route::prefix('problems')->group(function () {
        Route::get('/',            [CodingController::class, 'index']);
        Route::get('/{problem}',   [CodingController::class, 'show']);

        Route::middleware('role:guru|admin')->group(function () {
            Route::post('/',                       [CodingController::class, 'store']);
            Route::put('/{problem}',               [CodingController::class, 'update']);
            Route::delete('/{problem}',            [CodingController::class, 'destroy']);
            Route::post('/{problem}/test-cases',                   [CodingController::class, 'storeTestCase']);
            Route::delete('/{problem}/test-cases/{testCase}',      [CodingController::class, 'destroyTestCase']);
            Route::get('/{problem}/submissions',                   [CodingController::class, 'problemSubmissions']); // Guru view all subs
        });
    });

    // Code execution
    Route::prefix('code')->group(function () {
        Route::post('/run',              [CodingController::class, 'run'])->middleware('throttle:30,1');
        Route::post('/submit/{problem}', [CodingController::class, 'submit'])->middleware('throttle:10,1');
        Route::post('/save/{problem}',   [CodingController::class, 'saveProgress']); // Auto-save draft
    });

    // Submissions
    Route::prefix('submissions')->group(function () {
        Route::get('/', [CodingController::class, 'mySubmissions']);
        Route::get('/{submission}/status', [CodingController::class, 'submissionStatus']);
        Route::get('/{submission}/view', [CodingController::class, 'getSubmissionWithCode'])->middleware('role:guru|admin');
    });

    // ── Web Project Sandbox ───────────────────────────────────
    Route::prefix('projects')->group(function () {
        Route::get('/',                                 [ProjectController::class, 'index']);
        Route::get('/{project}',                        [ProjectController::class, 'show']);
        Route::post('/{project}/submit',                [ProjectController::class, 'submitProject']);
        Route::get('/submissions/{submission}/status',  [ProjectController::class, 'submissionStatus']);

        Route::middleware('role:guru|admin')->group(function () {
            Route::post('/',           [ProjectController::class, 'store']);
            Route::put('/{project}',   [ProjectController::class, 'update']);
            Route::get('/{project}/submissions', [ProjectController::class, 'allSubmissions']); // Guru view
        });
        Route::middleware('role:guru|admin')->post('/submissions/{submission}/grade', [ProjectController::class, 'grade']);
    });

    // ── Exam (CBT) ────────────────────────────────────────────
    Route::prefix('exams')->group(function () {
        Route::get('/', [ExamController::class, 'index']);

        Route::middleware('role:guru|admin')->group(function () {
            Route::post('/', [ExamController::class, 'store']);
        });

        Route::get('/{exam}/my-attempt',      [ExamController::class, 'myAttempt']);
        Route::post('/{exam}/start',           [ExamController::class, 'start']);
        Route::post('/{exam}/submit',          [ExamController::class, 'submit']);
        Route::post('/{exam}/log-activity',    [ExamController::class, 'logActivity']);
    });

    // ── Dashboard ─────────────────────────────────────────────
    Route::prefix('dashboard')->group(function () {
        Route::get('/guru',  [DashboardController::class, 'guru'])->middleware('role:guru|admin');
        Route::get('/siswa', [DashboardController::class, 'siswa'])->middleware('role:siswa');
    });

    // ── Logic Builder ─────────────────────────────────────────
    Route::prefix('logic')->group(function () {
        Route::get('/',                [LogicBuilderController::class, 'index']);
        Route::post('/save',           [LogicBuilderController::class, 'save']);
        Route::post('/generate',       [LogicBuilderController::class, 'generate']);
        Route::post('/simulate',       [LogicBuilderController::class, 'simulate']);
        Route::delete('/{flow}',       [LogicBuilderController::class, 'destroy']);
    });

    // ── AI Code Review ────────────────────────────────────────
    Route::post('/ai/review', [AIReviewController::class, 'review'])->middleware('throttle:5,1');
});
