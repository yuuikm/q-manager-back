<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CourseMaterial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class CourseMaterialController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = CourseMaterial::with(['course', 'author']);

        if ($request->has('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        $materials = $query->orderBy('sort_order')->get();

        return response()->json($materials);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'course_id' => 'required|exists:courses,id',
            'type' => 'required|in:video,pdf,doc,link,text',
            'file' => 'nullable|file|max:10240', // 10MB max
            'external_url' => 'nullable|url',
            'content' => 'nullable|string',
            'duration_minutes' => 'nullable|integer|min:1',
            'sort_order' => 'nullable|integer|min:0',
            'is_required' => 'nullable|in:true,false,1,0,"true","false"',
            'is_active' => 'nullable|in:true,false,1,0,"true","false"',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        $data = [
            'title' => $request->title,
            'description' => $request->description,
            'course_id' => $request->course_id,
            'type' => $request->type,
            'external_url' => $request->external_url,
            'content' => $request->content,
            'duration_minutes' => $request->duration_minutes,
            'sort_order' => $request->sort_order ?? 0,
            'is_required' => $this->convertToBoolean($request->is_required) ?? false,
            'is_active' => $this->convertToBoolean($request->is_active) ?? true,
            'created_by' => $user->id,
        ];

        // Handle file upload
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $filePath = $file->store('course-materials', 'public');
            
            $data['file_path'] = $filePath;
            $data['file_name'] = $file->getClientOriginalName();
            $data['file_type'] = $file->getMimeType();
            $data['file_size'] = $file->getSize();
        }

        $material = CourseMaterial::create($data);
        $material->load(['course', 'author']);

        return response()->json([
            'message' => 'Course material created successfully',
            'material' => $material,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $material = CourseMaterial::with(['course', 'author'])->findOrFail($id);
        return response()->json($material);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $material = CourseMaterial::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:video,pdf,doc,link,text',
            'file' => 'nullable|file|max:10240', // 10MB max
            'external_url' => 'nullable|url',
            'content' => 'nullable|string',
            'duration_minutes' => 'nullable|integer|min:1',
            'sort_order' => 'nullable|integer|min:0',
            'is_required' => 'nullable|in:true,false,1,0,"true","false"',
            'is_active' => 'nullable|in:true,false,1,0,"true","false"',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = [
            'title' => $request->title,
            'description' => $request->description,
            'type' => $request->type,
            'external_url' => $request->external_url,
            'content' => $request->content,
            'duration_minutes' => $request->duration_minutes,
            'sort_order' => $request->sort_order ?? $material->sort_order,
            'is_required' => $request->has('is_required') ? $this->convertToBoolean($request->is_required) : $material->is_required,
            'is_active' => $request->has('is_active') ? $this->convertToBoolean($request->is_active) : $material->is_active,
        ];

        // Handle file upload
        if ($request->hasFile('file')) {
            // Delete old file
            if ($material->file_path) {
                Storage::disk('public')->delete($material->file_path);
            }

            $file = $request->file('file');
            $filePath = $file->store('course-materials', 'public');
            
            $data['file_path'] = $filePath;
            $data['file_name'] = $file->getClientOriginalName();
            $data['file_type'] = $file->getMimeType();
            $data['file_size'] = $file->getSize();
        }

        $material->update($data);
        $material->load(['course', 'author']);

        return response()->json([
            'message' => 'Course material updated successfully',
            'material' => $material,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $material = CourseMaterial::findOrFail($id);

        // Delete file if exists
        if ($material->file_path) {
            Storage::disk('public')->delete($material->file_path);
        }

        $material->delete();

        return response()->json(['message' => 'Course material deleted successfully']);
    }

    /**
     * Convert string representation of boolean to actual boolean
     */
    private function convertToBoolean($value)
    {
        if (is_bool($value)) {
            return $value;
        }
        
        if (is_string($value)) {
            return in_array(strtolower($value), ['true', '1', 'yes', 'on']);
        }
        
        if (is_numeric($value)) {
            return (bool) $value;
        }
        
        return false;
    }
}
