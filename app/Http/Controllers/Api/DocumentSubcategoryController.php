<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DocumentSubcategory;
use App\Models\DocumentCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DocumentSubcategoryController extends Controller
{
    /**
     * Display a listing of subcategories.
     */
    public function index(Request $request)
    {
        $query = DocumentSubcategory::with('category');

        // Filter by category if provided
        if ($request->has('category_id') && $request->category_id) {
            $query->where('category_id', $request->category_id);
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $subcategories = $query->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc')
            ->get();

        return response()->json($subcategories);
    }

    /**
     * Get subcategories by category ID.
     */
    public function byCategory($categoryId)
    {
        $subcategories = DocumentSubcategory::where('category_id', $categoryId)
            ->where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc')
            ->get();

        return response()->json($subcategories);
    }

    /**
     * Store a newly created subcategory.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:document_categories,id',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ]);

        $data = $request->only(['name', 'category_id', 'description', 'is_active', 'sort_order']);
        $data['slug'] = Str::slug($request->name);

        $subcategory = DocumentSubcategory::create($data);
        $subcategory->load('category');

        return response()->json($subcategory, 201);
    }

    /**
     * Display the specified subcategory.
     */
    public function show($id)
    {
        $subcategory = DocumentSubcategory::with('category')->findOrFail($id);
        return response()->json($subcategory);
    }

    /**
     * Update the specified subcategory.
     */
    public function update(Request $request, $id)
    {
        $subcategory = DocumentSubcategory::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:document_categories,id',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ]);

        $data = $request->only(['name', 'category_id', 'description', 'is_active', 'sort_order']);
        $data['slug'] = Str::slug($request->name);

        $subcategory->update($data);
        $subcategory->load('category');

        return response()->json($subcategory);
    }

    /**
     * Remove the specified subcategory.
     */
    public function destroy($id)
    {
        $subcategory = DocumentSubcategory::findOrFail($id);
        $subcategory->delete();

        return response()->json(['message' => 'Subcategory deleted successfully']);
    }

    /**
     * Find or create subcategory by name and category.
     */
    public function findOrCreate(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:document_categories,id',
        ]);

        $subcategory = DocumentSubcategory::firstOrCreate(
            [
                'name' => $request->name,
                'category_id' => $request->category_id,
            ],
            [
                'slug' => Str::slug($request->name),
                'is_active' => true,
                'sort_order' => 0,
            ]
        );

        $subcategory->load('category');

        return response()->json($subcategory);
    }

    /**
     * Get all active subcategories for public access (with document counts).
     */
    public function publicIndex()
    {
        $subcategories = DocumentSubcategory::with('category')
            ->where('is_active', true)
            ->withCount(['documents' => function ($query) {
                $query->where('is_active', true);
            }])
            ->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc')
            ->get();

        return response()->json($subcategories);
    }

    /**
     * Get subcategory by slug for public access.
     */
    public function showBySlug($slug)
    {
        $subcategory = DocumentSubcategory::with('category')
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        return response()->json($subcategory);
    }
}
