<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResultResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // return parent::toArray($request);
        return [
            "id" => $this->id,
            "user_id" => $this->user_id,
            "course_id" => $this->course_id,
            "course_code" => $this->course_code,
            "course_title" => $this->course_title,
            "credit_load" => $this->credit_load,
            "quality_point" => $this->quality_point,
            "session" => $this->session,
            "score" => $this->score,
            "grade" => $this->grade,
            "remarks" => $this->remarks,
            "status" => $this->status,
            "date_of_result" => $this->date_of_result,
            "created_at" => $this->created_at,
            "updated_at" => $this->updated_at,
            "course_details" => $this->whenLoaded('course'),
            "user_info" => $this->whenLoaded('user'),
        ];
    }
}
