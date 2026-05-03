<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Document;
use App\Models\DocumentPurchase;
use App\Models\News;
use App\Models\User;

class DashboardController extends Controller
{
    public function stats()
    {
        $totalUsers = User::where('role', '!=', 'admin')->count();
        $totalAdmins = User::where('role', 'admin')->count();

        $totalEnrollments = CourseEnrollment::count();
        $enrollmentsInProgress = CourseEnrollment::where('status', 'in_progress')->count();
        $enrollmentsCompleted = CourseEnrollment::where('status', 'completed')->count();
        $enrollmentsEnrolled = CourseEnrollment::where('status', 'enrolled')->count();

        $totalCertificates = Certificate::count();

        $totalDocumentPurchases = DocumentPurchase::count();

        $totalCourses = Course::count();
        $activeCourses = Course::where('is_published', true)->count();

        $totalDocuments = Document::count();
        $activeDocuments = Document::where('is_active', true)->count();

        $totalNews = News::count();
        $publishedNews = News::where('is_published', true)->count();

        return response()->json([
            'users' => [
                'total' => $totalUsers,
                'admins' => $totalAdmins,
            ],
            'enrollments' => [
                'total' => $totalEnrollments,
                'enrolled' => $enrollmentsEnrolled,
                'in_progress' => $enrollmentsInProgress,
                'completed' => $enrollmentsCompleted,
            ],
            'certificates' => [
                'total' => $totalCertificates,
            ],
            'document_purchases' => [
                'total' => $totalDocumentPurchases,
            ],
            'courses' => [
                'total' => $totalCourses,
                'active' => $activeCourses,
            ],
            'documents' => [
                'total' => $totalDocuments,
                'active' => $activeDocuments,
            ],
            'news' => [
                'total' => $totalNews,
                'published' => $publishedNews,
            ],
        ]);
    }
}
