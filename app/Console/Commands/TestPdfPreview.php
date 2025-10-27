<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\PdfPreviewService;
use App\Models\Document;

class TestPdfPreview extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:pdf-preview {document_id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test PDF preview generation service';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing PDF Preview Service...');
        
        $pdfPreviewService = new PdfPreviewService();
        
        // Check if service is available
        if (!$pdfPreviewService->isAvailable()) {
            $this->error('PDF Preview Service is not available. FPDI library may not be installed.');
            return 1;
        }
        
        $this->info('✓ PDF Preview Service is available');
        
        // Test with specific document if provided
        $documentId = $this->argument('document_id');
        if ($documentId) {
            $document = Document::find($documentId);
            if (!$document) {
                $this->error("Document with ID {$documentId} not found.");
                return 1;
            }
            
            $this->info("Testing with document: {$document->title}");
            
            // Check if document file exists
            $filePath = storage_path('app/public/' . $document->file_path);
            if (!file_exists($filePath)) {
                $this->error("Document file not found: {$filePath}");
                return 1;
            }
            
            $this->info("✓ Document file exists");
            
            // Test preview generation
            $success = $pdfPreviewService->generateDocumentPreview($document, 3);
            
            if ($success) {
                $this->info('✓ PDF preview generated successfully');
                $this->info("Preview file: {$document->preview_file_path}");
                $this->info("Preview size: " . number_format($document->preview_file_size) . " bytes");
            } else {
                $this->error('✗ Failed to generate PDF preview');
                return 1;
            }
        } else {
            $this->info('No document ID provided. Service availability check completed.');
        }
        
        return 0;
    }
}
