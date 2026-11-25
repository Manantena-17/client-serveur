<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use UnexpectedValueException;

class StripeWebhookController extends Controller
{
    // POST /api/v1/webhooks/stripe  (no token: protected by Stripe's signature)
    public function __invoke(Request $request, StripeService $service): JsonResponse
    {
        $secret = config('services.stripe.webhook_secret');

        if (! $secret) {
            Log::error('Stripe webhook secret is not configured.');

            return response()->json(['success' => false], HttpResponse::HTTP_INTERNAL_SERVER_ERROR);
        }

        // The RAW body is required: the signature is computed on the exact bytes
        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature'),
                $secret,
            );
        } catch (UnexpectedValueException|SignatureVerificationException $e) {
            Log::warning('Stripe webhook rejected: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Signature invalide.',
            ], HttpResponse::HTTP_BAD_REQUEST);
        }

        match ($event->type) {
            'payment_intent.succeeded'      => $service->handleSucceeded($event->data->object),
            'payment_intent.payment_failed' => $service->handleFailed($event->data->object),
            default                         => null,   // other events: ignored
        };

        return response()->json(['received' => true]);
    }
}
