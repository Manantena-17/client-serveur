<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Loan extends Model
{
    protected $fillable = [
        'member_id',
        'book_id',
        'borrowed_at',
        'due_at',
        'returned_at',
    ];

    protected $casts = [
        'borrowed_at' => 'date',
        'due_at'      => 'date',
        'returned_at' => 'date',
    ];

    protected $appends = ['is_overdue', 'days_late'];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    // Overdue: not returned yet and due date passed,
    // or returned after the due date
    public function getIsOverdueAttribute(): bool
    {
        return $this->days_late > 0;
    }

    public function getDaysLateAttribute(): int
    {
        $end = $this->returned_at ?? now();

        return $end->startOfDay()->gt($this->due_at->startOfDay())
            ? $this->due_at->startOfDay()->diffInDays($end->startOfDay())
            : 0;
    }
}
