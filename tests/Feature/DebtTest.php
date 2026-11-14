<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Debt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DebtTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_debt(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('debts.store'), [
            'party_name' => 'Toko Komputer',
            'type' => 'debt',
            'total_amount' => 1500000,
            'due_date' => now()->addMonth()->toDateString(),
            'notes' => 'Beli monitor baru',
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('debts', [
            'user_id' => $user->id,
            'party_name' => 'Toko Komputer',
            'total_amount' => 1500000,
            'remaining_amount' => 1500000,
            'status' => 'unpaid',
        ]);
    }

    public function test_user_can_record_debt_installment_payment(): void
    {
        $user = User::factory()->create();
        $account = Account::create([
            'user_id' => $user->id,
            'name' => 'BCA',
            'type' => 'bank',
            'initial_balance' => 2000000,
            'current_balance' => 2000000,
        ]);

        $debt = Debt::create([
            'user_id' => $user->id,
            'party_name' => 'Toko Komputer',
            'type' => 'debt',
            'total_amount' => 1000000,
            'remaining_amount' => 1000000,
            'status' => 'unpaid',
        ]);

        // Pay installment 400.000
        $response = $this->actingAs($user)->post(route('debts.payments.store', $debt->id), [
            'amount' => 400000,
            'account_id' => $account->id,
            'payment_date' => now()->toDateString(),
            'notes' => 'Cicilan 1',
        ]);

        $response->assertSessionHas('success');

        $debt->refresh();
        $this->assertEquals(600000, $debt->remaining_amount);
        $this->assertEquals('partially_paid', $debt->status);
        $this->assertEquals(1600000, $account->fresh()->current_balance);

        // Pay remaining 600.000 (settlement)
        $this->actingAs($user)->post(route('debts.payments.store', $debt->id), [
            'amount' => 600000,
            'account_id' => $account->id,
            'payment_date' => now()->toDateString(),
            'notes' => 'Pelunasan',
        ]);

        $debt->refresh();
        $this->assertEquals(0, $debt->remaining_amount);
        $this->assertEquals('paid', $debt->status);
        $this->assertEquals(1000000, $account->fresh()->current_balance);
    }

    public function test_underpayment_with_mark_as_settled_closes_debt_fully(): void
    {
        $user = User::factory()->create();
        $account = Account::create([
            'user_id' => $user->id,
            'name' => 'Cash',
            'type' => 'cash',
            'initial_balance' => 100000,
            'current_balance' => 100000,
        ]);

        // Debt 33.000
        $debt = Debt::create([
            'user_id' => $user->id,
            'party_name' => 'Warung Pak Joko',
            'type' => 'debt',
            'total_amount' => 33000,
            'remaining_amount' => 33000,
            'status' => 'unpaid',
        ]);

        // Pay 30.000, check mark_as_settled
        $response = $this->actingAs($user)->post(route('debts.payments.store', $debt->id), [
            'amount' => 30000,
            'account_id' => $account->id,
            'payment_date' => now()->toDateString(),
            'mark_as_settled' => true,
            'notes' => 'Dibulatkan pak joko',
        ]);

        $response->assertSessionHas('success');

        $debt->refresh();
        $this->assertEquals(0, $debt->remaining_amount);
        $this->assertEquals('paid', $debt->status);
        // Only 30.000 physically deducted from Cash
        $this->assertEquals(70000, $account->fresh()->current_balance);
    }

    public function test_overpayment_closes_debt_and_records_actual_amount(): void
    {
        $user = User::factory()->create();
        $account = Account::create([
            'user_id' => $user->id,
            'name' => 'GoPay',
            'type' => 'ewallet',
            'initial_balance' => 100000,
            'current_balance' => 100000,
        ]);

        // Receivable: someone owes us 33.000
        $debt = Debt::create([
            'user_id' => $user->id,
            'party_name' => 'Teman Kampus',
            'type' => 'receivable',
            'total_amount' => 33000,
            'remaining_amount' => 33000,
            'status' => 'unpaid',
        ]);

        // They pay 35.000 (2.000 tip/rounding)
        $response = $this->actingAs($user)->post(route('debts.payments.store', $debt->id), [
            'amount' => 35000,
            'account_id' => $account->id,
            'payment_date' => now()->toDateString(),
        ]);

        $response->assertSessionHas('success');

        $debt->refresh();
        $this->assertEquals(0, $debt->remaining_amount);
        $this->assertEquals('paid', $debt->status);
        // 35.000 received into account
        $this->assertEquals(135000, $account->fresh()->current_balance);
    }

    public function test_user_can_write_off_remaining_debt_balance(): void
    {
        $user = User::factory()->create();

        $debt = Debt::create([
            'user_id' => $user->id,
            'party_name' => 'Teman Lama',
            'type' => 'receivable',
            'total_amount' => 50000,
            'remaining_amount' => 5000, // Leftover 5.000
            'status' => 'partially_paid',
        ]);

        $response = $this->actingAs($user)->post(route('debts.write-off', $debt->id), [
            'reason' => 'Diikhlaskan saja',
        ]);

        $response->assertSessionHas('success');

        $debt->refresh();
        $this->assertEquals(0, $debt->remaining_amount);
        $this->assertEquals('paid', $debt->status);
        $this->assertStringContainsString('Diikhlaskan saja', $debt->notes);
    }
}
