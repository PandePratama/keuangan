<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Debt extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'party_name',
        'type', // 'debt' (utang) or 'receivable' (piutang)
        'total_amount',
        'remaining_amount',
        'due_date',
        'status', // 'unpaid', 'partially_paid', 'paid'
        'notes',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'due_date' => 'date',
    ];

    protected $appends = [
        'is_overdue',
        'is_due_soon',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(DebtPayment::class);
    }

    public function getIsOverdueAttribute(): bool
    {
        if ($this->status === 'paid' || ! $this->due_date) {
            return false;
        }

        return $this->due_date->isPast() && ! $this->due_date->isToday();
    }

    public function getIsDueSoonAttribute(): bool
    {
        if ($this->status === 'paid' || ! $this->due_date) {
            return false;
        }

        return $this->due_date->isFuture() && $this->due_date->diffInDays(Carbon::today()) <= 3;
    }

    public function scopeDebts(Builder $query): Builder
    {
        return $query->where('type', 'debt');
    }

    public function scopeReceivables(Builder $query): Builder
    {
        return $query->where('type', 'receivable');
    }
}
