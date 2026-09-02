<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerRequest;
use App\Models\Customer;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Customers/Index', [
            'customers' => Customer::query()
                ->where('company_id', $request->user()->company_id)
                ->orderBy('name')
                ->get(['id', 'name', 'contact']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Customers/Create');
    }

    /**
     * US-CUST-01 AC1: also serves the quick-create flow from the
     * transaction/invoice form — an XHR request gets the new customer
     * back as JSON instead of a redirect.
     */
    public function store(CustomerRequest $request): RedirectResponse|JsonResponse
    {
        $customer = Customer::create([
            ...$request->validated(),
            'company_id' => $request->user()->company_id,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'customer' => $customer->only('id', 'name', 'contact', 'address'),
            ], 201);
        }

        return to_route('customers.index');
    }

    public function show(Request $request, Customer $customer): Response
    {
        $this->authorizeTenant($request, $customer);

        // US-CUST-03 AC1: income transactions linked to this customer (via an
        // invoice or standalone). AC2: breakdown derived on-the-fly from the same
        // rows — no stored aggregate.
        $transactions = Transaction::query()
            ->where('company_id', $customer->company_id)
            ->where('customer_id', $customer->id)
            ->where('type', 'income')
            ->with('category:id,name')
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->get(['id', 'amount', 'transaction_date', 'category_id', 'invoice_id'])
            ->map(fn (Transaction $transaction): array => [
                'id' => $transaction->id,
                'amount' => (float) $transaction->amount,
                'transaction_date' => $transaction->transaction_date,
                'category' => $transaction->category?->name,
                'invoice_id' => $transaction->invoice_id,
            ]);

        return Inertia::render('Customers/Show', [
            'customer' => $customer->only('id', 'name', 'contact', 'address'),
            'transactions' => $transactions,
            'breakdown' => [
                'total' => (float) $transactions->sum('amount'),
                'count' => $transactions->count(),
                'last_date' => $transactions->first()['transaction_date'] ?? null,
            ],
        ]);
    }

    public function edit(Request $request, Customer $customer): Response
    {
        $this->authorizeTenant($request, $customer);

        return Inertia::render('Customers/Edit', [
            'customer' => $customer->only('id', 'name', 'contact', 'address'),
        ]);
    }

    public function update(CustomerRequest $request, Customer $customer): RedirectResponse
    {
        $this->authorizeTenant($request, $customer);

        $customer->update($request->validated());

        return to_route('customers.index');
    }

    public function destroy(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorizeTenant($request, $customer);

        $customer->delete();

        return to_route('customers.index');
    }

    private function authorizeTenant(Request $request, Customer $customer): void
    {
        abort_if($customer->company_id !== $request->user()->company_id, 404);
    }
}
