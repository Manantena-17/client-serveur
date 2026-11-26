<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Member extends Authenticatable
{
    use HasApiTokens, HasFactory;

    // Free plan: 2 active loans. Premium: unlimited.
    public const FREE_MAX_LOANS = 2;

    // "role" and the Stripe columns are NOT fillable: only the server sets them
    protected $fillable = ['name', 'email', 'type', 'password', 'subscription_status'];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'password'      => 'hashed',
            'premium_until' => 'datetime',
        ];
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function activeLoans(): HasMany
    {
        return $this->loans()->whereNull('returned_at');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    // Premium = status "premium" AND not expired (null = no end date, set by an admin)
    public function isPremium(): bool
    {
        return $this->subscription_status === 'premium'
            && ($this->premium_until === null || $this->premium_until->isFuture());
    }

    // null = unlimited
    public function maxLoans(): ?int
    {
        return $this->isPremium() ? null : self::FREE_MAX_LOANS;
    }

    public function canBorrow(): bool
    {
        $max = $this->maxLoans();

        return $max === null || $this->activeLoans()->count() < $max;
    }
}
