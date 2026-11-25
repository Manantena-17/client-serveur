<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'member'         => new MemberResource($this->whenLoaded('member')),
            'book'           => new BookResource($this->whenLoaded('book')),
            'borrowed_at'    => $this->borrowed_at?->toDateString(),
            'due_at'         => $this->due_at?->toDateString(),
            'returned_at'    => $this->returned_at?->toDateString(),
            'status'         => $this->returned_at ? 'returned' : 'borrowed',
            'payment_status' => $this->payment_status,
            'is_overdue'     => $this->is_overdue,
            'days_late'      => $this->days_late,
            'created_at'     => $this->created_at?->toIso8601String(),
        ];
    }
}
