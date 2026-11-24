<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\MemberResource;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $member = Member::query()->create([
            'name'     => $request->validated('name'),
            'email'    => $request->validated('email'),
            'password' => $request->validated('password'),
            'type'     => 'student',
        ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'member' => new MemberResource($member->refresh()),
                'token'  => $member->createToken('api')->plainTextToken,
            ],
        ], HttpResponse::HTTP_CREATED);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $member = Member::query()->where('email', $request->validated('email'))->first();

        // Same message for unknown email and wrong password
        if (! $member
            || ! $member->password
            || ! Hash::check($request->validated('password'), $member->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Identifiants invalides.',
            ], HttpResponse::HTTP_UNAUTHORIZED);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'member' => new MemberResource($member),
                'token'  => $member->createToken('api')->plainTextToken,
            ],
        ]);
    }

    public function me(Request $request): MemberResource
    {
        return new MemberResource($request->user());
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Déconnecté.',
        ]);
    }
    public function updateMe(UpdateProfileRequest $request): MemberResource
    {
        $member = $request->user();
        $member->update($request->validated());

        return new MemberResource($member->refresh());
    }
}
