<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'log_name' => $this->log_name,
            'description' => $this->description,
            'event' => $this->event,
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'causer' => $this->when($this->causer, fn () => [
                'id' => $this->causer->id,
                'name' => $this->causer->name,
            ]),
            'properties' => [
                'old' => $this->properties['old'] ?? null,
                'attributes' => $this->properties['attributes'] ?? null,
            ],
            'batch_uuid' => $this->batch_uuid,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
