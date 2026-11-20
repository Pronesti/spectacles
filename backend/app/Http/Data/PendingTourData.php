<?php

namespace App\Http\Data;

use Spatie\LaravelData\Data;

class PendingTourData extends Data
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $selection,
    ) {}

    /**
     * @return array{name: string}
     */
    public function attributes(): array
    {
        return ['name' => $this->name];
    }
}
