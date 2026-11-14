<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\FinancialPeriod;
use App\Models\User;
use App\Services\PeriodService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PeriodTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Account $account;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->account = Account::create([
            'user_id' => $this->user->id,
            'name' => 'BCA Utama',
            'type' => 'bank',
            'current_balance' => 10000000,
            'is_active' => true,
        ]);

        $this->category = Category::create([
            'name' => 'Gaji',
            'type' => 'income',
        ]);
    }

    public function test_user_can_view_periods_index(): void
    {
        $response = $this->actingAs($this->user)->get(route('periods.index'));

        $response->assertStatus(200);
    }

    public function test_period_is_auto_created_with_opening_balance(): void
    {
        /** @var PeriodService $periodService */
        $periodService = app(PeriodService::class);
        $period = $periodService->getOrCreatePeriodForDate($this->user, Carbon::create(2026, 1, 15));

        $this->assertEquals(2026, $period->year);
        $this->assertEquals(1, $period->month);
        $this->assertEquals('open', $period->status);
        $this->assertEquals(10000000, $period->total_opening_balance);
        $this->assertDatabaseHas('period_account_balances', [
            'financial_period_id' => $period->id,
            'account_id' => $this->account->id,
            'opening_balance' => 10000000,
        ]);
    }

    public function test_user_can_close_period_and_next_period_is_auto_opened(): void
    {
        /** @var PeriodService $periodService */
        $periodService = app(PeriodService::class);
        $period = $periodService->getOrCreatePeriodForDate($this->user, Carbon::create(2026, 1, 1));

        $response = $this->actingAs($this->user)->post(route('periods.close', $period), [
            'notes' => 'Tutup buku Januari',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $period->refresh();
        $this->assertEquals('closed', $period->status);
        $this->assertEquals(10000000, $period->total_closing_balance);
        $this->assertNotNull($period->closed_at);

        // Verify next month (Februari 2026) period exists and has 10,000,000 opening balance
        $nextPeriod = FinancialPeriod::where('user_id', $this->user->id)
            ->where('year', 2026)
            ->where('month', 2)
            ->first();

        $this->assertNotNull($nextPeriod);
        $this->assertEquals('open', $nextPeriod->status);
        $this->assertEquals(10000000, $nextPeriod->total_opening_balance);
    }

    public function test_cannot_create_transaction_in_closed_period(): void
    {
        /** @var PeriodService $periodService */
        $periodService = app(PeriodService::class);
        $period = $periodService->getOrCreatePeriodForDate($this->user, Carbon::create(2026, 1, 1));

        $periodService->closePeriod($period);

        // Attempt to create a transaction in the closed period date
        $response = $this->actingAs($this->user)->post(route('transactions.store'), [
            'type' => 'income',
            'amount' => 500000,
            'transaction_date' => '2026-01-15',
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'notes' => 'Bonus Januari',
        ]);

        $response->assertSessionHasErrors(['transaction_date']);
    }

    public function test_user_can_reopen_closed_period(): void
    {
        /** @var PeriodService $periodService */
        $periodService = app(PeriodService::class);
        $period = $periodService->getOrCreatePeriodForDate($this->user, Carbon::create(2026, 1, 1));
        $periodService->closePeriod($period);

        $this->assertEquals('closed', $period->fresh()->status);

        $response = $this->actingAs($this->user)->post(route('periods.reopen', $period));

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertEquals('open', $period->fresh()->status);
        $this->assertNull($period->fresh()->closed_at);
    }

    public function test_user_can_switch_active_period(): void
    {
        /** @var PeriodService $periodService */
        $periodService = app(PeriodService::class);
        $jan = $periodService->getOrCreatePeriodForDate($this->user, Carbon::create(2026, 1, 1));
        $feb = $periodService->getOrCreatePeriodForDate($this->user, Carbon::create(2026, 2, 1));

        $response = $this->actingAs($this->user)->post(route('periods.switch', $feb));

        $response->assertSessionHas('active_period_id', $feb->id);
    }
}
