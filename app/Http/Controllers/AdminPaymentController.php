<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AdminPaymentController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Payments/Index', [
            'payments' => Payment::query()
                ->with('company:id,name')
                ->when(request('status'), fn ($query, $status) => $query->where('status', $status))
                ->latest()
                ->get(['id', 'company_id', 'amount', 'attachment_path', 'status', 'approved_by', 'approved_at', 'created_at']),
        ]);
    }

    public function approve(Payment $payment): RedirectResponse
    {
        DB::transaction(function () use ($payment): void {
            $payment->refresh();

            if ($payment->status !== 'pending') {
                return;
            }

            $company = $payment->company()->lockForUpdate()->firstOrFail();
            $now = now();
            $paidUntil = $company->paid_until?->isFuture()
                ? $company->paid_until->copy()->addDays(30)
                : $now->copy()->addDays(30);

            $payment->update([
                'status' => 'approved',
                'approved_by' => auth('admin')->id(),
                'approved_at' => $now,
            ]);

            $companyChanges = ['paid_until' => $paidUntil];

            // US-SUB-07 AC4: an approved payment reactivates a soft-closed company
            // and restores the accounts that were disabled *because* it closed.
            if ($company->status === 'closed') {
                $companyChanges['status'] = 'active';

                User::query()
                    ->where('company_id', $company->id)
                    ->where('status', 'inactive')
                    ->where('inactive_reason', 'company_closed')
                    ->update(['status' => 'active', 'inactive_reason' => null]);
            }

            $company->update($companyChanges);
        });

        return to_route('admin.payments.index');
    }
}
