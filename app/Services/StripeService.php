<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Stripe\PaymentIntent;
use Stripe\StripeClient;
use Stripe\Exception\InvalidRequestException;

class StripeService
{
    public function createPremiumPaymentIntent(Member $member): PaymentIntent
    {
        $stripe = $this->client();

        $customerId = $member->stripe_customer_id ?: $this->createCustomer($stripe, $member);

        try {
            return $this->createIntent($stripe, $member, $customerId);
        } catch (InvalidRequestException $e) {
            // Customer supprimé ou inexistant côté Stripe : on le recrée et on réessaie une fois
            if ($e->getStripeCode() !== 'resource_missing' || ! str_contains($e->getMessage(), 'customer')) {
                throw $e;
            }

            Log::warning('Stripe customer missing, recreating', [
                'member_id'   => $member->id,
                'old_customer' => $customerId,
            ]);

            $member->forceFill(['stripe_customer_id' => null])->save();

            $customerId = $this->createCustomer($stripe, $member);

            return $this->createIntent($stripe, $member, $customerId);
        }
    }

    private function createCustomer(StripeClient $stripe, Member $member): string
    {
        $customer = $stripe->customers->create([
            'email'    => $member->email,
            'name'     => $member->name,
            'metadata' => ['member_id' => (string) $member->id],
        ]);

        $member->forceFill(['stripe_customer_id' => $customer->id])->save();

        return $customer->id;
    }

    private function createIntent(StripeClient $stripe, Member $member, string $customerId): PaymentIntent
    {
        return $stripe->paymentIntents->create([
            'amount'   => (int) config('services.stripe.premium_amount'),
            'currency' => strtolower((string) config('services.stripe.currency', 'eur')),
            'customer' => $customerId,
            'automatic_payment_methods' => ['enabled' => true],
            'description' => 'Abonnement premium bibliothèque (1 mois)',
            'metadata' => [
                'member_id' => (string) $member->id,
                'plan'      => 'premium',
            ],
        ]);
    }

    /** Called by the webhook: unlocks premium. Safe to run twice for the same payment. */
    public function handleSucceeded(PaymentIntent $intent): void
    {
        $memberId = $intent->metadata['member_id'] ?? null;
        $member   = $memberId ? Member::query()->find($memberId) : null;

        if (! $member) {
            Log::warning('Stripe payment without a known member', ['payment_intent' => $intent->id]);

            return;
        }

        if ((int) $intent->amount_received < (int) config('services.stripe.premium_amount')) {
            Log::warning('Stripe payment amount too low', ['payment_intent' => $intent->id]);

            return;
        }

        DB::transaction(function () use ($intent, $member) {
            $payment = Payment::query()
                ->where('stripe_payment_intent_id', $intent->id)
                ->lockForUpdate()
                ->first();

            // Stripe resends events: a payment already processed is ignored
            if ($payment?->status === 'succeeded') {
                return;
            }

            $payment ??= new Payment(['stripe_payment_intent_id' => $intent->id]);
            $payment->fill([
                'member_id' => $member->id,
                'amount'    => $intent->amount_received,
                'currency'  => $intent->currency,
                'status'    => 'succeeded',
                'paid_at'   => now(),
            ])->save();

            $member = Member::query()->lockForUpdate()->findOrFail($member->id);

            // Renewal: add one month after the current end date
            $start = ($member->isPremium() && $member->premium_until) ? $member->premium_until : now();

            $member->forceFill([
                'subscription_status' => 'premium',
                'premium_until'       => $start->copy()->addMonth(),
            ])->save();
        });
    }

    public function handleFailed(PaymentIntent $intent): void
    {
        $payment = Payment::query()->firstOrNew(['stripe_payment_intent_id' => $intent->id]);

        if ($payment->status === 'succeeded') {
            return;
        }

        $payment->fill([
            'member_id' => $intent->metadata['member_id'] ?? null,
            'amount'    => $intent->amount,
            'currency'  => $intent->currency,
            'status'    => 'failed',
        ])->save();
    }

    private function client(): StripeClient
    {
        $secret = config('services.stripe.secret');

        if (! $secret) {
            throw new RuntimeException('Stripe is not configured.');
        }

        return new StripeClient($secret);
    }
}
