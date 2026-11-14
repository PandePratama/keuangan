<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'financial_period_id',
        'account_id',
        'to_account_id',
        'category_id',
        'type',
        'amount',
        'transaction_date',
        'notes',
        'receipt_image_path',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'transaction_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function financialPeriod(): BelongsTo
    {
        return $this->belongsTo(FinancialPeriod::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function toAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'to_account_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function debtPayment(): HasOne
    {
        return $this->hasOne(DebtPayment::class);
    }

    /**
     * Filter transactions based on request parameters.
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['period_id'] ?? null, function ($q, $periodId) {
                $q->where('financial_period_id', $periodId);
            })
            ->when($filters['type'] ?? null, function ($q, $type) {
                $q->where('type', $type);
            })
            ->when($filters['account_id'] ?? null, function ($q, $accountId) {
                $q->where(function ($sub) use ($accountId) {
                    $sub->where('account_id', $accountId)
                        ->orWhere('to_account_id', $accountId);
                });
            })
            ->when($filters['category_id'] ?? null, function ($q, $categoryId) {
                $q->where('category_id', $categoryId);
            })
            ->when($filters['start_date'] ?? null, function ($q, $startDate) {
                $q->whereDate('transaction_date', '>=', $startDate);
            })
            ->when($filters['end_date'] ?? null, function ($q, $endDate) {
                $q->whereDate('transaction_date', '<=', $endDate);
            })
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where('notes', 'like', "%{$search}%");
            });
    }
}
