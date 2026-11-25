<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'book_id'      => $this->id,
            'title'        => $this->title,
            'summary'      => $this->summary,
            'generated_by' => $this->summary_generated_by,
            'generated_at' => $this->summary_generated_at?->toIso8601ZuluString(),
        ];
    }
}
