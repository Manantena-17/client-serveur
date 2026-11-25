<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Member extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $fillable = [
        'name',
        'email',
        'type',
        'password',
        'subscription_status',
        'role',              // ← AJOUTÉ
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public const MAX_LOANS = [
        'student' => 3,
        'teacher' => 6,
        'staff'   => 6,     // ← AJOUTÉ (sinon maxLoans() = 0 pour staff)
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
        return self::MAX_LOANS[$this->type] ?? 0;
    }

    public function canBorrow(): bool
    {
        return $this->activeLoans()->count() < $this->maxLoans();
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}
