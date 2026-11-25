<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'title'            => $this->title,
            'author_id'        => $this->author_id,
            'author'           => new AuthorResource($this->whenLoaded('author')),
            'isbn'             => $this->isbn,
            'publication_year' => $this->publication_year,
            'summary'          => $this->summary,
            'total_copies'     => $this->total_copies,
            'available_copies' => $this->available_copies,
            'is_available'     => $this->available_copies > 0,
            'loans_count'      => $this->whenCounted('loans'),
            'category'         => new CategoryResource($this->whenLoaded('category')),
            'created_at'       => $this->created_at?->toIso8601String(),
            'updated_at'       => $this->updated_at?->toIso8601String(),
        ];
    }
}
