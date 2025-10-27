<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Services\PdfPreviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Models\User;

class AdminController extends Controller
{
    public function getPublicDocument($id)
    {
        $document = Document::with(['creator', 'category'])
            ->where('is_active', true)
            ->findOrFail($id);
        
        return response()->json($document);
    }

    public function getPublicDocuments(Request $request)
    {
        $documents = Document::with(['creator', 'category'])
            ->where('is_active', true)
            ->when($request->category, function ($query, $category) {
                return $query->whereHas('category', function($q) use ($category) {
                    $q->where('name', $category);
                });
            })
            ->when($request->search, function ($query, $search) {
                return $query->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            })
            ->orderBy('created_at', 'desc')
            ->paginate(12);

        return response()->json($documents);
    }

    public function getCategories()
    {
        $categories = DocumentCategory::orderBy('name')->get();
        return response()->json($categories);
    }

    public function uploadDocument(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:500',
            'category' => 'required|string|max:100',
            'price' => 'required|numeric|min:0',
            'preview_pages' => 'required|integer|min:1|max:10',
            'file' => 'required|file|mimes:pdf,doc,docx,txt,rtf,jpg,jpeg,png,gif|max:10240',
        ], [
            'file.required' => 'Файл обязателен для загрузки',
            'file.file' => 'Загруженный файл недействителен',
            'file.mimes' => 'Файл должен быть одного из типов: pdf, doc, docx, txt, rtf, jpg, jpeg, png, gif',
            'file.max' => 'Размер файла не должен превышать 10MB',
        ]);

        if ($validator->fails()) {
            \Log::error('Document upload validation failed:', $validator->errors()->toArray());
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $file = $request->file('file');
            
            
            if (!$file || !$file->isValid()) {
                \Log::error('File upload failed:', [
                    'has_file' => $request->hasFile('file'),
                    'file_valid' => $file ? $file->isValid() : false,
                    'upload_error' => $file ? $file->getError() : 'no file',
                ]);
                return response()->json(['message' => 'File upload failed'], 422);
            }
            
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('documents', $fileName, 'public');
            
            // Create preview file for PDFs (first 3 pages only)
            $previewFilePath = null;
            $previewFileName = null;
            $previewFileSize = null;
            
            // Note: Preview file creation will be done after document creation

            // Handle category - find existing or create new
            $categoryName = $request->category;
            $category = DocumentCategory::where('name', $categoryName)->first();
            
            if (!$category) {
                // Create new category
                $category = DocumentCategory::create([
                    'name' => $categoryName,
                    'slug' => \Str::slug($categoryName),
                ]);
            }

            $documentData = [
                'title' => $request->title,
                'description' => $request->description,
                'price' => $request->price,
                'file_path' => $filePath,
                'file_name' => $file->getClientOriginalName(),
                'file_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
                'preview_file_path' => null,
                'preview_file_name' => null,
                'preview_file_size' => null,
                'created_by' => $request->user()->id,
                'buy_number' => 0,
                'category_id' => $category->id,
            ];
            
            // Only add preview_pages if column exists
            if (\Schema::hasColumn('documents', 'preview_pages')) {
                $documentData['preview_pages'] = $request->preview_pages ?? 3;
            }
            
            $document = Document::create($documentData);

            // Create preview file for PDFs after document creation
            if (strtolower($file->getClientOriginalExtension()) === 'pdf') {
                $pdfPreviewService = new PdfPreviewService();
                
                // Check if PDF preview service is available
                if (!$pdfPreviewService->isAvailable()) {
                    \Log::warning('PDF preview service not available, skipping preview generation', [
                        'document_id' => $document->id
                    ]);
                } else {
                    // Get preview pages count
                    $previewPages = 3;
                    if (\Schema::hasColumn('documents', 'preview_pages') && isset($document->preview_pages)) {
                        $previewPages = $document->preview_pages;
                    } else {
                        $previewPages = $request->preview_pages ?? 3;
                    }
                    
                    // Generate preview using the service
                    $success = $pdfPreviewService->generateDocumentPreview($document, $previewPages);
                    
                    if (!$success) {
                        \Log::error('Failed to generate PDF preview using service', [
                            'document_id' => $document->id,
                            'preview_pages' => $previewPages
                        ]);
                    }
                }
            }

            return response()->json([
                'message' => 'Document uploaded successfully',
                'document' => $document->load(['creator', 'category']),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to upload document',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getDocuments(Request $request)
    {
        $documents = Document::with(['creator', 'category'])
            ->when($request->category, function ($query, $category) {
                return $query->whereHas('category', function($q) use ($category) {
                    $q->where('name', $category);
                });
            })
            ->when($request->search, function ($query, $search) {
                return $query->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            })
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json($documents);
    }

    public function updateDocument(Request $request, $id)
    {
        $document = Document::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:500',
            'category' => 'required|string|max:100',
            'price' => 'required|numeric|min:0',
            'preview_pages' => 'nullable|integer|min:1|max:10',
            'file' => 'nullable|file|mimes:pdf,doc,docx,txt,rtf,jpg,jpeg,png,gif|max:10240',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $updateData = [
            'title' => $request->title,
            'description' => $request->description,
            'price' => $request->price,
            'is_active' => $request->is_active ?? $document->is_active,
        ];
        
        $previewPagesChanged = false;
        $oldPreviewPages = $document->preview_pages;
        
        // Only update preview_pages if provided
        if ($request->has('preview_pages')) {
            $updateData['preview_pages'] = $request->preview_pages;
            $previewPagesChanged = ($oldPreviewPages != $request->preview_pages);
        }
        
        // Handle file upload if provided
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            
            // Delete old file
            if ($document->file_path) {
                Storage::disk('public')->delete($document->file_path);
            }
            
            // Delete old preview file
            if ($document->preview_file_path) {
                Storage::disk('public')->delete($document->preview_file_path);
            }
            
            // Store new file
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('documents', $fileName, 'public');
            
            $updateData['file_path'] = $filePath;
            $updateData['file_name'] = $file->getClientOriginalName();
            $updateData['file_type'] = $file->getClientMimeType();
            $updateData['file_size'] = $file->getSize();
            
            // Reset preview fields (will be regenerated below)
            $updateData['preview_file_path'] = null;
            $updateData['preview_file_name'] = null;
            $updateData['preview_file_size'] = null;
        }
        
        $document->update($updateData);

        // Handle category - find existing or create new
        $categoryName = $request->category;
        $category = DocumentCategory::where('name', $categoryName)->first();
        
        if (!$category) {
            // Create new category
            $category = DocumentCategory::create([
                'name' => $categoryName,
                'slug' => \Str::slug($categoryName),
            ]);
        }

        // Update category relationship
        $document->update(['category_id' => $category->id]);
        
        // Regenerate preview if preview_pages changed or file was uploaded
        if ($previewPagesChanged || $request->hasFile('file')) {
            // Reload document to get updated values
            $document->refresh();
            
            // Only generate preview for PDFs
            $filePathForPreview = $request->hasFile('file') ? $document->file_path : $document->file_path;
            $fullOriginalPath = storage_path('app/public/' . $filePathForPreview);
            
            if (file_exists($fullOriginalPath) && strtolower(pathinfo($fullOriginalPath, PATHINFO_EXTENSION)) === 'pdf') {
                $pdfPreviewService = new PdfPreviewService();
                
                // Check if PDF preview service is available
                if (!$pdfPreviewService->isAvailable()) {
                    \Log::warning('PDF preview service not available, skipping preview generation', [
                        'document_id' => $document->id
                    ]);
                } else {
                    // Delete old preview file if exists
                    if ($document->preview_file_path) {
                        Storage::disk('public')->delete($document->preview_file_path);
                    }
                    
                    // Get preview pages count
                    $previewPages = $document->preview_pages ?? 3;
                    
                    // Generate preview using the service
                    $success = $pdfPreviewService->generateDocumentPreview($document, $previewPages);
                    
                    if (!$success) {
                        \Log::error('Failed to regenerate PDF preview using service', [
                            'document_id' => $document->id,
                            'preview_pages' => $previewPages
                        ]);
                    }
                }
            }
        }

        return response()->json([
            'message' => 'Document updated successfully',
            'document' => $document->load(['creator', 'category']),
        ]);
    }

    public function deleteDocument($id)
    {
        $document = Document::findOrFail($id);

        // Delete the file
        if ($document->file_path) {
            Storage::disk('public')->delete($document->file_path);
        }

        // Delete the document (this will also delete pivot table entries due to cascade)
        $document->delete();

        return response()->json(['message' => 'Document deleted successfully']);
    }

    public function toggleDocumentStatus($id)
    {
        $document = Document::findOrFail($id);
        $document->update(['is_active' => !$document->is_active]);

        return response()->json([
            'message' => 'Document status updated successfully',
            'document' => $document->load(['creator', 'category']),
        ]);
    }

    public function getUsers(Request $request)
    {
        $users = User::when($request->search, function ($query, $search) {
            return $query->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%");
        })
        ->orderBy('created_at', 'desc')
        ->paginate(10);

        return response()->json($users);
    }

    public function updateUser(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $id,
            'role' => 'required|in:user,admin',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user->update([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'role' => $request->role,
        ]);

        return response()->json([
            'message' => 'User updated successfully',
            'user' => $user,
        ]);
    }

    public function deleteUser($id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }

    public function previewDocument($id)
    {
        $document = Document::findOrFail($id);

        // Use preview file if available, otherwise use main file
        $filePath = $document->preview_file_path 
            ? storage_path('app/public/' . $document->preview_file_path)
            : storage_path('app/public/' . $document->file_path);

        if (!file_exists($filePath)) {
            return response()->json(['message' => 'Preview file not found'], 404);
        }

        // Check if it's a PDF file
        $fileExtension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        if ($fileExtension !== 'pdf') {
            return response()->json(['message' => 'Preview is only available for PDF files'], 400);
        }

        $fileName = $document->preview_file_name ?: $document->file_name;
        
        // Log preview access
        \Log::info('Document preview accessed', [
            'document_id' => $id,
            'using_preview_file' => !is_null($document->preview_file_path),
            'file_path' => $filePath,
            'file_size' => filesize($filePath)
        ]);

        // Return the PDF file for preview
        return response()->file($filePath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $fileName . '"',
        ]);
    }

    public function downloadDocument(Request $request, $id)
    {
        $document = Document::findOrFail($id);

        if (!$document->file_path) {
            return response()->json(['message' => 'File not found'], 404);
        }

        // Check if user is authenticated
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Authentication required'], 401);
        }

        // Check if document is free or user has purchased it
        if ($document->price > 0 && !$document->isPurchasedBy($user->id)) {
            return response()->json(['message' => 'Document must be purchased before download'], 403);
        }

        $filePath = storage_path('app/public/' . $document->file_path);

        if (!file_exists($filePath)) {
            return response()->json(['message' => 'File not found on disk'], 404);
        }

        // Return the file for download
        return response()->download($filePath, $document->file_name);
    }

    public function purchaseDocument(Request $request, $id)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'required|email|max:255',
            'company' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $document = Document::findOrFail($id);
        $user = $request->user();

        // Check if document is active
        if (!$document->is_active) {
            return response()->json(['message' => 'Document not available for purchase'], 404);
        }

        // Check if user already purchased this document (if authenticated)
        if ($user && $document->isPurchasedBy($user->id)) {
            return response()->json(['message' => 'Document already purchased'], 400);
        }

        // Create purchase record
        $purchaseData = [
            'document_id' => $document->id,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'phone' => $request->phone,
            'email' => $request->email,
            'company' => $request->company,
            'notes' => $request->notes,
            'price_paid' => $document->price,
            'status' => 'completed',
            'purchased_at' => now(),
        ];

        if ($user) {
            $purchaseData['user_id'] = $user->id;
            
            // Update user profile with provided data
            $user->update([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'phone' => $request->phone,
            ]);
        }

        $purchase = \App\Models\DocumentPurchase::create($purchaseData);

        // Update buy count
        $document->increment('buy_number');

        return response()->json([
            'message' => 'Document purchased successfully',
            'purchase' => $purchase
        ]);
    }

    public function getUserPurchasedDocuments(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['message' => 'Authentication required'], 401);
        }

        $purchases = \App\Models\DocumentPurchase::with(['document.category'])
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->orderBy('purchased_at', 'desc')
            ->get();

        return response()->json([
            'purchases' => $purchases,
            'user' => $user
        ]);
    }
}
