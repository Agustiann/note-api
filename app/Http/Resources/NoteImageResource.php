<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NoteImageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'file_name' => $this->file_name,
            'url' => route('notes.images.show', [
                'note' => $this->note_id,
                'image' => $this->id,
            ]),
            'mime_type' => $this->mime_type,
            'created_at' => $this->created_at,
        ];
    }
}