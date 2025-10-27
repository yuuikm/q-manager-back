<?php

namespace App\Services;

use setasign\Fpdi\Fpdi;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PdfPreviewService
{
    /**
     * Generate a preview PDF with specified number of pages
     *
     * @param string $sourceFilePath Full path to the source PDF file
     * @param string $outputFilePath Full path where the preview PDF should be saved
     * @param int $previewPages Number of pages to include in preview (default: 3)
     * @return bool True if successful, false otherwise
     */
    public function generatePreview(string $sourceFilePath, string $outputFilePath, int $previewPages = 3): bool
    {
        try {
            // Check if source file exists
            if (!file_exists($sourceFilePath)) {
                Log::error('Source PDF file not found', ['source_path' => $sourceFilePath]);
                return false;
            }

            // Create output directory if it doesn't exist
            $outputDir = dirname($outputFilePath);
            if (!is_dir($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            // Initialize FPDI
            $pdf = new Fpdi();
            
            // Get the total number of pages in the source PDF
            $pageCount = $pdf->setSourceFile($sourceFilePath);
            
            // Limit preview pages to the actual number of pages available
            $pagesToExtract = min($previewPages, $pageCount);
            
            if ($pagesToExtract <= 0) {
                Log::error('No pages found in source PDF', ['source_path' => $sourceFilePath]);
                return false;
            }

            // Extract the first N pages
            for ($pageNo = 1; $pageNo <= $pagesToExtract; $pageNo++) {
                // Import the page
                $templateId = $pdf->importPage($pageNo);
                
                // Get the size of the imported page
                $size = $pdf->getTemplateSize($templateId);
                
                // Add a page with the same orientation and size
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                
                // Use the imported page as template
                $pdf->useTemplate($templateId);
            }

            // Save the preview PDF
            $pdf->Output('F', $outputFilePath);
            
            // Verify the file was created successfully
            if (file_exists($outputFilePath) && filesize($outputFilePath) > 0) {
                Log::info('PDF preview generated successfully', [
                    'source_path' => $sourceFilePath,
                    'output_path' => $outputFilePath,
                    'pages_extracted' => $pagesToExtract,
                    'file_size' => filesize($outputFilePath)
                ]);
                return true;
            } else {
                Log::error('Failed to create preview PDF file', [
                    'source_path' => $sourceFilePath,
                    'output_path' => $outputFilePath
                ]);
                return false;
            }

        } catch (\Exception $e) {
            Log::error('Error generating PDF preview', [
                'source_path' => $sourceFilePath,
                'output_path' => $outputFilePath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Generate preview for a document and update the database record
     *
     * @param \App\Models\Document $document The document model
     * @param int $previewPages Number of pages for preview
     * @return bool True if successful, false otherwise
     */
    public function generateDocumentPreview($document, int $previewPages = 3): bool
    {
        try {
            $sourceFilePath = storage_path('app/public/' . $document->file_path);
            
            if (!file_exists($sourceFilePath)) {
                Log::error('Document file not found for preview generation', [
                    'document_id' => $document->id,
                    'file_path' => $document->file_path,
                    'full_path' => $sourceFilePath
                ]);
                return false;
            }

            // Generate preview file name and path
            $previewFileName = 'preview_doc_' . $document->id . '_' . time() . '.pdf';
            $previewFilePath = 'previews/' . $previewFileName;
            $fullPreviewPath = storage_path('app/public/' . $previewFilePath);

            // Generate the preview
            $success = $this->generatePreview($sourceFilePath, $fullPreviewPath, $previewPages);

            if ($success) {
                // Update document with preview file information
                $document->update([
                    'preview_file_path' => $previewFilePath,
                    'preview_file_name' => $previewFileName,
                    'preview_file_size' => filesize($fullPreviewPath),
                ]);

                Log::info('Document preview updated in database', [
                    'document_id' => $document->id,
                    'preview_file_path' => $previewFilePath,
                    'preview_file_size' => filesize($fullPreviewPath)
                ]);

                return true;
            }

            return false;

        } catch (\Exception $e) {
            Log::error('Error generating document preview', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Check if PDF preview generation is available
     * This method checks if the FPDI library is properly installed
     *
     * @return bool True if available, false otherwise
     */
    public function isAvailable(): bool
    {
        return class_exists('setasign\Fpdi\Fpdi');
    }
}
