<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureCompanySubscription;
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
        $paid = EnsureCompanySubscription::isPaid($company->id, $company->paid_until);

        return Inertia::render('Subscription/Index', [
            'paid' => $paid,
            'expired' => $company->paid_until !== null && ! $paid,
            'subscriptionWarning' => $company->paid_until !== null && ! $paid
                ? EnsureCompanySubscription::EXPIRED_MESSAGE
                : null,
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
        abort_unless($request->user()?->role === 'owner', 403);

        Payment::create([
            'company_id' => $request->user()->company_id,
            'amount' => 99000,
            'attachment_path' => $request->file('proof')->store('payment-proofs', 'local'),
            'status' => 'pending',
        ]);

        return to_route('subscription.index');
    }
}
