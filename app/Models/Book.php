<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Book extends Model
{
    protected $fillable = [
        'title',
        'author',
        'isbn',
        'publication_year',
        'total_copies',
        'available_copies',
        'category_id',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function activeLoans(): HasMany
    {
        return $this->loans()->whereNull('returned_at');
    }

    public function isAvailable(): bool
    {
        return $this->available_copies > 0;
    }
}
