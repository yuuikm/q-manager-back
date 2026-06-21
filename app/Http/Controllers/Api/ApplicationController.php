<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DocumentPurchase;
use App\Models\CourseEnrollment;
use Illuminate\Http\Request;

class ApplicationController extends Controller
{
    /**
     * Get all applications (document purchases + course enrollments)
     */
    public function index(Request $request)
    {
        $statusFilter = $request->get('payment_status');
        $typeFilter = $request->get('type'); // 'course' or 'document'
        $search = $request->get('search');

        $applications = [];

        // Get document purchases
        if (!$typeFilter || $typeFilter === 'document') {
            $docQuery = DocumentPurchase::with(['document.category', 'user']);

            if ($statusFilter) {
                $docQuery->where('payment_status', $statusFilter);
            }

            if ($search) {
                $docQuery->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%")
                      ->orWhereHas('document', function ($dq) use ($search) {
                          $dq->where('title', 'like', "%{$search}%");
                      });
                });
            }

            $docPurchases = $docQuery->orderBy('created_at', 'desc')->get();

            foreach ($docPurchases as $purchase) {
                $applications[] = [
                    'id' => $purchase->id,
                    'type' => 'document',
                    'item_title' => $purchase->document ? $purchase->document->title : 'Удалённый документ',
                    'item_id' => $purchase->document_id,
                    'first_name' => $purchase->first_name,
                    'last_name' => $purchase->last_name,
                    'phone' => $purchase->phone,
                    'email' => $purchase->email,
                    'company' => $purchase->company,
                    'notes' => $purchase->notes,
                    'price' => $purchase->price_paid,
                    'payment_status' => $purchase->payment_status ?? 'created',
                    'created_at' => $purchase->created_at,
                    'user' => $purchase->user ? [
                        'id' => $purchase->user->id,
                        'username' => $purchase->user->username,
                        'email' => $purchase->user->email,
                    ] : null,
                ];
            }
        }

        // Get course enrollments
        if (!$typeFilter || $typeFilter === 'course') {
            $courseQuery = CourseEnrollment::with(['course.category', 'user']);

            if ($statusFilter) {
                $courseQuery->where('payment_status', $statusFilter);
            }

            if ($search) {
                $courseQuery->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%")
                      ->orWhereHas('course', function ($cq) use ($search) {
                          $cq->where('title', 'like', "%{$search}%");
                      });
                });
            }

            $courseEnrollments = $courseQuery->orderBy('created_at', 'desc')->get();

            foreach ($courseEnrollments as $enrollment) {
                $applications[] = [
                    'id' => $enrollment->id,
                    'type' => 'course',
                    'item_title' => $enrollment->course ? $enrollment->course->title : 'Удалённый курс',
                    'item_id' => $enrollment->course_id,
                    'first_name' => $enrollment->first_name,
                    'last_name' => $enrollment->last_name,
                    'phone' => $enrollment->phone,
                    'email' => $enrollment->email,
                    'company' => $enrollment->company,
                    'notes' => $enrollment->notes,
                    'price' => $enrollment->course ? $enrollment->course->price : 0,
                    'payment_status' => $enrollment->payment_status ?? 'created',
                    'created_at' => $enrollment->created_at,
                    'user' => $enrollment->user ? [
                        'id' => $enrollment->user->id,
                        'username' => $enrollment->user->username,
                        'email' => $enrollment->user->email,
                    ] : null,
                ];
            }
        }

        // Sort all by created_at desc
        usort($applications, function ($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        return response()->json([
            'applications' => $applications,
            'total' => count($applications),
        ]);
    }

    /**
     * Update application payment status
     */
    public function updateStatus(Request $request, string $type, int $id)
    {
        $request->validate([
            'payment_status' => 'required|in:created,contract,paid',
        ]);

        $newStatus = $request->payment_status;

        if ($type === 'document') {
            $purchase = DocumentPurchase::findOrFail($id);
            $purchase->update(['payment_status' => $newStatus]);

            // If paid, also mark status as completed for backward compatibility
            if ($newStatus === 'paid') {
                $purchase->update([
                    'status' => 'completed',
                    'purchased_at' => $purchase->purchased_at ?? now(),
                ]);
            }

            return response()->json([
                'message' => 'Статус заявки обновлён',
                'application' => $purchase,
            ]);
        }

        if ($type === 'course') {
            $enrollment = CourseEnrollment::findOrFail($id);
            $enrollment->update(['payment_status' => $newStatus]);

            // If paid, mark enrollment as ready for course access
            if ($newStatus === 'paid' && $enrollment->status === 'created') {
                $enrollment->update(['status' => 'enrolled']);
            }

            return response()->json([
                'message' => 'Статус заявки обновлён',
                'application' => $enrollment,
            ]);
        }

        return response()->json(['message' => 'Неверный тип заявки'], 400);
    }

    /**
     * Get current user's applications (for frontend profile page)
     */
    public function userApplications(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $applications = [];

        // Document purchases
        $purchases = DocumentPurchase::with(['document.category'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        foreach ($purchases as $purchase) {
            $applications[] = [
                'id' => $purchase->id,
                'type' => 'document',
                'item_title' => $purchase->document ? $purchase->document->title : 'Удалённый документ',
                'item_id' => $purchase->document_id,
                'price' => $purchase->price_paid,
                'payment_status' => $purchase->payment_status ?? 'created',
                'status' => $purchase->status,
                'created_at' => $purchase->created_at,
                'document' => $purchase->document,
            ];
        }

        // Course enrollments
        $enrollments = CourseEnrollment::with(['course.category', 'course.author'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        foreach ($enrollments as $enrollment) {
            $applications[] = [
                'id' => $enrollment->id,
                'type' => 'course',
                'item_title' => $enrollment->course ? $enrollment->course->title : 'Удалённый курс',
                'item_id' => $enrollment->course_id,
                'price' => $enrollment->course ? $enrollment->course->price : 0,
                'payment_status' => $enrollment->payment_status ?? 'created',
                'status' => $enrollment->status,
                'created_at' => $enrollment->created_at,
                'course' => $enrollment->course,
                'enrollment_status' => $enrollment->status,
                'progress_percentage' => $enrollment->progress_percentage,
            ];
        }

        // Sort by created_at desc
        usort($applications, function ($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        return response()->json([
            'applications' => $applications,
        ]);
    }
}
