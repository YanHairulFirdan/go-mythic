<?php

namespace Tests\Feature;

use App\Models\CapitalEntry;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class TransactionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-20 09:00:00');
    }

    /**
     * @return array{0: User, 1: TransactionCategory, 2: TransactionCategory}
     */
    private function company(string $role = 'owner'): array
    {
        $user = User::factory()->create(['role' => $role]);
        CapitalEntry::factory()->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
        ]);

        return [
            $user,
            TransactionCategory::factory()->for($user->company)->income()->create(['name' => 'Penjualan']),
            TransactionCategory::factory()->for($user->company)->expense()->create(['name' => 'Belanja']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(TransactionCategory $category, array $overrides = []): array
    {
        return array_merge([
            'type' => $category->type,
            'amount' => 150_000,
            'category_id' => $category->id,
            'transaction_date' => '2026-09-15',
            'payment_method' => 'cash',
            'notes' => 'Catatan uji',
        ], $overrides);
    }

    public function test_guest_cannot_reach_create_or_store(): void
    {
        [$user, $income] = $this->company();

        $this->get(route('transactions.create'))->assertRedirect(route('login'));
        $this->post(route('transactions.store'), $this->payload($income))->assertRedirect(route('login'));
    }

    public function test_create_page_lists_company_categories(): void
    {
        [$user] = $this->company();

        $this->actingAs($user)
            ->get(route('transactions.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Transactions/Create')
                ->has('categories', 2));
    }

    public function test_owner_records_an_income_transaction(): void
    {
        [$owner, $income] = $this->company();

        $this->actingAs($owner)
            ->post(route('transactions.store'), $this->payload($income))
            ->assertRedirect(route('transactions.index'));

        $this->assertDatabaseHas('transactions', [
            'company_id' => $owner->company_id,
            'created_by' => $owner->id,
            'type' => 'income',
            'amount' => 150_000,
            'category_id' => $income->id,
            'transaction_date' => '2026-09-15',
            'payment_method' => 'cash',
            'notes' => 'Catatan uji',
            'updated_by' => null,
        ]);
    }

    public function test_employee_can_record_a_transaction(): void
    {
        [$employee, $income] = $this->company('employee');

        $this->actingAs($employee)
            ->post(route('transactions.store'), $this->payload($income))
            ->assertRedirect(route('transactions.index'));

        $this->assertDatabaseHas('transactions', [
            'created_by' => $employee->id,
            'company_id' => $employee->company_id,
        ]);
    }

    public function test_amount_must_be_greater_than_zero(): void
    {
        [$owner, $income] = $this->company();

        $this->actingAs($owner)
            ->post(route('transactions.store'), $this->payload($income, ['amount' => 0]))
            ->assertSessionHasErrors('amount');

        $this->actingAs($owner)
            ->post(route('transactions.store'), $this->payload($income, ['amount' => -5000]))
            ->assertSessionHasErrors('amount');
    }

    public function test_type_and_category_are_required(): void
    {
        [$owner] = $this->company();

        $this->actingAs($owner)
            ->post(route('transactions.store'), [
                'amount' => 1000,
                'transaction_date' => '2026-09-15',
                'payment_method' => 'cash',
            ])
            ->assertSessionHasErrors(['type', 'category_id']);
    }

    public function test_category_must_belong_to_the_company(): void
    {
        [$owner] = $this->company();
        $foreignCategory = TransactionCategory::factory()->income()->create();

        $this->actingAs($owner)
            ->post(route('transactions.store'), $this->payload($foreignCategory))
            ->assertSessionHasErrors('category_id');
    }

    public function test_category_type_must_match_transaction_type(): void
    {
        [$owner, $income] = $this->company();

        $this->actingAs($owner)
            ->post(route('transactions.store'), $this->payload($income, ['type' => 'expense']))
            ->assertSessionHasErrors('category_id');
    }

    public function test_transaction_date_cannot_be_in_the_future(): void
    {
        [$owner, $income] = $this->company();

        $this->actingAs($owner)
            ->post(route('transactions.store'), $this->payload($income, ['transaction_date' => '2026-09-21']))
            ->assertSessionHasErrors('transaction_date');
    }

    public function test_transaction_date_must_fall_within_an_active_capital_period(): void
    {
        [$owner, $income] = $this->company();

        // Before the capital entry's start_date (2026-09-01).
        $this->actingAs($owner)
            ->post(route('transactions.store'), $this->payload($income, ['transaction_date' => '2026-08-25']))
            ->assertSessionHasErrors('transaction_date');
    }

    public function test_transaction_is_blocked_when_there_is_no_active_capital(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $category = TransactionCategory::factory()->for($owner->company)->income()->create();

        $this->actingAs($owner)
            ->post(route('transactions.store'), $this->payload($category))
            ->assertSessionHasErrors('transaction_date');

        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_payment_method_must_be_supported(): void
    {
        [$owner, $income] = $this->company();

        $this->actingAs($owner)
            ->post(route('transactions.store'), $this->payload($income, ['payment_method' => 'gopay']))
            ->assertSessionHasErrors('payment_method');
    }

    public function test_creating_a_transaction_writes_an_activity_log(): void
    {
        [$owner, $income] = $this->company();

        $this->actingAs($owner)->post(route('transactions.store'), $this->payload($income));

        $transaction = Transaction::sole();
        $activity = Activity::query()
            ->where('log_name', 'transaction')
            ->where('event', 'created')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame($transaction->id, $activity->subject_id);
        $this->assertSame(Transaction::class, $activity->subject_type);
        $this->assertSame($owner->id, $activity->causer_id);
        $this->assertSame('150000.00', (string) $activity->properties['attributes']['amount']);
    }

    public function test_attachment_is_stored_on_the_private_disk(): void
    {
        Storage::fake('local');
        [$owner, $income] = $this->company();

        $this->actingAs($owner)->post(route('transactions.store'), $this->payload($income, [
            'attachment' => UploadedFile::fake()->image('bukti.png'),
        ]));

        $path = Transaction::sole()->attachment_path;
        $this->assertNotNull($path);
        $this->assertStringStartsWith('transaction-attachments/', $path);
        Storage::disk('local')->assertExists($path);
    }

    public function test_gif_attachment_is_rejected(): void
    {
        Storage::fake('local');
        [$owner, $income] = $this->company();

        $this->actingAs($owner)
            ->post(route('transactions.store'), $this->payload($income, [
                'attachment' => UploadedFile::fake()->create('animasi.gif', 200, 'image/gif'),
            ]))
            ->assertSessionHasErrors('attachment');
    }

    public function test_attachment_larger_than_one_megabyte_is_rejected(): void
    {
        Storage::fake('local');
        [$owner, $income] = $this->company();

        $this->actingAs($owner)
            ->post(route('transactions.store'), $this->payload($income, [
                'attachment' => UploadedFile::fake()->image('besar.png')->size(2048),
            ]))
            ->assertSessionHasErrors('attachment');
    }

    public function test_non_image_attachment_is_rejected(): void
    {
        Storage::fake('local');
        [$owner, $income] = $this->company();

        $this->actingAs($owner)
            ->post(route('transactions.store'), $this->payload($income, [
                'attachment' => UploadedFile::fake()->create('nota.pdf', 100, 'application/pdf'),
            ]))
            ->assertSessionHasErrors('attachment');
    }

    public function test_owner_can_download_any_company_transaction_attachment(): void
    {
        Storage::fake('local');
        [$owner, $income] = $this->company();
        $employee = User::factory()->create(['role' => 'employee', 'company_id' => $owner->company_id]);

        $this->actingAs($employee)->post(route('transactions.store'), $this->payload($income, [
            'attachment' => UploadedFile::fake()->image('bukti.png'),
        ]));
        $transaction = Transaction::sole();

        $this->actingAs($owner)
            ->get(route('transactions.attachment', $transaction))
            ->assertOk()
            ->assertDownload();
    }

    public function test_employee_cannot_download_another_users_transaction_attachment(): void
    {
        Storage::fake('local');
        [$owner, $income] = $this->company();
        $employee = User::factory()->create(['role' => 'employee', 'company_id' => $owner->company_id]);

        $this->actingAs($owner)->post(route('transactions.store'), $this->payload($income, [
            'attachment' => UploadedFile::fake()->image('bukti.png'),
        ]));
        $transaction = Transaction::sole();

        $this->actingAs($employee)
            ->get(route('transactions.attachment', $transaction))
            ->assertNotFound();
    }

    public function test_attachment_download_is_tenant_scoped(): void
    {
        Storage::fake('local');
        [$owner, $income] = $this->company();
        $this->actingAs($owner)->post(route('transactions.store'), $this->payload($income, [
            'attachment' => UploadedFile::fake()->image('bukti.png'),
        ]));
        $transaction = Transaction::sole();

        $outsider = User::factory()->create(['role' => 'owner']);

        $this->actingAs($outsider)
            ->get(route('transactions.attachment', $transaction))
            ->assertNotFound();
    }

    public function test_attachment_route_returns_404_without_a_file(): void
    {
        [$owner, $income] = $this->company();
        $this->actingAs($owner)->post(route('transactions.store'), $this->payload($income));
        $transaction = Transaction::sole();

        $this->actingAs($owner)
            ->get(route('transactions.attachment', $transaction))
            ->assertNotFound();
    }
}
