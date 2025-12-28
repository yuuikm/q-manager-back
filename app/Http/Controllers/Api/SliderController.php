<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Slider;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SliderController extends Controller
{
    /**
     * Public index - only active slides
     */
    public function index()
    {
        $sliders = Slider::where('is_active', true)
            ->orderBy('order', 'asc')
            ->get();
        return response()->json($sliders);
    }

    /**
     * Admin index - all slides paginated
     */
    public function adminIndex(Request $request)
    {
        $query = Slider::with('author');

        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $sliders = $query->orderBy('order', 'asc')
            ->orderBy('created_at', 'desc')
            ->paginate(15);
        return response()->json($sliders);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120',
            'link_url' => 'nullable|string|max:500',
            'order' => 'nullable|integer',
            'is_active' => 'boolean',
        ]);

        $user = $request->user();
        if (!$user) {
            $user = User::where('role', 'admin')->first();
            if (!$user) {
                return response()->json(['error' => 'Admin user not found'], 500);
            }
        }

        $data = $request->only(['title', 'description', 'link_url', 'order']);
        $data['created_by'] = $user->id;
        $data['is_active'] = $request->has('is_active') ? $request->boolean('is_active') : true;

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('sliders', 'public');
        }

        $slider = Slider::create($data);
        return response()->json($slider->load('author'), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $slider = Slider::with('author')->findOrFail($id);
        return response()->json($slider);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $slider = Slider::findOrFail($id);

        $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
            'link_url' => 'nullable|string|max:500',
            'order' => 'nullable|integer',
            'is_active' => 'boolean',
        ]);

        $data = $request->only(['title', 'description', 'link_url', 'order']);
        
        if ($request->has('is_active')) {
            $data['is_active'] = $request->boolean('is_active');
        }

        if ($request->hasFile('image')) {
            if ($slider->image_path) {
                Storage::disk('public')->delete($slider->image_path);
            }
            $data['image_path'] = $request->file('image')->store('sliders', 'public');
        }

        $slider->update($data);
        return response()->json($slider->load('author'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $slider = Slider::findOrFail($id);
        if ($slider->image_path) {
            Storage::disk('public')->delete($slider->image_path);
        }
        $slider->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }

    /**
     * Toggle active status.
     */
    public function toggleStatus($id)
    {
        $slider = Slider::findOrFail($id);
        $slider->update(['is_active' => !$slider->is_active]);

        return response()->json([
            'message' => 'Status updated successfully',
            'slider' => $slider->load('author'),
        ]);
    }
}
