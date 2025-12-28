<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Test;
use App\Models\TestQuestion;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

class TestController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Test::with(['course', 'author', 'questions']);

        // Filter by course
        if ($request->has('course_id')) {
            $query->where('course_id', $request->get('course_id'));
        }

        // Filter by active status
        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        // Search by title
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where('title', 'like', "%{$search}%");
        }

        // Filter by author
        if ($request->has('author_id') && $request->author_id) {
            $query->where('created_by', $request->author_id);
        }

        // Filter by date range
        if ($request->has('start_date') && $request->start_date) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->has('end_date') && $request->end_date) {
            $query->whereDate('created_at', '<=', $request->end_date);
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

        // Validate total_questions doesn't exceed available questions
        $totalQuestions = $request->total_questions ?? count($request->questions);
        if ($totalQuestions > count($request->questions)) {
            $totalQuestions = count($request->questions);
        }

        DB::beginTransaction();
        try {
            // Get created_by user ID with fallback
            $createdBy = auth()->id();
            if (!$createdBy) {
                // Fallback to first admin user
                $adminUser = \App\Models\User::where('role', 'admin')->first();
                if ($adminUser) {
                    $createdBy = $adminUser->id;
                } else {
                    return response()->json(['message' => 'Не удалось определить автора теста'], 500);
                }
            }

            $test = Test::create([
                'title' => $request->title,
                'description' => $request->description,
                'course_id' => $request->course_id,
                'time_limit_minutes' => $request->time_limit_minutes,
                'passing_score' => $request->passing_score,
                'max_attempts' => $request->max_attempts,
                'total_questions' => $totalQuestions,
                'is_active' => $request->is_active ?? true,
                'created_by' => $createdBy,
            ]);

            // Create test questions
            foreach ($request->questions as $index => $questionData) {
                TestQuestion::create([
                    'test_id' => $test->id,
                    'question' => $questionData['question'],
                    'type' => $questionData['type'],
                    'options' => $questionData['options'] ?? null,
                    'correct_answer' => $questionData['correct_answer'],
                    'points' => $questionData['points'],
                    'explanation' => $questionData['explanation'] ?? null,
                    'order' => $index,
                ]);
            }

            DB::commit();
            $test->load(['course', 'author', 'questions']);
            return response()->json($test, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Test creation error: ' . $e->getMessage());
            return response()->json(['message' => 'Ошибка при создании теста: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $test = Test::with(['course', 'author', 'questions'])->findOrFail($id);
        return response()->json($test);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $test = Test::findOrFail($id);

        // Check if this is a simple status toggle (only is_active is provided)
        $isStatusToggle = $request->has('is_active') && count($request->all()) === 1;

        if ($isStatusToggle) {
            // Simple status toggle
            $request->validate([
                'is_active' => 'required|boolean',
            ]);
            
            $test->update(['is_active' => $request->is_active]);
            $test->load(['course', 'author', 'questions']);
            return response()->json($test);
        }

        // Full test update
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'course_id' => 'required|exists:courses,id',
            'time_limit_minutes' => 'required|integer|min:1|max:300',
            'passing_score' => 'required|integer|min:1|max:100',
            'max_attempts' => 'required|integer|min:1|max:10',
            'total_questions' => 'nullable|integer|min:1',
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

        // Validate total_questions doesn't exceed available questions
        $totalQuestions = $request->total_questions ?? count($request->questions);
        if ($totalQuestions > count($request->questions)) {
            $totalQuestions = count($request->questions);
        }

        DB::beginTransaction();
        try {
            $test->update([
                'title' => $request->title,
                'description' => $request->description,
                'course_id' => $request->course_id,
                'time_limit_minutes' => $request->time_limit_minutes,
                'passing_score' => $request->passing_score,
                'max_attempts' => $request->max_attempts,
                'total_questions' => $totalQuestions,
                'is_active' => $request->is_active ?? $test->is_active,
            ]);

            // Delete old questions and create new ones
            $test->questions()->delete();
            foreach ($request->questions as $index => $questionData) {
                TestQuestion::create([
                    'test_id' => $test->id,
                    'question' => $questionData['question'],
                    'type' => $questionData['type'],
                    'options' => $questionData['options'] ?? null,
                    'correct_answer' => $questionData['correct_answer'],
                    'points' => $questionData['points'],
                    'explanation' => $questionData['explanation'] ?? null,
                    'order' => $index,
                ]);
            }

            DB::commit();
            $test->load(['course', 'author', 'questions']);
            return response()->json($test);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Test update error: ' . $e->getMessage());
            return response()->json(['message' => 'Ошибка при обновлении теста: ' . $e->getMessage()], 500);
        }
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
        $tests = $course->tests()->with(['author', 'questions'])->get();
        return response()->json($tests);
    }

    /**
     * Duplicate a test
     */
    public function duplicate(string $id)
    {
        $originalTest = Test::with('questions')->findOrFail($id);
        
        DB::beginTransaction();
        try {
            // Get created_by user ID with fallback
            $createdBy = auth()->id();
            if (!$createdBy) {
                // Fallback to first admin user
                $adminUser = \App\Models\User::where('role', 'admin')->first();
                if ($adminUser) {
                    $createdBy = $adminUser->id;
                } else {
                    return response()->json(['message' => 'Не удалось определить автора теста'], 500);
                }
            }

            $newTest = Test::create([
                'title' => $originalTest->title . ' (Copy)',
                'description' => $originalTest->description,
                'course_id' => $originalTest->course_id,
                'time_limit_minutes' => $originalTest->time_limit_minutes,
                'passing_score' => $originalTest->passing_score,
                'max_attempts' => $originalTest->max_attempts,
                'total_questions' => $originalTest->total_questions,
                'is_active' => false, // Start as inactive
                'created_by' => $createdBy,
            ]);

            // Duplicate questions
            foreach ($originalTest->questions as $question) {
                TestQuestion::create([
                    'test_id' => $newTest->id,
                    'question' => $question->question,
                    'type' => $question->type,
                    'options' => $question->options,
                    'correct_answer' => $question->correct_answer,
                    'points' => $question->points,
                    'explanation' => $question->explanation,
                    'order' => $question->order,
                ]);
            }

            DB::commit();
            $newTest->load(['course', 'author', 'questions']);
            return response()->json($newTest, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Test duplication error: ' . $e->getMessage());
            return response()->json(['message' => 'Ошибка при дублировании теста: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Parse Excel file and extract test questions
     * Expected format:
     * Column A: Тип вопроса (Question Type) - optional
     * Column B: Вопрос (Question)
     * Column C: Ответ 1 (Answer 1)
     * Column D: Ответ 2 (Answer 2)
     * Column E: Ответ 3 (Answer 3)
     * Column F: Ответ 4 (Answer 4)
     * Column G: Ответ 5 (Answer 5) - optional
     * Column H: Балл (Score) - optional, defaults to 5
     * One of the answers contains "(прав)" which marks it as correct
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

            // Start from row 2 (skip header row)
            for ($row = 2; $row <= $highestRow; $row++) {
                // Get cell values
                $questionType = trim((string)$worksheet->getCell('A' . $row)->getValue());
                $questionText = trim((string)$worksheet->getCell('B' . $row)->getValue());
                $answer1 = trim((string)$worksheet->getCell('C' . $row)->getValue());
                $answer2 = trim((string)$worksheet->getCell('D' . $row)->getValue());
                $answer3 = trim((string)$worksheet->getCell('E' . $row)->getValue());
                $answer4 = trim((string)$worksheet->getCell('F' . $row)->getValue());
                $answer5 = trim((string)$worksheet->getCell('G' . $row)->getValue());
                $points = trim((string)$worksheet->getCell('H' . $row)->getValue());

                // Skip empty rows (if question text is empty)
                if (empty($questionText)) {
                    continue;
                }

                // Collect all answers
                $answers = [];
                if (!empty($answer1)) $answers[] = $answer1;
                if (!empty($answer2)) $answers[] = $answer2;
                if (!empty($answer3)) $answers[] = $answer3;
                if (!empty($answer4)) $answers[] = $answer4;
                if (!empty($answer5)) $answers[] = $answer5;

                // Find correct answer (contains "(прав)") and remove the marker
                $correctAnswer = '';
                $cleanAnswers = [];

                foreach ($answers as $answer) {
                    if (stripos($answer, '(прав)') !== false) {
                        // Remove "(прав)" marker (case-insensitive)
                        $cleanAnswer = preg_replace('/\(прав\)/i', '', $answer);
                        $cleanAnswer = trim($cleanAnswer);
                        $correctAnswer = $cleanAnswer;
                        $cleanAnswers[] = $cleanAnswer;
                    } else {
                        $cleanAnswers[] = trim($answer);
                    }
                }

                // Validate we have at least 2 answers and a correct answer
                if (count($cleanAnswers) < 2 || empty($correctAnswer)) {
                    continue; // Skip invalid questions
                }

                // Parse points (default to 5 if not set)
                $questionPoints = 5;
                if (!empty($points) && is_numeric($points)) {
                    $questionPoints = (int)$points;
                }

                // Create question structure
                $questions[] = [
                    'question' => $questionText,
                    'type' => 'single_choice',
                    'options' => $cleanAnswers,
                    'correct_answer' => $correctAnswer,
                    'points' => $questionPoints,
                    'explanation' => null,
                ];
            }

            // Validate that we have questions
            if (empty($questions)) {
                return response()->json([
                    'message' => 'Не удалось найти вопросы в Excel файле. Убедитесь, что файл содержит вопросы и варианты ответов в правильном формате.'
                ], 422);
            }

            // Get course information
            $course = Course::findOrFail($courseId);

            // Create test data structure
            $testData = [
                'title' => 'Тест из Excel - ' . $course->title,
                'description' => 'Тест',
                'course_id' => $courseId,
                'time_limit_minutes' => 60,
                'passing_score' => 70,
                'max_attempts' => 3,
                'total_questions' => count($questions), // Default to all questions
                'is_active' => false, // Start as inactive for review
                'questions' => $questions,
            ];

            return response()->json($testData);

        } catch (\Exception $e) {
            Log::error('Excel parsing error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Ошибка при обработке Excel файла: ' . $e->getMessage()
            ], 422);
        }
    }

}

