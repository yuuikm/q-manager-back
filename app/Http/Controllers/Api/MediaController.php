<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\Document;
use App\Models\InternalDocument;
use App\Models\ManagerHelp;
use App\Models\News;
use App\Models\Slider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    private array $directories = [
        'documents',
        'previews',
        'iso-documents',
        'courses/images',
        'courses/certificates',
        'course-materials',
        'news/images',
        'news/featured',
        'sliders',
        'manager_help/documents',
    ];

    public function index(Request $request)
    {
        $disk = Storage::disk('public');
        $allFiles = [];

        foreach ($this->directories as $directory) {
            if (!$disk->exists($directory)) {
                continue;
            }
            $files = $disk->files($directory);
            foreach ($files as $filePath) {
                $allFiles[] = $this->buildFileInfo($filePath);
            }
        }

        if ($request->filled('search')) {
            $search = strtolower($request->search);
            $allFiles = array_values(array_filter(
                $allFiles,
                fn($f) => str_contains(strtolower($f['name']), $search)
            ));
        }

        if ($request->filled('type')) {
            $allFiles = array_values(array_filter(
                $allFiles,
                fn($f) => $f['type'] === $request->type
            ));
        }

        if ($request->filled('folder')) {
            $allFiles = array_values(array_filter(
                $allFiles,
                fn($f) => $f['folder'] === $request->folder
            ));
        }

        if ($request->filled('used')) {
            $isUsed = filter_var($request->used, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($isUsed !== null) {
                $allFiles = array_values(array_filter(
                    $allFiles,
                    fn($f) => $f['is_used'] === $isUsed
                ));
            }
        }

        $unusedCount = count(array_filter($allFiles, fn($f) => !$f['is_used']));

        return response()->json([
            'files' => $allFiles,
            'total' => count($allFiles),
            'unused_count' => $unusedCount,
            'directories' => $this->directories,
        ]);
    }

    public function destroy(Request $request)
    {
        $request->validate([
            'path' => 'required|string',
        ]);

        $path = $request->path;

        if (!Storage::disk('public')->exists($path)) {
            return response()->json(['message' => 'Файл не найден'], 404);
        }

        Storage::disk('public')->delete($path);

        return response()->json(['message' => 'Файл успешно удалён']);
    }

    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'paths' => 'required|array|min:1',
            'paths.*' => 'required|string',
        ]);

        $disk = Storage::disk('public');
        $deleted = [];
        $failed = [];

        foreach ($request->paths as $path) {
            if ($disk->exists($path)) {
                $disk->delete($path);
                $deleted[] = $path;
            } else {
                $failed[] = $path;
            }
        }

        return response()->json([
            'deleted' => $deleted,
            'failed' => $failed,
            'message' => 'Удалено файлов: ' . count($deleted),
        ]);
    }

    private function buildFileInfo(string $filePath): array
    {
        $disk = Storage::disk('public');

        $size = $disk->size($filePath);
        $lastModified = $disk->lastModified($filePath);
        $mimeType = $disk->mimeType($filePath) ?: 'application/octet-stream';

        return [
            'path' => $filePath,
            'name' => basename($filePath),
            'folder' => dirname($filePath),
            'size' => $size,
            'last_modified' => date('Y-m-d H:i:s', $lastModified),
            'mime_type' => $mimeType,
            'type' => $this->resolveFileType($mimeType),
            'url' => asset('storage/' . $filePath),
            'is_used' => $this->isFileUsed($filePath),
        ];
    }

    private function resolveFileType(string $mimeType): string
    {
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }

        if ($mimeType === 'application/pdf') {
            return 'pdf';
        }

        if (in_array($mimeType, [
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])) {
            return 'word';
        }

        if (in_array($mimeType, [
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])) {
            return 'excel';
        }

        if (str_starts_with($mimeType, 'video/')) {
            return 'video';
        }

        return 'other';
    }

    private function isFileUsed(string $filePath): bool
    {
        if (Document::where('file_path', $filePath)->orWhere('preview_file_path', $filePath)->exists()) {
            return true;
        }

        if (News::where('image_path', $filePath)->orWhere('featured_image', $filePath)->exists()) {
            return true;
        }

        if (Course::where('featured_image', $filePath)->orWhere('certificate_template', $filePath)->exists()) {
            return true;
        }

        if (CourseMaterial::where('file_path', $filePath)->exists()) {
            return true;
        }

        if (InternalDocument::where('file_path', $filePath)->exists()) {
            return true;
        }

        if (ManagerHelp::where('file_path', $filePath)->exists()) {
            return true;
        }

        if (Slider::where('image_path', $filePath)->exists()) {
            return true;
        }

        return false;
    }
}
