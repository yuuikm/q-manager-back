<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Test;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\IOFactory;

class TestController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Test::with(['course', 'author']);

        // Filter by course
        if ($request->has('course_id')) {
            $query->where('course_id', $request->get('course_id'));
        }

        // Filter by active status
        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        $tests = $query->orderBy('created_at', 'desc')->paginate(15);
        return response()->json($tests);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'course_id' => 'required|exists:courses,id',
            'time_limit_minutes' => 'required|integer|min:1|max:300',
            'passing_score' => 'required|integer|min:1|max:100',
            'max_attempts' => 'required|integer|min:1|max:10',
            'is_active' => 'boolean',
            'questions' => 'required|array|min:1',
            'questions.*.question' => 'required|string|max:1000',
            'questions.*.type' => 'required|in:multiple_choice,single_choice,true_false,text',
            'questions.*.options' => 'required_if:questions.*.type,multiple_choice,single_choice|array|min:2',
            'questions.*.options.*' => 'required|string|max:500',
            'questions.*.correct_answer' => 'required|string|max:500',
            'questions.*.points' => 'required|integer|min:1|max:100',
            'questions.*.explanation' => 'nullable|string|max:1000',
        ]);

        $test = Test::create([
            'title' => $request->title,
            'description' => $request->description,
            'course_id' => $request->course_id,
            'time_limit_minutes' => $request->time_limit_minutes,
            'passing_score' => $request->passing_score,
            'max_attempts' => $request->max_attempts,
            'is_active' => $request->is_active ?? true,
            'questions' => $request->questions,
            'created_by' => auth()->id(),
        ]);

        $test->load(['course', 'author']);
        return response()->json($test, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $test = Test::with(['course', 'author'])->findOrFail($id);
        return response()->json($test);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $test = Test::findOrFail($id);

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'course_id' => 'required|exists:courses,id',
            'time_limit_minutes' => 'required|integer|min:1|max:300',
            'passing_score' => 'required|integer|min:1|max:100',
            'max_attempts' => 'required|integer|min:1|max:10',
            'is_active' => 'boolean',
            'questions' => 'required|array|min:1',
            'questions.*.question' => 'required|string|max:1000',
            'questions.*.type' => 'required|in:multiple_choice,single_choice,true_false,text',
            'questions.*.options' => 'required_if:questions.*.type,multiple_choice,single_choice|array|min:2',
            'questions.*.options.*' => 'required|string|max:500',
            'questions.*.correct_answer' => 'required|string|max:500',
            'questions.*.points' => 'required|integer|min:1|max:100',
            'questions.*.explanation' => 'nullable|string|max:1000',
        ]);

        $test->update([
            'title' => $request->title,
            'description' => $request->description,
            'course_id' => $request->course_id,
            'time_limit_minutes' => $request->time_limit_minutes,
            'passing_score' => $request->passing_score,
            'max_attempts' => $request->max_attempts,
            'is_active' => $request->is_active ?? $test->is_active,
            'questions' => $request->questions,
        ]);

        $test->load(['course', 'author']);
        return response()->json($test);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $test = Test::findOrFail($id);
        $test->delete();
        return response()->json(['message' => 'Test deleted successfully']);
    }

    /**
     * Get tests for a specific course
     */
    public function getCourseTests(string $courseId)
    {
        $course = Course::findOrFail($courseId);
        $tests = $course->tests()->with('author')->get();
        return response()->json($tests);
    }

    /**
     * Duplicate a test
     */
    public function duplicate(string $id)
    {
        $originalTest = Test::findOrFail($id);
        
        $newTest = Test::create([
            'title' => $originalTest->title . ' (Copy)',
            'description' => $originalTest->description,
            'course_id' => $originalTest->course_id,
            'time_limit_minutes' => $originalTest->time_limit_minutes,
            'passing_score' => $originalTest->passing_score,
            'max_attempts' => $originalTest->max_attempts,
            'is_active' => false, // Start as inactive
            'questions' => $originalTest->questions,
            'created_by' => auth()->id(),
        ]);

        $newTest->load(['course', 'author']);
        return response()->json($newTest, 201);
    }

    /**
     * Parse Excel file and extract test questions
     */
    public function parseExcel(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls|max:10240', // 10MB max
            'course_id' => 'required|exists:courses,id',
        ]);

        try {
            $file = $request->file('excel_file');
            $courseId = $request->course_id;

            // Load the Excel file
            $spreadsheet = IOFactory::load($file->getPathname());
            $worksheet = $spreadsheet->getActiveSheet();
            $highestRow = $worksheet->getHighestRow();

            $questions = [];
            $currentQuestion = null;
            $questionNumber = 0;

            // Parse the Excel file row by row
            for ($row = 1; $row <= $highestRow; $row++) {
                $cellA = $worksheet->getCell('A' . $row)->getValue();
                $cellB = $worksheet->getCell('B' . $row)->getValue();
                $cellC = $worksheet->getCell('C' . $row)->getValue();

                // Skip empty rows
                if (empty($cellA) && empty($cellB) && empty($cellC)) {
                    continue;
                }

                // Check if this is a question (usually starts with a number or contains question text)
                if ($this->isQuestionRow($cellA, $cellB)) {
                    // Save previous question if exists
                    if ($currentQuestion) {
                        $questions[] = $currentQuestion;
                    }

                    // Start new question
                    $questionNumber++;
                    $currentQuestion = [
                        'question' => trim($cellA . ' ' . $cellB),
                        'type' => 'single_choice', // Default type
                        'options' => [],
                        'correct_answer' => '',
                        'points' => 1,
                        'explanation' => null,
                    ];
                }
                // Check if this is an answer option
                elseif ($currentQuestion && $this->isAnswerRow($cellA, $cellB, $cellC)) {
                    $answerText = trim($cellA . ' ' . $cellB . ' ' . $cellC);
                    
                    // Check if this is the correct answer (contains "(прав)")
                    if (strpos($answerText, '(прав)') !== false) {
                        // Remove the (прав) marker and set as correct answer
                        $cleanAnswer = str_replace('(прав)', '', $answerText);
                        $cleanAnswer = trim($cleanAnswer);
                        $currentQuestion['correct_answer'] = $cleanAnswer;
                        $currentQuestion['options'][] = $cleanAnswer;
                    } else {
                        $currentQuestion['options'][] = $answerText;
                    }
                }
            }

            // Add the last question
            if ($currentQuestion) {
                $questions[] = $currentQuestion;
            }

            // Validate that we have questions
            if (empty($questions)) {
                return response()->json([
                    'message' => 'Не удалось найти вопросы в Excel файле. Убедитесь, что файл содержит вопросы и варианты ответов.'
                ], 422);
            }

            // Get course information
            $course = Course::findOrFail($courseId);

            // Create test data structure
            $testData = [
                'title' => 'Тест из Excel - ' . $course->title,
                'description' => 'Тест, созданный из Excel файла',
                'course_id' => $courseId,
                'time_limit_minutes' => 60,
                'passing_score' => 70,
                'max_attempts' => 3,
                'is_active' => false, // Start as inactive for review
                'questions' => $questions,
            ];

            return response()->json($testData);

        } catch (\Exception $e) {
            \Log::error('Excel parsing error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Ошибка при обработке Excel файла: ' . $e->getMessage()
            ], 422);
        }
    }

    /**
     * Check if a row contains a question
     */
    private function isQuestionRow($cellA, $cellB)
    {
        $text = trim($cellA . ' ' . $cellB);
        
        // Check if it starts with a number followed by a dot or contains question keywords
        if (preg_match('/^\d+\./', $text) || 
            preg_match('/^\d+\)/', $text) ||
            preg_match('/вопрос|question/i', $text) ||
            (strlen($text) > 20 && !strpos($text, '(прав)'))) {
            return true;
        }
        
        return false;
    }

    /**
     * Check if a row contains an answer option
     */
    private function isAnswerRow($cellA, $cellB, $cellC)
    {
        $text = trim($cellA . ' ' . $cellB . ' ' . $cellC);
        
        // Check if it looks like an answer option
        if (preg_match('/^[а-яёa-z]\)/i', $text) || 
            preg_match('/^[а-яёa-z]\./i', $text) ||
            preg_match('/^\d+\)/', $text) ||
            preg_match('/^\d+\./', $text) ||
            strpos($text, '(прав)') !== false) {
            return true;
        }
        
        return false;
    }
}
