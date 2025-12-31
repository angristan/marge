<?php

declare(strict_types=1);

use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\ConfigController;
use App\Http\Controllers\Api\ThreadController;
use Illuminate\Support\Facades\Route;

// Client config - needs session to detect admin
Route::middleware('web')->get('config', ConfigController::class);

// Comment counts (batch)
Route::post('counts', [ThreadController::class, 'counts']);

// Thread comments - use web middleware for admin session detection
Route::prefix('threads/{uri}')->where(['uri' => '.*'])->middleware('web')->group(function (): void {
    Route::get('comments', [ThreadController::class, 'comments']);
    Route::post('comments', [CommentController::class, 'store']);
});

// Single comment operations
Route::prefix('comments')->group(function (): void {
    Route::post('preview', [CommentController::class, 'preview']);
    Route::get('{comment}', [CommentController::class, 'show']);
    Route::put('{comment}', [CommentController::class, 'update']);
    Route::delete('{comment}', [CommentController::class, 'destroy']);
    Route::post('{comment}/upvote', [CommentController::class, 'upvote']);
    Route::post('{comment}/downvote', [CommentController::class, 'downvote']);
});
