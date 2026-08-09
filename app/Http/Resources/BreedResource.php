<?php

namespace App\Http\Resources;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\App;

/**
 * @property string $name
 * @property string $description
 * @property string $image
 * @property Category $category
 */
class BreedResource extends JsonResource
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
            'image' => $this->getFirstMediaUrl('breeds') ?: $this->image,
            'category' => $this->whenLoaded('category', fn () => CategoryResource::make($this->category)),
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
