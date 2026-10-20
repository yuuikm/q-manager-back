<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FaqController extends Controller
{
    /**
     * Public listing: returns active FAQ tree or root questions, with optional search.
     */
    public function index(Request $request)
    {
        $search = $request->query('search');

        if (!empty($search)) {
            $results = Faq::where('is_active', true)
                ->where(function ($q) use ($search) {
                    $q->where('question', 'like', "%{$search}%")
                      ->orWhere('answer', 'like', "%{$search}%");
                })
                ->with(['activeChildren', 'parent'])
                ->orderBy('sort_order', 'asc')
                ->limit(20)
                ->get();

            return response()->json($results);
        }

        // Return tree structure starting from root questions
        $tree = Faq::whereNull('parent_id')
            ->where('is_active', true)
            ->with('allActiveChildren')
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        return response()->json($tree);
    }

    /**
     * Public show: returns a specific question with its active children and breadcrumbs/parent.
     */
    public function show(string $id)
    {
        $faq = Faq::where('is_active', true)
            ->with(['activeChildren', 'parent.parent'])
            ->findOrFail($id);

        return response()->json($faq);
    }

    /**
     * Admin listing: returns all FAQs with parent and children counts, supporting search and filters.
     */
    public function adminIndex(Request $request)
    {
        $query = Faq::query()->with(['parent:id,question,parent_id'])->withCount('children');

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('question', 'like', "%{$search}%")
                  ->orWhere('answer', 'like', "%{$search}%");
            });
        }

        if ($request->has('parent_id')) {
            $parentId = $request->parent_id;
            if ($parentId === 'null' || $parentId === '' || $parentId === null) {
                $query->whereNull('parent_id');
            } else {
                $query->where('parent_id', $parentId);
            }
        }

        if ($request->boolean('flat')) {
            $all = $query->orderBy('sort_order', 'asc')->orderBy('id', 'asc')->get();
            return response()->json($all);
        }

        $faqs = $query->orderByRaw('parent_id IS NULL DESC, parent_id ASC, sort_order ASC, id ASC')->get();
        return response()->json($faqs);
    }

    /**
     * Admin full hierarchy tree.
     */
    public function adminTree()
    {
        $tree = Faq::whereNull('parent_id')
            ->with('allChildren')
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        return response()->json($tree);
    }

    /**
     * Admin store new FAQ item.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'parent_id'  => 'nullable|exists:faqs,id',
            'question'   => 'required|string|max:500',
            'answer'     => 'required|string',
            'sort_order' => 'nullable|integer',
            'is_active'  => 'nullable|boolean',
        ]);

        $data = [
            'parent_id'  => $validated['parent_id'] ?? null,
            'question'   => $validated['question'],
            'answer'     => $validated['answer'],
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active'  => $request->has('is_active') ? $request->boolean('is_active') : true,
        ];

        $faq = Faq::create($data);

        return response()->json($faq->load(['parent', 'children']), 201);
    }

    /**
     * Admin show single FAQ item.
     */
    public function adminShow(string $id)
    {
        $faq = Faq::with(['parent', 'children'])->findOrFail($id);
        return response()->json($faq);
    }

    /**
     * Admin update FAQ item.
     */
    public function update(Request $request, string $id)
    {
        $faq = Faq::findOrFail($id);

        $validated = $request->validate([
            'parent_id'  => 'nullable|exists:faqs,id',
            'question'   => 'required|string|max:500',
            'answer'     => 'required|string',
            'sort_order' => 'nullable|integer',
            'is_active'  => 'nullable|boolean',
        ]);

        $newParentId = $validated['parent_id'] ?? null;

        if ($newParentId !== null) {
            if ((int)$newParentId === (int)$faq->id) {
                throw ValidationException::withMessages([
                    'parent_id' => ['Вопрос не может быть родителем самого себя.'],
                ]);
            }

            $descendantIds = $faq->getAllDescendantIds();
            if (in_array((int)$newParentId, $descendantIds, true)) {
                throw ValidationException::withMessages([
                    'parent_id' => ['Нельзя выбрать дочерний вопрос в качестве родителя (циклическая ссылка).'],
                ]);
            }
        }

        $faq->update([
            'parent_id'  => $newParentId,
            'question'   => $validated['question'],
            'answer'     => $validated['answer'],
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active'  => $request->has('is_active') ? $request->boolean('is_active') : $faq->is_active,
        ]);

        return response()->json($faq->load(['parent', 'children']));
    }

    /**
     * Admin delete FAQ item (and its children by cascade).
     */
    public function destroy(string $id)
    {
        $faq = Faq::findOrFail($id);
        $faq->delete();

        return response()->json(['message' => 'Вопрос успешно удален']);
    }

    /**
     * Admin toggle active status.
     */
    public function toggleStatus(string $id)
    {
        $faq = Faq::findOrFail($id);
        $faq->is_active = !$faq->is_active;
        $faq->save();

        return response()->json($faq);
    }
}
