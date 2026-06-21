<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\Test;
use App\Models\CourseCategory;
use App\Models\Certificate;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CourseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Course::with(['author', 'materials', 'tests', 'enrollments', 'category']);

        // Filter by type - handle both single type and array of types
        if ($request->has('type')) {
            $typeFilter = $request->get('type');
            if (is_array($typeFilter)) {
                // Filter by any of the types in the array
                $query->where(function($q) use ($typeFilter) {
                    foreach ($typeFilter as $type) {
                        $q->orWhereJsonContains('type', $type);
                    }
                });
            } else {
                // Single type filter
                $query->whereJsonContains('type', $typeFilter);
            }
        }

        // Filter by category
        if ($request->has('category')) {
            $query->whereHas('category', function($q) use ($request) {
                $q->where('name', $request->get('category'));
            });
        }

        // For public access, only show published courses by default
        if (!$request->has('published')) {
            $query->where('is_published', true);
        } else {
            $query->where('is_published', $request->boolean('published'));
        }

        // Filter by featured status
        if ($request->has('featured')) {
            $query->where('is_featured', $request->boolean('featured'));
        }

        // Search by title or description
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by author
        if ($request->has('author_id') && $request->author_id) {
            $query->where('created_by', $request->author_id);
        }

        // Filter by date range
        if ($request->has('start_date') && $request->start_date) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->has('end_date') && $request->end_date) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $courses = $query->orderBy('created_at', 'desc')->paginate(15);
        return response()->json($courses);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'content' => 'required|string',
            'price' => 'required|numeric|min:0',
            'type' => 'required|string', // Will be JSON string from frontend
            'category' => 'required|string|max:255',
            'featured_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'certificate_template' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            // removed max_students and duration_hours
            'requirements' => 'nullable|string',
            'learning_outcomes' => 'nullable|string',
            'zoom_link' => 'nullable|url',
            'schedule' => 'nullable|array',
            'is_published' => 'boolean',
            'is_featured' => 'boolean',
        ]);

        // Check if user is authenticated
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }
        $userId = $user->id;

        // Handle type - can be JSON string or array
        $typeData = $request->type;
        if (is_string($typeData)) {
            $typeData = json_decode($typeData, true);
        }
        if (!is_array($typeData)) {
            $typeData = [$typeData];
        }
        // Validate types
        $validTypes = ['online', 'self_learning', 'offline'];
        $typeData = array_filter($typeData, function($type) use ($validTypes) {
            return in_array($type, $validTypes);
        });
        if (empty($typeData)) {
            return response()->json(['error' => 'At least one valid type is required'], 422);
        }
        // Limit to 3 types
        $typeData = array_slice($typeData, 0, 3);

        $data = [
            'title' => $request->title,
            'slug' => Str::slug($request->title),
            'description' => $request->description,
            'content' => $request->input('content'),
            'price' => $request->price,
            'type' => $typeData, // Store as JSON array
            // removed max_students and duration_hours
            'requirements' => $request->requirements,
            'learning_outcomes' => $request->learning_outcomes,
            'zoom_link' => $request->zoom_link,
            'schedule' => $request->schedule,
            'is_published' => $request->is_published ?? false,
            'is_featured' => $request->is_featured ?? false,
            'created_by' => $userId,
        ];

        // Handle image uploads
        if ($request->hasFile('featured_image')) {
            $data['featured_image'] = $request->file('featured_image')->store('courses/images', 'public');
        }

        if ($request->hasFile('certificate_template')) {
            $data['certificate_template'] = $request->file('certificate_template')->store('courses/certificates', 'public');
        }

        // Handle category - find existing or create new
        $categoryName = $request->category;
        $category = CourseCategory::where('name', $categoryName)->first();
        
        if (!$category) {
            // Create new category
            $category = CourseCategory::create([
                'name' => $categoryName,
                'slug' => Str::slug($categoryName),
            ]);
        }

        $data['category_id'] = $category->id;
        $course = Course::create($data);
        
        $course->load(['author', 'materials', 'tests', 'enrollments', 'category']);

        return response()->json($course, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $course = Course::with(['author', 'category'])->findOrFail($id);
        
        // Only show published courses for public access
        if (!$course->is_published) {
            return response()->json(['message' => 'Course not found'], 404);
        }
        
        // Increment view count
        $course->increment('views_count');
        
        return response()->json($course);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $course = Course::findOrFail($id);

        $request->validate([
            'title' => ['required', 'string', 'max:255', Rule::unique('courses', 'title')->ignore($course->id)],
            'description' => 'required|string',
            'content' => 'required|string',
            'price' => 'required|numeric|min:0',
            'type' => 'required|string', // Will be JSON string from frontend
            'category' => 'required|string|max:255',
            'featured_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'certificate_template' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            // removed max_students and duration_hours
            'requirements' => 'nullable|string',
            'learning_outcomes' => 'nullable|string',
            'zoom_link' => 'nullable|url',
            'schedule' => 'nullable|array',
            'is_published' => 'boolean',
            'is_featured' => 'boolean',
        ]);

        // Handle type - can be JSON string or array
        $typeData = $request->type;
        if (is_string($typeData)) {
            $typeData = json_decode($typeData, true);
        }
        if (!is_array($typeData)) {
            $typeData = [$typeData];
        }
        // Validate types
        $validTypes = ['online', 'self_learning', 'offline'];
        $typeData = array_filter($typeData, function($type) use ($validTypes) {
            return in_array($type, $validTypes);
        });
        if (empty($typeData)) {
            return response()->json(['error' => 'At least one valid type is required'], 422);
        }
        // Limit to 3 types
        $typeData = array_slice($typeData, 0, 3);

        $data = [
            'title' => $request->title,
            'slug' => Str::slug($request->title),
            'description' => $request->description,
            'content' => $request->input('content'),
            'price' => $request->price,
            'type' => $typeData, // Store as JSON array
            // removed max_students and duration_hours
            'requirements' => $request->requirements,
            'learning_outcomes' => $request->learning_outcomes,
            'zoom_link' => $request->zoom_link,
            'schedule' => $request->schedule,
            'is_published' => $request->is_published ?? $course->is_published,
            'is_featured' => $request->is_featured ?? $course->is_featured,
        ];

        // Handle image uploads
        if ($request->hasFile('featured_image')) {
            // Delete old image
            if ($course->featured_image) {
                Storage::disk('public')->delete($course->featured_image);
            }
            $data['featured_image'] = $request->file('featured_image')->store('courses/images', 'public');
        }

        if ($request->hasFile('certificate_template')) {
            // Delete old template
            if ($course->certificate_template) {
                Storage::disk('public')->delete($course->certificate_template);
            }
            $data['certificate_template'] = $request->file('certificate_template')->store('courses/certificates', 'public');
        }

        // Handle category - find existing or create new
        $categoryName = $request->category;
        $category = CourseCategory::where('name', $categoryName)->first();
        
        if (!$category) {
            // Create new category
            $category = CourseCategory::create([
                'name' => $categoryName,
                'slug' => Str::slug($categoryName),
            ]);
        }

        $data['category_id'] = $category->id;
        $course->update($data);
        
        $course->load(['author', 'materials', 'tests', 'enrollments', 'category']);

        return response()->json($course);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $course = Course::findOrFail($id);

        // Delete associated images
        if ($course->featured_image) {
            Storage::disk('public')->delete($course->featured_image);
        }
        if ($course->certificate_template) {
            Storage::disk('public')->delete($course->certificate_template);
        }

        // Delete the course (this will also delete pivot table entries due to cascade)
        $course->delete();

        return response()->json(['message' => 'Course deleted successfully']);
    }

    /**
     * Get course materials
     */
    public function materials(string $id)
    {
        $course = Course::findOrFail($id);
        
        // Only show materials for published courses for public access
        if (!$course->is_published) {
            return response()->json(['message' => 'Course not found'], 404);
        }
        
        $materials = $course->materials()->orderBy('sort_order')->get();
        return response()->json($materials);
    }

    /**
     * Get course tests
     */
    public function tests(string $id)
    {
        $course = Course::findOrFail($id);
        $tests = $course->tests()->get();
        return response()->json($tests);
    }

    /**
     * Get course enrollments
     */
    public function enrollments(string $id)
    {
        $course = Course::findOrFail($id);
        $enrollments = $course->enrollments()->with('user')->get();
        return response()->json($enrollments);
    }

    public function togglePublishStatus($id)
    {
        $course = Course::findOrFail($id);
        $course->update(['is_published' => !$course->is_published]);

        return response()->json([
            'message' => 'Course publish status updated successfully',
            'course' => $course->load(['author', 'category']),
        ]);
    }

    /**
     * Enroll user in course
     */
    public function enroll(Request $request, string $id)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'required|email|max:255',
            'company' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $course = Course::findOrFail($id);
        
        // Check if course is published
        if (!$course->is_published) {
            return response()->json(['message' => 'Course not available for enrollment'], 404);
        }

        // Get or create user
        $user = $request->user();
        
        // If route is public, user() might be null. Try to get it from token directly.
        if (!$user && $request->bearerToken()) {
            $personalAccessToken = \App\Models\PersonalAccessToken::where('token', hash('sha256', $request->bearerToken()))->first();
            if ($personalAccessToken && (!$personalAccessToken->expires_at || !$personalAccessToken->expires_at->isPast())) {
                $user = $personalAccessToken->tokenable;
            }
        }

        if (!$user) {
            // For non-authenticated users, we'll create a record but they need to register later
            $user = null;
        }

        // Check if user is already enrolled
        if ($user && $course->enrollments()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'You are already enrolled in this course'], 400);
        }

        // Create enrollment
        $enrollmentData = [
            'course_id' => $course->id,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'phone' => $request->phone,
            'email' => $request->email,
            'company' => $request->company,
            'notes' => $request->notes,
            'status' => 'created',
            'payment_status' => 'created',
            'enrolled_at' => now(),
        ];

        if ($user) {
            $enrollmentData['user_id'] = $user->id;
            
            // Update user profile with provided data
            $user->update([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'phone' => $request->phone,
            ]);
        }

        $enrollment = $course->enrollments()->create($enrollmentData);

        return response()->json([
            'message' => 'Successfully enrolled in course',
            'enrollment' => $enrollment,
            'course' => $course->load(['author', 'category']),
        ], 201);
    }

    /**
     * Get the authenticated user's enrolled courses
     */
    public function getUserEnrolledCourses(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $enrollments = \App\Models\CourseEnrollment::with(['course.category', 'course.author'])
            ->where('user_id', $user->id)
            ->orderBy('enrolled_at', 'desc')
            ->get();

        return response()->json([
            'enrollments' => $enrollments,
        ]);
    }

    /**
     * Update course enrollment progress
     */
    public function updateProgress(Request $request, string $id)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'current_step_index' => 'required|integer|min:0',
            'progress_percentage' => 'nullable|integer|min:0|max:100',
        ]);

        $enrollment = \App\Models\CourseEnrollment::where('course_id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$enrollment) {
            return response()->json(['message' => 'Not enrolled in this course'], 403);
        }

        $dataToUpdate = [
            'current_step_index' => $request->current_step_index,
        ];
        
        if ($request->has('progress_percentage')) {
            $dataToUpdate['progress_percentage'] = $request->progress_percentage;
            
            // Mark as completed if 100%
            if ($request->progress_percentage == 100 && $enrollment->status !== 'completed') {
                $dataToUpdate['status'] = 'completed';
                $dataToUpdate['completed_at'] = now();
            }
        }

        // If this is the first progress update, mark as started
        if (!$enrollment->started_at && $request->current_step_index > 0) {
            $dataToUpdate['started_at'] = now();
            if ($enrollment->status === 'enrolled') {
                $dataToUpdate['status'] = 'in_progress';
            }
        }

        $enrollment->update($dataToUpdate);

        return response()->json([
            'message' => 'Progress updated',
            'enrollment' => $enrollment
        ]);
    }

    /**
     * Get questions for a test
     */
    public function getTestQuestions(Request $request, string $id)
    {
        $test = Test::findOrFail($id);

        $allQuestions = \DB::table('test_questions')
            ->where('test_id', $id)
            ->get()
            ->map(function ($q) {
                return [
                    'id'      => $q->id,
                    'question'=> $q->question,
                    'type'    => $q->type,
                    'options' => json_decode($q->options, true),
                    'points'  => $q->points,
                ];
            })
            ->shuffle(); // randomize

        // If total_questions is set and less than all questions, take that many
        if ($test->total_questions && $test->total_questions < $allQuestions->count()) {
            $questions = $allQuestions->take($test->total_questions)->values();
        } else {
            $questions = $allQuestions->values();
        }

        return response()->json([
            'test'      => $test,
            'questions' => $questions,
        ]);
    }

    /**
     * Submit test answers and calculate score
     */
    public function submitTest(Request $request, string $id)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $test = Test::findOrFail($id);

        $request->validate([
            'answers' => 'required|array',
        ]);

        $answers = $request->answers; // ['question_id' => 'selected_answer']

        // Get questions that the user actually answered
        $questionIds = array_keys($answers);
        $questions = \DB::table('test_questions')
            ->whereIn('id', $questionIds)
            ->where('test_id', $id)
            ->get();

        // Calculate expected total points for the subset served to the student
        $poolStats = \DB::table('test_questions')
            ->where('test_id', $id)
            ->selectRaw('count(*) as count, sum(points) as total_points')
            ->first();
            
        $avgPoints = ($poolStats && $poolStats->count > 0) ? $poolStats->total_points / $poolStats->count : 5;
        $questionsToShow = $test->total_questions ?: ($poolStats ? $poolStats->count : 0);
        $totalPoints = $avgPoints * $questionsToShow;

        $earnedPoints = 0;
        $resultDetails = [];

        foreach ($questions as $q) {
            $userAnswer = $answers[$q->id] ?? null;
            $isCorrect = $userAnswer === $q->correct_answer;
            if ($isCorrect) {
                $earnedPoints += $q->points;
            }
            $resultDetails[] = [
                'question_id' => $q->id,
                'question' => $q->question,
                'user_answer' => $userAnswer,
                'correct_answer' => $q->correct_answer,
                'is_correct' => $isCorrect,
                'points' => $q->points,
                'explanation' => $q->explanation ?? null,
            ];
        }

        $scorePercentage = $totalPoints > 0 ? round(($earnedPoints / $totalPoints) * 100) : 0;
        $passed = $scorePercentage >= $test->passing_score;

        // If passed, find enrollment and issue certificate if not already given
        $certificate = null;
        if ($passed) {
            $enrollment = \App\Models\CourseEnrollment::where('course_id', $test->course_id)
                ->where('user_id', $user->id)
                ->first();

            if ($enrollment) {
                // Update enrollment status
                $enrollment->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'final_score' => $scorePercentage,
                    'progress_percentage' => 100,
                ]);

                // Check if certificate already exists
                $existing = Certificate::where('enrollment_id', $enrollment->id)->first();
                if (!$existing) {
                    $certNumber = 'CERT-' . strtoupper(Str::random(8)) . '-' . date('Y');
                    $certificate = Certificate::create([
                        'certificate_number' => $certNumber,
                        'course_id' => $test->course_id,
                        'user_id' => $user->id,
                        'enrollment_id' => $enrollment->id,
                        'pdf_path' => '',
                        'final_score' => $scorePercentage,
                        'issued_at' => now(),
                        'is_valid' => true,
                    ]);
                } else {
                    $certificate = $existing;
                }
            }
        }

        return response()->json([
            'passed' => $passed,
            'score_percentage' => $scorePercentage,
            'earned_points' => $earnedPoints,
            'total_points' => $totalPoints,
            'passing_score' => $test->passing_score,
            'details' => $resultDetails,
            'certificate' => $certificate,
        ]);
    }

    /**
     * Get user certificates
     */
    public function getUserCertificates(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $certificates = Certificate::where('user_id', $user->id)
            ->with(['course', 'user'])
            ->orderBy('issued_at', 'desc')
            ->get();

        return response()->json(['certificates' => $certificates]);
    }

    /**
     * Public certificate verification by certificate number
     */
    public function verifyCertificate(string $number)
    {
        $certificate = Certificate::where('certificate_number', $number)
            ->with(['course', 'user'])
            ->first();

        if (!$certificate) {
            return response()->json(['message' => 'Сертификат не найден'], 404);
        }

        return response()->json([
            'certificate' => [
                'certificate_number' => $certificate->certificate_number,
                'final_score' => $certificate->final_score,
                'issued_at' => $certificate->issued_at,
                'is_valid' => $certificate->is_valid,
                'course' => [
                    'id' => $certificate->course->id,
                    'title' => $certificate->course->title,
                ],
                'user' => [
                    'first_name' => $certificate->user->first_name,
                    'last_name' => $certificate->user->last_name,
                    'username' => $certificate->user->username,
                ],
            ],
        ]);
    }
}
