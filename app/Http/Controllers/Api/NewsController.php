<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\News;
use App\Models\NewsComment;
use App\Models\NewsLike;
use App\Models\NewsCategory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class NewsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = News::with(['author', 'comments', 'likes', 'category']);

        // For public access, only show published news by default
        if (!$request->has('published')) {
            $query->where('is_published', true);
        } else {
            $query->where('is_published', $request->boolean('published'));
        }

        // Filter by featured status
        if ($request->has('featured')) {
            $query->where('is_featured', $request->boolean('featured'));
        }

        // Filter by category
        if ($request->has('category')) {
            $query->whereHas('category', function($q) use ($request) {
                $q->where('name', $request->get('category'));
            });
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

        $news = $query->orderBy('created_at', 'desc')->paginate(15);
        return response()->json($news);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Debug authentication
        Log::info('News store - Auth check:', [
            'user_id' => auth()->id(),
            'user' => auth()->user(),
            'token' => $request->bearerToken(),
        ]);

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'video_link' => 'nullable|url|max:500',
            'content' => 'nullable|string',
            'category' => 'nullable|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'featured_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'is_published' => 'boolean',
            'is_featured' => 'boolean',
            'published_at' => 'nullable|date',
        ]);

        $user = $request->user();
        if (!$user) {
            $user = User::where('role', 'admin')->first();
            if (!$user) {
                return response()->json(['error' => 'Admin user not found'], 500);
            }
        }

        $data = [
            'title' => $request->title,
            'slug' => Str::slug($request->title),
            'description' => $request->description,
            'video_link' => $request->video_link,
            'content' => $request->input('content'),
            'is_published' => $request->is_published ?? false,
            'is_featured' => $request->is_featured ?? false,
            'published_at' => $request->published_at,
            'created_by' => $user->id,
        ];

        // Debug the data being inserted
        Log::info('News data to be inserted:', $data);

        // Handle image upload
        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('news/images', 'public');
        }

        if ($request->hasFile('featured_image')) {
            $data['featured_image'] = $request->file('featured_image')->store('news/featured', 'public');
        }

        // Handle category if provided
        if ($request->has('category') && $request->category) {
            $categoryName = $request->category;
            $category = NewsCategory::where('name', $categoryName)->first();
            
            if (!$category) {
                // Create new category
                $category = NewsCategory::create([
                    'name' => $categoryName,
                    'slug' => Str::slug($categoryName),
                ]);
            }

            $data['category_id'] = $category->id;
        }

        $news = News::create($data);

        $news->load(['author', 'comments', 'likes', 'category']);

        return response()->json($news, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $news = News::with(['author', 'category'])->findOrFail($id);
        
        // Only show published news for public access
        if (!$news->is_published) {
            return response()->json(['message' => 'News not found'], 404);
        }
        
        // Increment view count
        $news->increment('views_count');
        
        return response()->json($news);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $news = News::findOrFail($id);

        $request->validate([
            'title' => ['required', 'string', 'max:255', Rule::unique('news', 'title')->ignore($news->id)],
            'description' => 'nullable|string',
            'video_link' => 'nullable|url|max:500',
            'content' => 'nullable|string',
            'category' => 'nullable|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'featured_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'is_published' => 'boolean',
            'is_featured' => 'boolean',
            'published_at' => 'nullable|date',
        ]);

        $data = [
            'title' => $request->title,
            'slug' => Str::slug($request->title),
            'description' => $request->description,
            'video_link' => $request->video_link,
            'content' => $request->input('content'),
            'is_published' => $request->is_published ?? $news->is_published,
            'is_featured' => $request->is_featured ?? $news->is_featured,
            'published_at' => $request->published_at ?? $news->published_at,
        ];

        // Debug the data being updated
        Log::info('News data to be updated:', $data);

        // Handle image upload
        if ($request->hasFile('image')) {
            // Delete old image
            if ($news->image_path) {
                Storage::disk('public')->delete($news->image_path);
            }
            $data['image_path'] = $request->file('image')->store('news/images', 'public');
        }

        if ($request->hasFile('featured_image')) {
            // Delete old featured image
            if ($news->featured_image) {
                Storage::disk('public')->delete($news->featured_image);
            }
            $data['featured_image'] = $request->file('featured_image')->store('news/featured', 'public');
        }

        // Handle category if provided
        if ($request->has('category')) {
            if ($request->category) {
                $categoryName = $request->category;
                $category = NewsCategory::where('name', $categoryName)->first();
                
                if (!$category) {
                    // Create new category
                    $category = NewsCategory::create([
                        'name' => $categoryName,
                        'slug' => Str::slug($categoryName),
                    ]);
                }

                $data['category_id'] = $category->id;
            } else {
                // Remove category if empty
                $data['category_id'] = null;
            }
        }

        $news->update($data);

        $news->load(['author', 'comments', 'likes', 'category']);

        return response()->json($news);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $news = News::findOrFail($id);

        // Delete associated images
        if ($news->image_path) {
            Storage::disk('public')->delete($news->image_path);
        }
        if ($news->featured_image) {
            Storage::disk('public')->delete($news->featured_image);
        }

        // Delete the news (this will also delete pivot table entries due to cascade)
        $news->delete();
        return response()->json(['message' => 'News deleted successfully']);
    }

    /**
     * Toggle like for news
     */
    public function toggleLike(Request $request, string $id)
    {
        $news = News::findOrFail($id);
        $user = auth()->user();

        $like = NewsLike::where('news_id', $news->id)
                       ->where('user_id', auth()->id())
                       ->first();

        if ($like) {
            $like->delete();
            $news->decrement('likes_count');
            $liked = false;
        } else {
            NewsLike::create([
                'news_id' => $news->id,
                'user_id' => auth()->id(),
            ]);
            $news->increment('likes_count');
            $liked = true;
        }

        return response()->json([
            'liked' => $liked,
            'likes_count' => $news->fresh()->likes_count
        ]);
    }

    /**
     * Add comment to news
     */
    public function addComment(Request $request, string $id)
    {
        $request->validate([
            'content' => 'required|string|max:1000',
        ]);

        $news = News::findOrFail($id);
        $user = auth()->user();

        $comment = NewsComment::create([
            'news_id' => $news->id,
            'user_id' => auth()->id(),
            'content' => $request->input('content'),
        ]);

        $news->increment('comments_count');

        return response()->json([
            'message' => 'Comment added successfully',
            'comment' => $comment->load('user')
        ], 201);
    }

    public function togglePublishStatus($id)
    {
        $news = News::findOrFail($id);
        $news->update(['is_published' => !$news->is_published]);

        return response()->json([
            'message' => 'News publish status updated successfully',
            'news' => $news->load(['author', 'category']),
        ]);
    }
}
