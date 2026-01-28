<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;

class DocumentTypeController extends Controller
{
    /**
     * Get all available document types.
     */
    public function index()
    {
        $types = [
            Document::TYPE_DOCUMENTED_PROCEDURES,
            Document::TYPE_MAIN_PROCESS_MAPS,
            Document::TYPE_SUPPORTING_PROCESS_MAPS,
            Document::TYPE_MANAGEMENT_PROCESS_MAPS,
            Document::TYPE_QUALITY_MANUAL,
            Document::TYPE_PRODUCTION_INSTRUCTIONS,
            Document::TYPE_GMP_MANUAL,
        ];

        return response()->json($types);
    }
}
