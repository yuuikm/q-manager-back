<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\PdfPreviewService;
use App\Models\Document;
use Illuminate\Support\Facades\Storage;

class RegeneratePreviews extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'documents:regenerate-previews {--limit=10 : Maximum number of documents to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Regenerate PDF previews for existing documents using the new service';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Regenerating PDF previews...');
        
        $pdfPreviewService = new PdfPreviewService();
        
        // Check if service is available
        if (!$pdfPreviewService->isAvailable()) {
            $this->error('PDF Preview Service is not available. FPDI library may not be installed.');
            return 1;
        }
        
        $this->info('✓ PDF Preview Service is available');
        
        $limit = $this->option('limit');
        
        // Get PDF documents that need preview regeneration
        $documents = Document::where('file_type', 'application/pdf')
            ->whereNotNull('file_path')
            ->limit($limit)
            ->get();
        
        if ($documents->isEmpty()) {
            $this->info('No PDF documents found to process.');
            return 0;
        }
        
        $this->info("Found {$documents->count()} PDF documents to process");
        
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($documents as $document) {
            $this->info("Processing document: {$document->title} (ID: {$document->id})");
            
            // Check if file exists
            $filePath = storage_path('app/public/' . $document->file_path);
            if (!file_exists($filePath)) {
                $this->error("  ✗ File not found: {$filePath}");
                $errorCount++;
                continue;
            }
            
            // Delete existing preview if it exists
            if ($document->preview_file_path) {
                Storage::disk('public')->delete($document->preview_file_path);
                $this->info("  ✓ Deleted old preview file");
            }
            
            // Generate new preview
            $previewPages = $document->preview_pages ?? 3;
            $success = $pdfPreviewService->generateDocumentPreview($document, $previewPages);
            
            if ($success) {
                $this->info("  ✓ Preview generated successfully ({$previewPages} pages, " . number_format($document->preview_file_size) . " bytes)");
                $successCount++;
            } else {
                $this->error("  ✗ Failed to generate preview");
                $errorCount++;
            }
        }
        
        $this->info("\nSummary:");
        $this->info("✓ Successfully processed: {$successCount}");
        $this->info("✗ Errors: {$errorCount}");
        
        return 0;
    }
}
