<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Faq;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    /**
     * Store a newly created lead from the front chat / FAQ bot (Public).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'phone'    => 'required|string|max:50',
            'question' => 'required|string|max:2000',
            'email'    => 'nullable|email|max:100',
            'name'     => 'nullable|string|max:100',
            'source'   => 'nullable|string|max:50',
        ]);

        $lead = Lead::create([
            'name'     => $validated['name'] ?? null,
            'phone'    => $validated['phone'],
            'email'    => $validated['email'] ?? null,
            'question' => $validated['question'],
            'source'   => $validated['source'] ?? 'chat_faq',
            'status'   => 'new',
        ]);

        return response()->json([
            'message' => 'Ваш вопрос и контактные данные успешно приняты. Наш менеджер свяжется с вами!',
            'lead'    => $lead,
        ], 201);
    }

    /**
     * Admin: Display a listing of leads with filters.
     */
    public function index(Request $request)
    {
        $query = Lead::with('faq:id,question');

        if ($request->has('status') && !empty($request->status)) {
            $query->where('status', $request->status);
        }

        if ($request->has('is_faq_added') && $request->is_faq_added !== '') {
            $query->where('is_faq_added', $request->boolean('is_faq_added'));
        }

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('question', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        $leads = $query->orderBy('created_at', 'desc')->get();

        return response()->json($leads);
    }

    /**
     * Admin: Display the specified lead.
     */
    public function show(string $id)
    {
        $lead = Lead::with('faq')->findOrFail($id);
        return response()->json($lead);
    }

    /**
     * Admin: Update lead status or notes.
     */
    public function updateStatus(Request $request, string $id)
    {
        $lead = Lead::findOrFail($id);

        $validated = $request->validate([
            'status' => 'nullable|string|in:new,in_progress,contacted,closed',
            'notes'  => 'nullable|string',
        ]);

        if (isset($validated['status'])) {
            $lead->status = $validated['status'];
        }

        if (array_key_exists('notes', $validated)) {
            $lead->notes = $validated['notes'];
        }

        $lead->save();

        return response()->json($lead);
    }

    /**
     * Admin: Convert user question into an FAQ or link to existing FAQ.
     */
    public function convertFaq(Request $request, string $id)
    {
        $lead = Lead::findOrFail($id);

        $validated = $request->validate([
            'faq_id'     => 'nullable|exists:faqs,id',
            'answer'     => 'nullable|string',
            'parent_id'  => 'nullable|exists:faqs,id',
            'sort_order' => 'nullable|integer',
        ]);

        // If an existing faq_id was passed, just link it
        if (!empty($validated['faq_id'])) {
            $lead->faq_id = $validated['faq_id'];
            $lead->is_faq_added = true;
            $lead->save();
            return response()->json($lead->load('faq'));
        }

        // If answer was provided, create a new FAQ item directly
        if (!empty($validated['answer'])) {
            $faq = Faq::create([
                'question'   => $lead->question,
                'answer'     => $validated['answer'],
                'parent_id'  => $validated['parent_id'] ?? null,
                'sort_order' => $validated['sort_order'] ?? 0,
                'is_active'  => true,
            ]);

            $lead->faq_id = $faq->id;
            $lead->is_faq_added = true;
            $lead->save();

            return response()->json([
                'lead' => $lead->load('faq'),
                'faq'  => $faq,
            ]);
        }

        // Otherwise just toggle is_faq_added
        $lead->is_faq_added = true;
        $lead->save();

        return response()->json($lead);
    }

    /**
     * Admin: Remove the specified lead.
     */
    public function destroy(string $id)
    {
        $lead = Lead::findOrFail($id);
        $lead->delete();

        return response()->json(['message' => 'Лид успешно удален']);
    }
}
