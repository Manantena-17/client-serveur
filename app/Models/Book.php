<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'title',
    'author_id',
    'isbn',
    'publication_year',
    'summary',
    'summary_generated_by',
    'summary_generated_at',
    'total_copies',
    'available_copies',
    'category_id',
])]
class Book extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'summary_generated_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
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
