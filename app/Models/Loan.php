<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Loan extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_id',
        'book_id',
        'borrowed_at',
        'due_at',
        'returned_at',
        'payment_status',
    ];

    protected $appends = ['is_overdue', 'days_late'];

    protected function casts(): array
    {
        return [
            'borrowed_at' => 'date',
            'due_at'      => 'date',
            'returned_at' => 'date',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->days_late > 0;
    }

    public function getDaysLateAttribute(): int
    {
        $end = ($this->returned_at ?? now())->startOfDay();
        $due = $this->due_at->startOfDay();

        return $end->gt($due) ? (int) $due->diffInDays($end) : 0;
    }
}
