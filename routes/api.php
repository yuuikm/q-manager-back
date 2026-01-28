<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\NewsController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\TestController;
use App\Http\Controllers\Api\CourseMaterialController;
use App\Http\Controllers\Api\NewsCategoryController;
use App\Http\Controllers\Api\DocumentCategoryController;
use App\Http\Controllers\Api\CourseCategoryController;
use App\Http\Controllers\Api\InternalDocumentController;
use App\Http\Controllers\Api\ManagerHelpController;
use App\Http\Controllers\Api\ManagerHelpCategoryController;
use App\Http\Controllers\Api\SliderController;
use App\Http\Controllers\Api\DocumentSubcategoryController;
use App\Http\Controllers\Api\DocumentTypeController;

// Public routes
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

// Public document routes
Route::get('/documents', [AdminController::class, 'getPublicDocuments']);
Route::get('/documents/{id}', [AdminController::class, 'getPublicDocument']);
Route::get('/documents/{id}/preview', [AdminController::class, 'previewDocument']);
Route::get('/categories', [AdminController::class, 'getCategories']);

// Public subcategory routes
Route::get('/subcategories', [DocumentSubcategoryController::class, 'publicIndex']);
Route::get('/subcategories/slug/{slug}', [DocumentSubcategoryController::class, 'showBySlug']);

// Public news routes
Route::get('/news', [NewsController::class, 'index']);
Route::get('/news/{id}', [NewsController::class, 'show']);
Route::get('/news-categories', [NewsCategoryController::class, 'index']);

// Public course routes
Route::get('/courses', [CourseController::class, 'index']);
Route::get('/courses/{id}', [CourseController::class, 'show']);
Route::get('/courses/{id}/materials', [CourseController::class, 'materials']);
Route::post('/courses/{id}/enroll', [CourseController::class, 'enroll']);
Route::get('/course-categories', [CourseCategoryController::class, 'index']);

// Public Manager Help routes
Route::get('/manager-help', [ManagerHelpController::class, 'index']);
Route::get('/manager-help/{id}', [ManagerHelpController::class, 'show']);
Route::get('/manager-help-categories', [ManagerHelpCategoryController::class, 'index']);

// Public Slider routes
Route::get('/sliders', [SliderController::class, 'index']);

// Protected routes
// Public refresh token route (no auth required)
Route::post('/auth/refresh', [AuthController::class, 'refreshToken']);

// User routes (require authentication but not admin privileges)
Route::middleware(['token.auth'])->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/user', [AuthController::class, 'user']);
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
    
    // User-only content access (read-only)
    Route::get('/public/documents', [AdminController::class, 'getPublicDocuments']);
    Route::get('/public/documents/{id}', [AdminController::class, 'getPublicDocument']);
    Route::get('/public/categories', [AdminController::class, 'getCategories']);
    
    // Document purchase and download (requires authentication)
    Route::post('/documents/{id}/purchase', [AdminController::class, 'purchaseDocument']);
    Route::get('/documents/{id}/download', [AdminController::class, 'downloadDocument']);
    Route::get('/user/purchased-documents', [AdminController::class, 'getUserPurchasedDocuments']);
    
});


// Admin-only routes (require admin privileges)
Route::middleware(['token.auth', 'admin.auth'])->prefix('admin')->group(function () {
    // Document management
    Route::post('/documents', [AdminController::class, 'uploadDocument']);
    Route::get('/documents', [AdminController::class, 'getDocuments']);
    Route::get('/documents/{id}', [AdminController::class, 'getDocument']);
    Route::put('/documents/{id}', [AdminController::class, 'updateDocument']);
    Route::patch('/documents/{id}/toggle-status', [AdminController::class, 'toggleDocumentStatus']);
    Route::delete('/documents/{id}', [AdminController::class, 'deleteDocument']);
    
    // Category management
    Route::apiResource('categories', CategoryController::class);
    
    // News management
    Route::apiResource('news', NewsController::class);
    Route::patch('/news/{id}/toggle-status', [NewsController::class, 'togglePublishStatus']);
    Route::post('/news/{id}/like', [NewsController::class, 'toggleLike']);
    Route::post('/news/{id}/comment', [NewsController::class, 'addComment']);
    
    // Course management
    Route::apiResource('courses', CourseController::class);
    Route::patch('/courses/{id}/toggle-status', [CourseController::class, 'togglePublishStatus']);
    Route::get('/courses/{id}/materials', [CourseController::class, 'materials']);
    Route::get('/courses/{id}/tests', [CourseController::class, 'tests']);
    Route::get('/courses/{id}/enrollments', [CourseController::class, 'enrollments']);
    
    // Course materials management
    Route::apiResource('course-materials', CourseMaterialController::class);
    
    // Test management
    Route::apiResource('tests', TestController::class);
    Route::get('/courses/{id}/tests', [TestController::class, 'getCourseTests']);
    Route::post('/tests/{id}/duplicate', [TestController::class, 'duplicate']);
    Route::post('/tests/parse-excel', [TestController::class, 'parseExcel']);
    
    // Category management for each content type
    Route::apiResource('news-categories', NewsCategoryController::class);
    Route::apiResource('document-categories', DocumentCategoryController::class);
    Route::apiResource('document-subcategories', DocumentSubcategoryController::class);
    Route::get('/document-subcategories/by-category/{categoryId}', [DocumentSubcategoryController::class, 'byCategory']);
    Route::get('/document-types', [DocumentTypeController::class, 'index']);
    Route::apiResource('course-categories', CourseCategoryController::class);
    
    // User management
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::patch('/users/{id}/toggle-admin', [UserController::class, 'toggleAdmin']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);
    Route::get('/get-admins', [AdminController::class, 'getAdmins']);
    
    // Internal Documents management
    Route::apiResource('internal-documents', InternalDocumentController::class);
    Route::get('/internal-documents/{id}/download', [InternalDocumentController::class, 'download']);

    // Manager Help management
    Route::apiResource('manager-help', ManagerHelpController::class);
    Route::patch('/manager-help/{id}/toggle-status', [ManagerHelpController::class, 'toggleStatus']);
    Route::apiResource('manager-help-categories', ManagerHelpCategoryController::class);

    // Slider management
    Route::get('/sliders', [SliderController::class, 'adminIndex']);
    Route::apiResource('sliders', SliderController::class)->except(['index']);
    Route::patch('/sliders/{id}/toggle-status', [SliderController::class, 'toggleStatus']);
});

// Test route
Route::get('/ping', function () {
    return ['pong' => true];
});
