<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceResource extends JsonResource
{
    /**
     * リソースを配列へ変換
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'date' => $this->date,
            'clock_in_time' => $this->clock_in_time,
            'clock_out_time' => $this->clock_out_time,
            'comment' => $this->comment,
            'user' => $this->whenLoaded('user'),
            'break_records' => $this->whenLoaded('break_records'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
