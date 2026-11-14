<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Category;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. System Default Categories (user_id = null)
        $systemCategories = [
            // Expense Categories
            ['name' => 'Makanan & Minuman', 'type' => 'expense', 'icon' => 'utensils', 'color_hex' => '#EF4444'],
            ['name' => 'Transportasi', 'type' => 'expense', 'icon' => 'car', 'color_hex' => '#F59E0B'],
            ['name' => 'Tagihan & Utilitas', 'type' => 'expense', 'icon' => 'zap', 'color_hex' => '#3B82F6'],
            ['name' => 'Belanja Kebutuhan', 'type' => 'expense', 'icon' => 'shopping-bag', 'color_hex' => '#8B5CF6'],
            ['name' => 'Hiburan & Hobi', 'type' => 'expense', 'icon' => 'film', 'color_hex' => '#EC4899'],
            ['name' => 'Kesehatan', 'type' => 'expense', 'icon' => 'heart-pulse', 'color_hex' => '#10B981'],
            ['name' => 'Pendidikan', 'type' => 'expense', 'icon' => 'book-open', 'color_hex' => '#6366F1'],
            ['name' => 'Lain-lain', 'type' => 'expense', 'icon' => 'more-horizontal', 'color_hex' => '#6B7280'],

            // Income Categories
            ['name' => 'Gaji Pokok', 'type' => 'income', 'icon' => 'briefcase', 'color_hex' => '#10B981'],
            ['name' => 'Freelance & Side Job', 'type' => 'income', 'icon' => 'laptop', 'color_hex' => '#06B6D4'],
            ['name' => 'Investasi & Dividen', 'type' => 'income', 'icon' => 'trending-up', 'color_hex' => '#8B5CF6'],
            ['name' => 'Bonus & Hadiah', 'type' => 'income', 'icon' => 'gift', 'color_hex' => '#F59E0B'],
            ['name' => 'Pendapatan Lain', 'type' => 'income', 'icon' => 'dollar-sign', 'color_hex' => '#3B82F6'],
        ];

        foreach ($systemCategories as $cat) {
            Category::firstOrCreate(
                ['name' => $cat['name'], 'user_id' => null],
                $cat
            );
        }

        // 2. Demo User: Andi (from PRD 3.1 Persona)
        $user = User::firstOrCreate(
            ['email' => 'andi@fintrack.id'],
            [
                'name' => 'Andi Pratama',
                'password' => Hash::make('password123'),
                'currency_symbol' => 'Rp',
            ]
        );

        // 3. Accounts for Andi
        $accountsData = [
            ['name' => 'BCA Utama', 'type' => 'bank', 'account_number' => '8210394821', 'balance' => 8500000, 'color' => '#00529C', 'icon' => 'landmark'],
            ['name' => 'Jenius Freelance', 'type' => 'bank', 'account_number' => '9001283719', 'balance' => 3200000, 'color' => '#F37021', 'icon' => 'credit-card'],
            ['name' => 'GoPay', 'type' => 'ewallet', 'account_number' => '08123456789', 'balance' => 450000, 'color' => '#00AED6', 'icon' => 'wallet'],
            ['name' => 'OVO', 'type' => 'ewallet', 'account_number' => '08123456789', 'balance' => 220000, 'color' => '#4C2A86', 'icon' => 'smartphone'],
            ['name' => 'Dompet Cash (Tunai)', 'type' => 'cash', 'account_number' => null, 'balance' => 350000, 'color' => '#10B981', 'icon' => 'banknote'],
        ];

        $accountModels = [];
        foreach ($accountsData as $acc) {
            $account = Account::firstOrCreate(
                ['user_id' => $user->id, 'name' => $acc['name']],
                [
                    'type' => $acc['type'],
                    'account_number' => $acc['account_number'],
                    'initial_balance' => $acc['balance'],
                    'current_balance' => $acc['balance'],
                    'color_hex' => $acc['color'],
                    'icon' => $acc['icon'],
                    'is_active' => true,
                ]
            );
            $accountModels[$acc['name']] = $account;
        }

        // 4. Sample Transactions for Andi
        $catGaji = Category::where('name', 'Gaji Pokok')->first();
        $catFreelance = Category::where('name', 'Freelance & Side Job')->first();
        $catMakan = Category::where('name', 'Makanan & Minuman')->first();
        $catTransport = Category::where('name', 'Transportasi')->first();
        $catTagihan = Category::where('name', 'Tagihan & Utilitas')->first();

        if (Transaction::where('user_id', $user->id)->count() === 0) {
            // Salary income
            Transaction::create([
                'user_id' => $user->id,
                'account_id' => $accountModels['BCA Utama']->id,
                'category_id' => $catGaji?->id,
                'type' => 'income',
                'amount' => 9500000,
                'transaction_date' => Carbon::now()->startOfMonth()->toDateString(),
                'notes' => 'Gaji Bulanan PT Maju Jaya',
            ]);

            // Freelance project
            Transaction::create([
                'user_id' => $user->id,
                'account_id' => $accountModels['Jenius Freelance']->id,
                'category_id' => $catFreelance?->id,
                'type' => 'income',
                'amount' => 3500000,
                'transaction_date' => Carbon::now()->subDays(5)->toDateString(),
                'notes' => 'Pembayaran UI/UX Design Project Mobile App',
            ]);

            // Transfer from BCA to Cash (Tarik Tunai ATM)
            Transaction::create([
                'user_id' => $user->id,
                'account_id' => $accountModels['BCA Utama']->id,
                'to_account_id' => $accountModels['Dompet Cash (Tunai)']->id,
                'type' => 'transfer',
                'amount' => 500000,
                'transaction_date' => Carbon::now()->subDays(3)->toDateString(),
                'notes' => 'Tarik tunai ATM BCA untuk pegangan mingguan',
            ]);

            // Transfer from BCA to GoPay (Topup)
            Transaction::create([
                'user_id' => $user->id,
                'account_id' => $accountModels['BCA Utama']->id,
                'to_account_id' => $accountModels['GoPay']->id,
                'type' => 'transfer',
                'amount' => 300000,
                'transaction_date' => Carbon::now()->subDays(2)->toDateString(),
                'notes' => 'Top Up GoPay via BCA Mobile',
            ]);

            // Expense: Lunch
            Transaction::create([
                'user_id' => $user->id,
                'account_id' => $accountModels['GoPay']->id,
                'category_id' => $catMakan?->id,
                'type' => 'expense',
                'amount' => 65000,
                'transaction_date' => Carbon::now()->subDays(1)->toDateString(),
                'notes' => 'Makan siang Nasi Padang & Es Teh',
            ]);

            // Expense: Internet Wi-Fi
            Transaction::create([
                'user_id' => $user->id,
                'account_id' => $accountModels['BCA Utama']->id,
                'category_id' => $catTagihan?->id,
                'type' => 'expense',
                'amount' => 375000,
                'transaction_date' => Carbon::now()->subDays(4)->toDateString(),
                'notes' => 'Tagihan IndiHome Internet Rumah',
            ]);
        }

        // 5. Debts & Receivables for Andi
        if (Debt::where('user_id', $user->id)->count() === 0) {
            // Debt 1: Utang cicilan laptop ke Tokopedia/Kartu Kredit
            $debt = Debt::create([
                'user_id' => $user->id,
                'party_name' => 'Cicilan Laptop Asus ROG',
                'type' => 'debt',
                'total_amount' => 4500000,
                'remaining_amount' => 3000000,
                'due_date' => Carbon::now()->addDays(12)->toDateString(),
                'status' => 'partially_paid',
                'notes' => 'Cicilan 3x bulan, sudah dibayar cicilan 1',
            ]);

            DebtPayment::create([
                'debt_id' => $debt->id,
                'amount' => 1500000,
                'payment_date' => Carbon::now()->subDays(18)->toDateString(),
                'notes' => 'Pembayaran Cicilan ke-1 via BCA',
            ]);

            // Receivable 1: Teman kantor pinjam uang (Budi)
            Debt::create([
                'user_id' => $user->id,
                'party_name' => 'Budi Santoso (Rekan Kantor)',
                'type' => 'receivable',
                'total_amount' => 750000,
                'remaining_amount' => 750000,
                'due_date' => Carbon::now()->addDays(2)->toDateString(),
                'status' => 'unpaid',
                'notes' => 'Pinjam untuk perbaikan motor, janji kembali saat gajian',
            ]);

            // Receivable 2: Hendra
            Debt::create([
                'user_id' => $user->id,
                'party_name' => 'Hendra Wijaya',
                'type' => 'receivable',
                'total_amount' => 1200000,
                'remaining_amount' => 400000,
                'due_date' => Carbon::now()->subDays(2)->toDateString(), // Overdue example
                'status' => 'partially_paid',
                'notes' => 'Talangan beli sparepart laptop kantor',
            ]);
        }
    }
}
