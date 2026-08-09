<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\App;

class CategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->localized('name'),
            'description' => $this->localized('description'),
            'slug' => $this->slug,
            'image' => $this->getFirstMediaUrl('categories') ?: $this->image,
            'breeds' => $this->whenLoaded('breeds', function () {
                return BreedResource::collection($this->breeds);
            }),
        ];
    }

    protected function localized(string $attribute): mixed
    {
        if (App::getLocale() === 'ar') {
            $arabic = $this->{"{$attribute}_ar"};

            if (filled($arabic)) {
                return $arabic;
            }
        }

        return $this->{$attribute};
    }
}
