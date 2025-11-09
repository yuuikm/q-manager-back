<?php

namespace App\Services;

use Smalot\PdfParser\Parser;
use Mpdf\Mpdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PdfParserPreviewService
{
    /**
     * Generate a preview PDF with specified number of pages using PDF Parser + mPDF
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

            // Try PDF Parser + mPDF first
            if ($this->generatePreviewWithPdfParser($sourceFilePath, $outputFilePath, $previewPages)) {
                return true;
            }

            // If that fails, try alternative method
            Log::warning('PDF Parser failed, trying alternative preview method', [
                'source_path' => $sourceFilePath,
                'output_path' => $outputFilePath
            ]);

            return $this->generatePreviewAlternative($sourceFilePath, $outputFilePath, $previewPages);

        } catch (\Exception $e) {
            Log::error('Error generating PDF preview with PDF Parser', [
                'source_path' => $sourceFilePath,
                'output_path' => $outputFilePath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Generate preview using PDF Parser + mPDF library
     */
    private function generatePreviewWithPdfParser(string $sourceFilePath, string $outputFilePath, int $previewPages): bool
    {
        try {
            // Parse the PDF
            $parser = new Parser();
            $pdf = $parser->parseFile($sourceFilePath);
            
            // Get all pages
            $pages = $pdf->getPages();
            $totalPages = count($pages);
            
            if ($totalPages === 0) {
                Log::error('No pages found in source PDF', ['source_path' => $sourceFilePath]);
                return false;
            }

            // Limit preview pages to the actual number of pages available
            $pagesToExtract = min($previewPages, $totalPages);

            // Initialize mPDF
            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'orientation' => 'P',
                'margin_left' => 15,
                'margin_right' => 15,
                'margin_top' => 16,
                'margin_bottom' => 16,
                'margin_header' => 9,
                'margin_footer' => 9,
            ]);

            $mpdf->SetTitle('Document Preview');
            $mpdf->SetAuthor('Q-Manager System');
            $mpdf->SetSubject('PDF Preview');

            // Extract the first N pages
            for ($i = 0; $i < $pagesToExtract; $i++) {
                $page = $pages[$i];
                
                // Get page content
                $text = $page->getText();
                
                // Add page to mPDF
                if ($i > 0) {
                    $mpdf->AddPage();
                }
                
                // Add page content
                $mpdf->WriteHTML('<div style="font-family: Arial, sans-serif; line-height: 1.4;">' . nl2br(htmlspecialchars($text)) . '</div>');
            }

            // Save the preview PDF
            $mpdf->Output($outputFilePath, 'F');
            
            // Verify the file was created successfully
            if (file_exists($outputFilePath) && filesize($outputFilePath) > 0) {
                Log::info('PDF preview generated successfully with PDF Parser + mPDF', [
                    'source_path' => $sourceFilePath,
                    'output_path' => $outputFilePath,
                    'pages_extracted' => $pagesToExtract,
                    'file_size' => filesize($outputFilePath)
                ]);
                return true;
            }

            return false;

        } catch (\Exception $e) {
            Log::warning('PDF Parser + mPDF preview generation failed', [
                'source_path' => $sourceFilePath,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Alternative preview generation method using mPDF
     * Creates a simple PDF with text indicating preview is not available
     */
    private function generatePreviewAlternative(string $sourceFilePath, string $outputFilePath, int $previewPages): bool
    {
        try {
            // Create a simple PDF with mPDF
            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'orientation' => 'P',
                'margin_left' => 15,
                'margin_right' => 15,
                'margin_top' => 16,
                'margin_bottom' => 16,
            ]);

            $mpdf->SetTitle('PDF Document Preview');
            $mpdf->SetAuthor('Q-Manager System');
            $mpdf->SetSubject('PDF Preview');

            $html = '
            <div style="text-align: center; font-family: Arial, sans-serif;">
                <h1 style="color: #333; margin-bottom: 20px;">PDF Document Preview</h1>
                <p style="font-size: 16px; margin-bottom: 10px;">Preview is not available for this PDF file.</p>
                <p style="font-size: 16px; margin-bottom: 20px;">The file may use unsupported compression.</p>
                <div style="background-color: #f5f5f5; padding: 15px; border-radius: 5px; margin: 20px 0;">
                    <p style="margin: 5px 0;"><strong>Original file:</strong> ' . basename($sourceFilePath) . '</p>
                    <p style="margin: 5px 0;"><strong>File size:</strong> ' . number_format(filesize($sourceFilePath)) . ' bytes</p>
                    <p style="margin: 5px 0;"><strong>Upload date:</strong> ' . date('Y-m-d H:i:s', filemtime($sourceFilePath)) . '</p>
                </div>
                <p style="font-size: 12px; color: #666; margin-top: 20px;">
                    Note: Full preview is not available due to PDF compression format.<br>
                    The complete document will be available after purchase.
                </p>
            </div>';

            $mpdf->WriteHTML($html);
            $mpdf->Output($outputFilePath, 'F');
            
            if (file_exists($outputFilePath) && filesize($outputFilePath) > 0) {
                Log::info('Alternative PDF preview generated with mPDF', [
                    'source_path' => $sourceFilePath,
                    'output_path' => $outputFilePath,
                    'file_size' => filesize($outputFilePath)
                ]);
                return true;
            }

            return false;

        } catch (\Exception $e) {
            Log::error('Alternative preview generation failed', [
                'source_path' => $sourceFilePath,
                'error' => $e->getMessage()
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

                Log::info('Document preview updated in database with PDF Parser', [
                    'document_id' => $document->id,
                    'preview_file_path' => $previewFilePath,
                    'preview_file_size' => filesize($fullPreviewPath)
                ]);

                return true;
            }

            return false;

        } catch (\Exception $e) {
            Log::error('Error generating document preview with PDF Parser', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Check if PDF preview generation is available
     * This method checks if both PDF Parser and mPDF libraries are properly installed
     *
     * @return bool True if available, false otherwise
     */
    public function isAvailable(): bool
    {
        return class_exists('Smalot\PdfParser\Parser') && class_exists('Mpdf\Mpdf');
    }

    /**
     * Check if a PDF file can be processed by PDF Parser
     *
     * @param string $filePath Path to the PDF file
     * @return bool True if PDF Parser can process the file, false otherwise
     */
    public function canProcessWithPdfParser(string $filePath): bool
    {
        try {
            if (!file_exists($filePath)) {
                return false;
            }

            $parser = new Parser();
            $pdf = $parser->parseFile($filePath);
            $pages = $pdf->getPages();
            return count($pages) > 0;
        } catch (\Exception $e) {
            Log::debug('PDF cannot be processed with PDF Parser', [
                'file_path' => $filePath,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
