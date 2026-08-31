<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerRequest;
use App\Models\Customer;
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

        // Breakdown + related transactions is US-CUST-03 (needs the transactions table).
        return Inertia::render('Customers/Show', [
            'customer' => $customer->only('id', 'name', 'contact', 'address'),
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
