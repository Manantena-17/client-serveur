<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Stripe\Exception\ApiErrorException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class SubscriptionController extends Controller
{
    // POST /api/v1/subscriptions/payment-intent
    public function paymentIntent(Request $request, StripeService $stripe): JsonResponse
    {
        $member = $request->user();

        if ($member->isPremium()) {
            return response()->json([
                'success' => false,
                'message' => 'Vous avez déjà un abonnement premium actif.',
                'data'    => ['premium_until' => $member->premium_until?->toIso8601ZuluString()],
            ], HttpResponse::HTTP_CONFLICT);
        }

        try {
            $intent = $stripe->createPremiumPaymentIntent($member);
        } catch (ApiErrorException|RuntimeException $e) {
            Log::error('Stripe PaymentIntent creation failed: ' . $e->getMessage(), ['member_id' => $member->id]);

            return response()->json([
                'success' => false,
                'message' => "Le paiement n'a pas pu être initialisé. Réessayez plus tard.",
            ], HttpResponse::HTTP_BAD_GATEWAY);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'payment_intent_id' => $intent->id,
                'client_secret'     => $intent->client_secret,
                'amount'            => $intent->amount,
                'currency'          => $intent->currency,
                'status'            => $intent->status,
            ],
        ], HttpResponse::HTTP_CREATED);
    }

    // GET /api/v1/subscriptions/status
    public function status(Request $request): JsonResponse
    {
        $member = $request->user();
        $active = $member->activeLoans()->count();
        $limit  = $member->maxLoans();   // null = unlimited

        return response()->json([
            'success' => true,
            'data'    => [
                'subscription_status' => $member->isPremium() ? 'premium' : 'free',
                'is_premium'          => $member->isPremium(),
                'premium_until'       => $member->premium_until?->toIso8601ZuluString(),
                'active_loans'        => $active,
                'loan_limit'          => $limit,
                'remaining_loans'     => $limit === null ? null : max(0, $limit - $active),
            ],
        ]);
    }
}
