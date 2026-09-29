<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ExamController;
use App\Http\Controllers\Api\ExamPaperController;
use App\Http\Controllers\Api\QuestionController;
use App\Http\Controllers\Api\ScoreController;
use App\Http\Controllers\Api\ScratchPaperController;
use Illuminate\Support\Facades\Route;

Route::middleware('api')->prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('/register', [AuthController::class, 'register']);
});

Route::middleware(['api', 'auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });

    Route::prefix('questions')->group(function () {
        Route::get('/', [QuestionController::class, 'index']);
        Route::post('/', [QuestionController::class, 'store']);
        Route::get('/categories', [QuestionController::class, 'categories']);
        Route::post('/categories', [QuestionController::class, 'storeCategory']);
        Route::get('/{question}', [QuestionController::class, 'show']);
        Route::put('/{question}', [QuestionController::class, 'update']);
        Route::delete('/{question}', [QuestionController::class, 'destroy']);
    });

    Route::prefix('exam-papers')->group(function () {
        Route::get('/', [ExamPaperController::class, 'index']);
        Route::post('/', [ExamPaperController::class, 'store']);
        Route::get('/{examPaper}', [ExamPaperController::class, 'show']);
        Route::put('/{examPaper}', [ExamPaperController::class, 'update']);
        Route::delete('/{examPaper}', [ExamPaperController::class, 'destroy']);
        Route::post('/{examPaper}/questions', [ExamPaperController::class, 'addQuestions']);
        Route::delete('/{examPaper}/questions/{question}', [ExamPaperController::class, 'removeQuestion']);
    });

    Route::prefix('exams')->group(function () {
        Route::get('/', [ExamController::class, 'index']);
        Route::post('/{examPaper}/start', [ExamController::class, 'start']);
        Route::get('/{examPaper}/questions', [ExamController::class, 'getQuestions']);
        Route::post('/{examPaper}/submit', [ExamController::class, 'submit']);
        Route::get('/records', [ExamController::class, 'myRecords']);
        Route::get('/records/{record}', [ExamController::class, 'showRecord']);

        // 草稿纸拍照留存
        Route::get('/{examPaper}/scratch/status', [ScratchPaperController::class, 'status']);
        Route::post('/{examPaper}/scratch/upload', [ScratchPaperController::class, 'upload']);
        Route::post('/{examPaper}/scratch/waiver', [ScratchPaperController::class, 'requestWaiver']);
        Route::get('/{examPaper}/records/{record}/timeline', [ScratchPaperController::class, 'timeline']);
    });

    // 监考复核（教师/管理员）
    Route::prefix('scratch-papers')->group(function () {
        Route::get('/reviews', [ScratchPaperController::class, 'reviewList']);
        Route::post('/records/{record}/review', [ScratchPaperController::class, 'reviewWaiver']);
    });

    Route::prefix('scores')->group(function () {
        Route::get('/statistics', [ScoreController::class, 'statistics']);
        Route::get('/ranking/{examPaper}', [ScoreController::class, 'ranking']);
        Route::get('/analysis/{examPaper}', [ScoreController::class, 'analysis']);
    });
});

// 草稿纸照片下载：需登录，支持 Authorization 头或 <img src="?token="> 两种方式
Route::middleware(['api', 'scratch.photo.auth', 'throttle:120,1'])
    ->get('/scratch-papers/photos/{photo}', [ScratchPaperController::class, 'download']);
