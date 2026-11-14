<?php

namespace App\Services;

use App\Models\Debt;
use App\Models\FinancialPeriod;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Menganalisa kondisi keuangan satu periode bulanan.
 *
 * - Metrik & skor dihitung secara deterministik (rule-based), selalu tersedia.
 * - Narasi AI (Gemini) bersifat opsional; jika gagal/tanpa API key, dipakai narasi rule-based.
 */
class FinancialInsightService
{
    /**
     * Hitung metrik keuangan mentah untuk sebuah periode.
     */
    public function buildMetrics(User $user, FinancialPeriod $period): array
    {
        $income = (float) $period->transactions()->where('type', 'income')->sum('amount');
        $expense = (float) $period->transactions()->where('type', 'expense')->sum('amount');
        $savings = $income - $expense;
        $savingsRate = $income > 0 ? round(($savings / $income) * 100, 1) : 0.0;

        $topCategories = $period->transactions()
            ->where('type', 'expense')
            ->with('category:id,name')
            ->get()
            ->groupBy(fn ($t) => $t->category?->name ?? 'Tanpa Kategori')
            ->map(fn ($rows) => (float) $rows->sum('amount'))
            ->sortDesc()
            ->take(5)
            ->map(fn ($total, $name) => [
                'name' => $name,
                'total' => $total,
                'percent' => $expense > 0 ? round(($total / $expense) * 100, 1) : 0,
            ])
            ->values()
            ->all();

        $previous = $user->periods()
            ->where(fn ($q) => $q->where('year', '<', $period->year)
                ->orWhere(fn ($q2) => $q2->where('year', $period->year)->where('month', '<', $period->month)))
            ->orderByDesc('year')->orderByDesc('month')
            ->first();

        $prevExpense = $previous ? (float) $previous->transactions()->where('type', 'expense')->sum('amount') : null;
        $expenseChange = ($prevExpense !== null && $prevExpense > 0)
            ? round((($expense - $prevExpense) / $prevExpense) * 100, 1)
            : null;

        $liquid = (float) $user->accounts()->active()->sum('current_balance');
        $debtRemaining = (float) Debt::where('user_id', $user->id)->where('type', 'debt')->sum('remaining_amount');
        $receivableRemaining = (float) Debt::where('user_id', $user->id)->where('type', 'receivable')->sum('remaining_amount');

        $emergencyMonths = $expense > 0 ? round($liquid / $expense, 1) : null;
        $debtRatio = $income > 0 ? round(($debtRemaining / $income) * 100, 1) : null;

        return [
            'period' => $period->name,
            'income' => $income,
            'expense' => $expense,
            'savings' => $savings,
            'savings_rate' => $savingsRate,
            'top_expense_categories' => $topCategories,
            'expense_change_percent' => $expenseChange,
            'liquid_balance' => $liquid,
            'debt_remaining' => $debtRemaining,
            'receivable_remaining' => $receivableRemaining,
            'emergency_fund_months' => $emergencyMonths,
            'debt_to_income_percent' => $debtRatio,
            'transaction_count' => $period->transactions()->count(),
        ];
    }

    /**
     * Skor kesehatan keuangan 0-100 + daftar temuan & saran rule-based.
     */
    public function evaluate(array $m): array
    {
        $score = 0;
        $findings = [];
        $tips = [];

        if ($m['transaction_count'] === 0) {
            return [
                'score' => null,
                'label' => 'Belum ada data',
                'findings' => ['Belum ada transaksi pada periode ini.'],
                'tips' => ['Mulai catat pemasukan & pengeluaran agar analisa dapat dibuat.'],
            ];
        }

        // 1) Tingkat tabungan (maks 40)
        $sr = $m['savings_rate'];
        if ($m['income'] <= 0) {
            $findings[] = 'Tidak ada pemasukan tercatat pada periode ini.';
            $tips[] = 'Pastikan semua pemasukan (gaji, freelance, dll) sudah diinput.';
        } elseif ($sr >= 20) {
            $score += 40;
            $findings[] = "Tingkat tabungan sangat baik ({$sr}% dari pemasukan).";
        } elseif ($sr >= 10) {
            $score += 28;
            $findings[] = "Tingkat tabungan cukup ({$sr}%), target ideal ≥ 20%.";
            $tips[] = 'Coba sisihkan tabungan di awal bulan sebelum membelanjakan.';
        } elseif ($sr > 0) {
            $score += 15;
            $findings[] = "Tingkat tabungan rendah ({$sr}%).";
            $tips[] = 'Kurangi pengeluaran non-esensial untuk menaikkan tabungan ke ≥ 10%.';
        } else {
            $findings[] = 'Pengeluaran melebihi pemasukan (defisit).';
            $tips[] = 'Prioritaskan memangkas kategori pengeluaran terbesar bulan ini.';
        }

        // 2) Dana darurat (maks 25)
        $em = $m['emergency_fund_months'];
        if ($em !== null) {
            if ($em >= 6) {
                $score += 25;
                $findings[] = "Dana likuid setara {$em} bulan pengeluaran (sangat aman).";
            } elseif ($em >= 3) {
                $score += 18;
                $findings[] = "Dana likuid setara {$em} bulan pengeluaran (cukup).";
            } elseif ($em >= 1) {
                $score += 8;
                $findings[] = "Dana likuid baru setara {$em} bulan pengeluaran.";
                $tips[] = 'Bangun dana darurat bertahap hingga 3–6 bulan pengeluaran.';
            } else {
                $findings[] = 'Dana likuid kurang dari 1 bulan pengeluaran.';
                $tips[] = 'Dana darurat adalah prioritas utama sebelum investasi/konsumsi besar.';
            }
        }

        // 3) Beban utang (maks 20)
        $dti = $m['debt_to_income_percent'];
        if ($m['debt_remaining'] <= 0) {
            $score += 20;
            $findings[] = 'Tidak ada utang berjalan.';
        } elseif ($dti !== null && $dti <= 35) {
            $score += 14;
            $findings[] = "Sisa utang setara {$dti}% pemasukan bulanan (terkendali).";
        } else {
            $score += 4;
            $findings[] = 'Sisa utang besar dibanding pemasukan bulanan.';
            $tips[] = 'Susun jadwal pelunasan utang, mulai dari yang jatuh tempo terdekat.';
        }

        // 4) Tren pengeluaran (maks 15)
        $chg = $m['expense_change_percent'];
        if ($chg === null) {
            $score += 8;
        } elseif ($chg <= 0) {
            $score += 15;
            $findings[] = 'Pengeluaran turun ' . abs($chg) . '% dibanding bulan lalu.';
        } elseif ($chg <= 10) {
            $score += 10;
            $findings[] = "Pengeluaran naik {$chg}% dibanding bulan lalu (wajar).";
        } else {
            $score += 3;
            $findings[] = "Pengeluaran naik {$chg}% dibanding bulan lalu.";
            $tips[] = 'Tinjau kategori yang melonjak dan tetapkan batas bulanan.';
        }

        // Dominasi kategori
        $top = $m['top_expense_categories'][0] ?? null;
        if ($top && $top['percent'] >= 50) {
            $findings[] = "Kategori \"{$top['name']}\" menyedot {$top['percent']}% dari total pengeluaran.";
            $tips[] = "Evaluasi pengeluaran \"{$top['name']}\" — peluang penghematan terbesar.";
        }

        $label = match (true) {
            $score >= 80 => 'Sangat Sehat',
            $score >= 60 => 'Sehat',
            $score >= 40 => 'Perlu Perhatian',
            default => 'Rawan',
        };

        return compact('score', 'label', 'findings', 'tips');
    }

    /**
     * Narasi AI via Gemini. Mengembalikan null bila tidak tersedia/gagal.
     */
    public function generateAiNarrative(array $metrics, array $evaluation, string $currency = 'Rp'): ?string
    {
        $key = config('services.gemini.key');
        if (! $key || $evaluation['score'] === null) {
            return null;
        }

        $prompt = "Kamu adalah penasihat keuangan pribadi yang ramah. Berdasarkan data JSON berikut "
            . "(mata uang {$currency}), tulis analisa singkat dalam Bahasa Indonesia: "
            . "1) penilaian umum apakah keuangan baik/tidak, 2) 3 temuan penting, "
            . "3) 3 saran konkret & terukur untuk bulan depan. Maksimal 200 kata, format markdown ringan. "
            . "Jangan mengarang angka di luar data.\n\n"
            . json_encode(['metrics' => $metrics, 'evaluation' => $evaluation], JSON_UNESCAPED_UNICODE);

        try {
            $model = config('services.gemini.model');
            $response = Http::timeout(20)
                ->withHeaders(['x-goog-api-key' => $key])
                ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
                    'contents' => [['parts' => [['text' => $prompt]]]],
                ]);

            if ($response->failed()) {
                Log::warning('Gemini insight failed', ['status' => $response->status()]);
                return null;
            }

            return $response->json('candidates.0.content.parts.0.text');
        } catch (\Throwable $e) {
            Log::warning('Gemini insight error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Paket lengkap untuk controller.
     */
    public function analyze(User $user, FinancialPeriod $period): array
    {
        $metrics = $this->buildMetrics($user, $period);
        $evaluation = $this->evaluate($metrics);
        $narrative = $this->generateAiNarrative($metrics, $evaluation, $user->currency_symbol ?? 'Rp');

        return [
            'metrics' => $metrics,
            'evaluation' => $evaluation,
            'ai_narrative' => $narrative,
            'ai_enabled' => (bool) config('services.gemini.key'),
        ];
    }
}
