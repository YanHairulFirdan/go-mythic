<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentRequest;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionController extends Controller
{
    public function index(): Response
    {
        $user = request()->user();

        abort_unless($user?->role === 'owner', 403);

        $company = $user->company;

        return Inertia::render('Subscription/Index', [
            'paid' => $company->paid_until !== null && $company->paid_until->isFuture(),
            'paidUntil' => $company->paid_until?->toDayDateTimeString(),
            'pendingPayment' => Payment::query()
                ->where('company_id', $company->id)
                ->where('status', 'pending')
                ->latest('id')
                ->first(['id', 'created_at'])
                ?->created_at
                ?->toDayDateTimeString(),
        ]);
    }

    public function store(StorePaymentRequest $request): RedirectResponse
    {
        Payment::create([
            'company_id' => $request->user()->company_id,
            'amount' => 99000,
            'attachment_path' => $request->file('proof')->store('payment-proofs', 'local'),
            'status' => 'pending',
        ]);

        return to_route('subscription.index');
    }
}
