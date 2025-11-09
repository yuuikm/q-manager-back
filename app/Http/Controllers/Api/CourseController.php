<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\Test;
use App\Models\CourseCategory;
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
            'content' => $request->content,
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
            'content' => $request->content,
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
            'status' => 'pending',
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
}
