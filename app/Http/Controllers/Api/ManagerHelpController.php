<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ManagerHelp;
use App\Models\ManagerHelpCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ManagerHelpController extends Controller
{
    public function index(Request $request)
    {
        $query = ManagerHelp::with('category');

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->has('author_id') && $request->author_id) {
            $query->where('created_by', $request->author_id);
        }

        if ($request->has('start_date') && $request->start_date) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->has('end_date') && $request->end_date) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $helps = $query->orderBy('created_at', 'desc')->paginate(15);
        return response()->json($helps);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'nullable|string|max:255',
            'category_id' => 'nullable|exists:manager_help_categories,id',
            'description' => 'nullable|string',
            'document' => 'nullable|file|mimes:pdf,doc,docx|max:10240', // 10MB
            'youtube_url' => 'nullable|url|max:255',
            'is_active' => 'boolean',
        ]);

        $data = $request->only(['title', 'description', 'youtube_url', 'is_active']);
        
        // Handle category
        if ($request->has('category') && $request->category) {
            $categoryName = $request->category;
            $category = ManagerHelpCategory::where('name', $categoryName)->first();
            
            if (!$category) {
                // Create new category
                $category = ManagerHelpCategory::create([
                    'name' => $categoryName,
                    'slug' => Str::slug($categoryName),
                ]);
            }
            $data['category_id'] = $category->id;
        } elseif ($request->has('category_id')) {
            $data['category_id'] = $request->category_id;
        }

        $data['is_active'] = $request->has('is_active') ? $request->boolean('is_active') : true;
        $data['slug'] = Str::slug($request->title);

        if ($request->hasFile('document')) {
            $file = $request->file('document');
            $data['file_path'] = $file->store('manager_help/documents', 'public');
            $data['file_name'] = $file->getClientOriginalName();
        }

        $help = ManagerHelp::create($data);
        return response()->json($help->load('category'), 201);
    }

    public function show($id)
    {
        $help = ManagerHelp::with('category')->findOrFail($id);
        return response()->json($help);
    }

    public function update(Request $request, $id)
    {
        $help = ManagerHelp::findOrFail($id);

        $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'nullable|string|max:255',
            'category_id' => 'nullable|exists:manager_help_categories,id',
            'description' => 'nullable|string',
            'document' => 'nullable|file|mimes:pdf,doc,docx|max:10240',
            'youtube_url' => 'nullable|url|max:255',
            'is_active' => 'boolean',
        ]);

        $data = $request->only(['title', 'description', 'youtube_url', 'is_active']);

        // Handle category
        if ($request->has('category') && $request->category) {
            $categoryName = $request->category;
            $category = ManagerHelpCategory::where('name', $categoryName)->first();
            
            if (!$category) {
                $category = ManagerHelpCategory::create([
                    'name' => $categoryName,
                    'slug' => Str::slug($categoryName),
                ]);
            }
            $data['category_id'] = $category->id;
        } elseif ($request->has('category_id')) {
            $data['category_id'] = $request->category_id;
        }
        $data['slug'] = Str::slug($request->title);

        if ($request->hasFile('document')) {
            // Delete old file
            if ($help->file_path) {
                Storage::disk('public')->delete($help->file_path);
            }
            $file = $request->file('document');
            $data['file_path'] = $file->store('manager_help/documents', 'public');
            $data['file_name'] = $file->getClientOriginalName();
        }

        $help->update($data);
        return response()->json($help->load('category'));
    }

    public function destroy($id)
    {
        $help = ManagerHelp::findOrFail($id);
        
        if ($help->file_path) {
            Storage::disk('public')->delete($help->file_path);
        }

        $help->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }

    public function toggleStatus($id)
    {
        $help = ManagerHelp::findOrFail($id);
        $help->update(['is_active' => !$help->is_active]);

        return response()->json([
            'message' => 'Status updated successfully',
            'help' => $help->load('category'),
        ]);
    }
}
