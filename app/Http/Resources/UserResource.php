<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */

    public static $wrap = null;

    public function toArray(Request $request): array
    {
        $user = $request->user();
        $user->append('profile');

        return [
            "id" => $user->id,
            "email" => $user->email,
            "image" => $user->image,
            "role" => $user->role,
            "profile" => [
                "id" => $user->profile->id,
                "username" => $user->profile->username,
                "created_at" => $user->profile->created_at,
                "updated_at" => $user->profile->updated_at
            ]
        ];
    }
}
