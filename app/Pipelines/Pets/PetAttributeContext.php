<?php

namespace App\Pipelines\Pets;

use App\Models\Pet;
use Illuminate\Http\UploadedFile;
use LogicException;

/**
 * The passable shared by the create and update pet flows.
 *
 * The steps that translate a submitted form into column values are identical
 * for both flows, so they type hint this base class and never learn which flow
 * they are running in. `$data` is the validated request payload and is read
 * only; `$attributes` is the column bag the Normalize* steps fill in and the
 * Persist step writes.
 *
 * The uploaded files ride along on the context rather than being read from the
 * request, because a pipeline step knows nothing about HTTP.
 */
abstract class PetAttributeContext
{
    /**
     * Column values accumulated by the Normalize* steps.
     *
     * @var array<string, mixed>
     */
    protected array $attributes = [];

    /**
     * The listing, once a Persist step has produced it.
     */
    protected ?Pet $pet = null;

    /**
     * @param  array<string, mixed>  $data  The validated payload.
     * @param  list<UploadedFile>  $galleryImages
     */
    public function __construct(
        public readonly array $data,
        public readonly ?UploadedFile $featuredImage = null,
        public readonly array $galleryImages = [],
    ) {}

    /**
     * Read a validated value, using dot notation for the nested form groups.
     */
    public function input(string $key, mixed $default = null): mixed
    {
        return data_get($this->data, $key, $default);
    }

    /**
     * Record a column value for the Persist step.
     */
    public function set(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    /**
     * Record several column values at once.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function merge(array $attributes): void
    {
        $this->attributes = [...$this->attributes, ...$attributes];
    }

    /**
     * Everything the Persist step should write.
     *
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return $this->attributes;
    }

    public function setPet(Pet $pet): void
    {
        $this->pet = $pet;
    }

    /**
     * The listing the media steps act on.
     *
     * @throws LogicException When a media step runs before the pet is persisted.
     */
    public function pet(): Pet
    {
        if ($this->pet === null) {
            throw new LogicException(static::class.' has no pet yet; a Persist step must run first.');
        }

        return $this->pet;
    }
}
