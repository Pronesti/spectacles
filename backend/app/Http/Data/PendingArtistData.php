<?php

namespace App\Http\Data;

use Spatie\LaravelData\Data;

class PendingArtistData extends Data
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $type,
        public readonly ?string $disambiguation,
        public readonly ?string $selection,
    ) {}

    /**
     * @return array{name: string, type: ?string, disambiguation: ?string}
     */
    public function attributes(): array
    {
        return ['name' => $this->name, 'type' => $this->type, 'disambiguation' => $this->disambiguation];
    }
}
