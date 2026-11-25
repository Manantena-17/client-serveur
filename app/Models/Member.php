<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Cashier\Billable;
use Laravel\Sanctum\HasApiTokens;

class Member extends Authenticatable
{
    use HasApiTokens, HasFactory, Billable;

    protected $fillable = [
        'name',
        'email',
        'type',
        'password',
        'subscription_status',
        'premium_until',
        'role',
    ];

    protected $hidden = ['password'];

    // ✅ Une seule déclaration de casts() (fusion des deux)
    protected function casts(): array
    {
        return [
            'password'      => 'hashed',
            'premium_until' => 'datetime',
            'trial_ends_at' => 'datetime', // requis par Cashier
        ];
    }

    public const FREE_MAX_LOANS = 2;

    // ---------- Relations ----------

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

    // ---------- Rôles / Premium ----------

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Premium = flag admin valide OU abonnement Stripe actif.
     */
    public function isPremium(): bool
    {
        if (
            $this->subscription_status === 'premium'
            && ($this->premium_until === null || $this->premium_until->isFuture())
        ) {
            return true;
        }

        return $this->subscribed('default'); // Stripe via Cashier
    }

    // ---------- Limites de prêts ----------

    /**
     * @return int|null  null = illimité (premium)
     */
    public function maxLoans(): ?int
    {
        return $this->isPremium() ? null : self::FREE_MAX_LOANS;
    }

    public function canBorrow(): bool
    {
        $max = $this->maxLoans();

        return $max === null || $this->activeLoans()->count() < $max;
    }

    // ---------- Stripe ----------

    public function checkoutPremium(string $priceId): \Laravel\Cashier\Checkout
    {
        return $this->newSubscription('default', $priceId)
            ->checkout([
                'success_url'    => route('premium.success'),
                'cancel_url'     => route('premium.cancel'),
                'customer_email' => $this->email,
            ]);
    }

    public function cancelPremium(): void
    {
        $this->subscription('default')?->cancel();
    }
}
