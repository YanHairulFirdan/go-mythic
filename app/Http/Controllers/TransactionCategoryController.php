<?php

namespace App\Http\Controllers;

use App\Http\Requests\TransactionCategoryRequest;
use App\Models\TransactionCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TransactionCategoryController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request): Response
    {
        $this->authorizeOwner($request);

        $validated = $request->validate([
            'type' => ['nullable', Rule::in(['income', 'expense'])],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $type = $validated['type'] ?? 'income';
        $search = trim($validated['search'] ?? '');

        $categories = TransactionCategory::query()
            ->where('company_id', $request->user()->company_id)
            ->where('type', $type)
            ->when($search !== '', fn (Builder $query) => $query->where('name', 'like', '%'.$search.'%'))
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (TransactionCategory $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'type' => $category->type,
                'is_default' => $category->is_default,
                // US-TR-01: real usage count via withCount('transactions') once
                // the transactions table exists (Feature 4).
                'transactions_count' => 0,
            ]);

        return Inertia::render('TransactionCategories/Index', [
            'categories' => Inertia::scroll($categories),
            'filters' => ['type' => $type, 'search' => $search],
        ]);
    }

    /**
     * PRD 3.2: Owner menambah kategori custom. Juga melayani quick-create dari
     * form transaksi (XHR dapat JSON, pola sama dengan CustomerController).
     */
    public function store(TransactionCategoryRequest $request): RedirectResponse|JsonResponse
    {
        $category = TransactionCategory::create([
            ...$request->validated(),
            'company_id' => $request->user()->company_id,
            'is_default' => false,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'category' => $category->only('id', 'name', 'type'),
            ], 201);
        }

        return to_route('transaction-categories.index');
    }

    public function update(TransactionCategoryRequest $request, TransactionCategory $transactionCategory): RedirectResponse
    {
        $this->authorizeTenant($request, $transactionCategory);
        $this->abortIfPreset($transactionCategory);

        $transactionCategory->update($request->validated());

        return to_route('transaction-categories.index');
    }

    public function destroy(Request $request, TransactionCategory $transactionCategory): RedirectResponse
    {
        $this->authorizeOwner($request);
        $this->authorizeTenant($request, $transactionCategory);
        $this->abortIfPreset($transactionCategory);

        $transactionCategory->delete();

        return to_route('transaction-categories.index');
    }

    /**
     * PRD 3.2 / US-RP-01 AC1: master data kategori adalah surface Owner.
     */
    private function authorizeOwner(Request $request): void
    {
        abort_unless($request->user()->role === 'owner', 403);
    }

    private function authorizeTenant(Request $request, TransactionCategory $category): void
    {
        abort_if($category->company_id !== $request->user()->company_id, 404);
    }

    /**
     * Preset categories are the fixed baseline — not editable or removable.
     */
    private function abortIfPreset(TransactionCategory $category): void
    {
        abort_if($category->is_default, 403, 'Kategori bawaan tidak dapat diubah.');
    }
}
