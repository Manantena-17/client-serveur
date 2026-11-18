<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'email', 'type'])]
class Member extends Model
{
    use HasFactory;                                        // 2. le trait

    public const MAX_LOANS = [
        'student' => 3,
        'teacher' => 6,
    ];

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function activeLoans(): HasMany
    {
        return $this->loans()->whereNull('returned_at');
    }

    public function maxLoans(): int
    {
        return self::MAX_LOANS[$this->type];
    }

    public function canBorrow(): bool
    {
        return $this->activeLoans()->count() < $this->maxLoans();
    }
}
