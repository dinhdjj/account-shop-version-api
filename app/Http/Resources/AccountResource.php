<?php

namespace App\Http\Resources;

use Illuminate\Support\Facades\Storage;

class AccountResource extends Resource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return array_merge(parent::toArray($request), [
            // Special attributes
            'price' => $this->calculateTemporaryPrice(),

            // Relationship
            'lastUpdatedEditor' => new UserResource($this->whenLoaded('lastUpdatedEditor')),
            'creator' => new UserResource($this->whenLoaded('creator')),
            'censor' => new UserResource($this->whenLoaded('censor')),
            'accountType' => new AccountTypeResource($this->whenLoaded('accountType')),
            'images' => AccountImageResource::collection($this->whenLoaded('images')),

            // Relationships contain pivot property
            'gameInfos' => GameInfoResource::collection($this->whenLoaded('gameInfos')),

            // Merge when auth can view sensitive infos
            $this->mergeWhen(
                auth()->check() && auth()->user()->can('viewSensitiveInfo', $this->resource),
                function () {
                    return [
                        'accountActions' => AccountActionResource::collection($this->whenLoaded('accountActions')),
                        'accountInfos' => AccountInfoResource::collection($this->whenLoaded('accountInfos')),
                        'password' => $this->password,
                    ];
                }
            ),
        ]);
    }
}
