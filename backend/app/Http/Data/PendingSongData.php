<?php

namespace App\Http\Data;

use Spatie\LaravelData\Data;

class PendingSongData extends Data
{
    public function __construct(
        public readonly string $title,
        public readonly ?string $disambiguation,
        public readonly ?string $selection,
    ) {}

    /**
     * @return array{title: string, disambiguation: ?string}
     */
    public function attributes(): array
    {
        return ['title' => $this->title, 'disambiguation' => $this->disambiguation];
    }
}
