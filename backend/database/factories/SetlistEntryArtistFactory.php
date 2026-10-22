<?php

namespace Database\Factories;

use App\Models\Artist;
use App\Models\SetlistEntry;
use App\Models\SetlistEntryArtist;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SetlistEntryArtist> */
class SetlistEntryArtistFactory extends Factory
{
    protected $model = SetlistEntryArtist::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'setlist_entry_id' => SetlistEntry::factory(),
            'artist_id' => Artist::factory(),
        ];
    }
}
