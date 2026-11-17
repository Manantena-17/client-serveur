<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMemberRequest;
use App\Http\Requests\UpdateMemberRequest;
use App\Http\Resources\MemberResource;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class MemberController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $members = Member::query()
            ->when($request->query('type'), fn ($q, $type) => $q->where('type', $type))
            ->when($request->query('search'), function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(15);

        return MemberResource::collection($members);
    }

    public function store(StoreMemberRequest $request): JsonResponse
    {
        $member = Member::query()->create($request->validated());

        return (new MemberResource($member))
            ->response()
            ->setStatusCode(HttpResponse::HTTP_CREATED);
    }

    public function show(Member $member): MemberResource
    {
        return new MemberResource($member);
    }

    public function update(UpdateMemberRequest $request, Member $member): MemberResource|JsonResponse
    {
        $data = $request->validated();

        // A member cannot become a student if they hold more loans than the student limit
        if (($data['type'] ?? null) === 'student'
            && $member->activeLoans()->count() > Member::MAX_LOANS['student']) {
            return response()->json([
                'message' => 'Cannot change to student: the member has more active loans than allowed.',
            ], HttpResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $member->update($data);

        return new MemberResource($member->refresh());
    }

    public function destroy(Member $member): Response|JsonResponse
    {
        // Business rule: cannot delete a member with active loans
        if ($member->activeLoans()->exists()) {
            return response()->json([
                'message' => 'Cannot delete this member: they have active loans.',
            ], HttpResponse::HTTP_CONFLICT);
        }

        // Loan history also blocks deletion (restrictOnDelete on the foreign key)
        if ($member->loans()->exists()) {
            return response()->json([
                'message' => 'Cannot delete this member: they have loan history.',
            ], HttpResponse::HTTP_CONFLICT);
        }

        $member->delete();

        return response()->noContent();
    }
}
