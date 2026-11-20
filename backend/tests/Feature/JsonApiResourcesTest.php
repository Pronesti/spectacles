<?php

namespace Tests\Feature;

use App\Http\Resources\AttendanceResource;
use App\Http\Resources\EventResource;
use App\Http\Resources\PublicJsonApiResource;
use App\Http\Resources\SetlistEntryResource;
use App\Models\Artist;
use App\Models\ArtistProviderId;
use App\Models\Attendance;
use App\Models\AttendanceInvitation;
use App\Models\AttendanceLink;
use App\Models\AttendanceMember;
use App\Models\AttendanceMemberSeat;
use App\Models\Event;
use App\Models\EventArtist;
use App\Models\EventProviderReference;
use App\Models\EventTour;
use App\Models\SetlistEntry;
use App\Models\SetlistEntryArtist;
use App\Models\Song;
use App\Models\SongLink;
use App\Models\SongProviderId;
use App\Models\Tour;
use App\Models\TourArtist;
use App\Models\TourProviderId;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueProviderId;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class JsonApiResourcesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  class-string<Model>  $modelClass
     */
    #[DataProvider('entities')]
    public function test_entity_resources_use_public_identity_and_explicit_attributes(string $modelClass): void
    {
        $model = Factory::factoryForModel($modelClass)->createOne();
        $model->setAttribute('private_extra', 'must not leak');
        $resource = $model->toResource();
        $request = Request::create('/resources');

        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $resource->toResponse($request);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $document = $response->getData(true);

        expect($resource::class)->toBe('App\\Http\\Resources\\'.class_basename($modelClass).'Resource')
            ->and($response->headers->get('Content-Type'))->toBe('application/vnd.api+json')
            ->and(data_get($document, 'data.id'))->toBe($model->getAttribute('external_id'))
            ->and(data_get($document, 'data.type'))->toBe($model->getTable())
            ->and($queries)->toBeEmpty()
            ->and($this->arrayAt($document, 'data'))->not->toHaveKey('relationships')
            ->and($this->arrayAt($document))->not->toHaveKey('included');
        $attributes = $this->arrayAt(data_get($document, 'data.attributes', []));
        expect($attributes)->not->toHaveKey('private_extra')->not->toHaveKey('external_id')->not->toHaveKey('password')->not->toHaveKey('remember_token')->not->toHaveKey('email');
        foreach (array_keys($model->getAttributes()) as $key) {
            if ($key === 'id' || (str_ends_with($key, '_id') && ! in_array($key, ['provider_resource_id', 'external_provider_id'], true))) {
                expect($attributes)->not->toHaveKey($key);
            }
        }
        $createdAt = $model->getAttribute('created_at');
        if (! $createdAt instanceof CarbonInterface) {
            $this->fail('The model must have a created_at timestamp.');
        }
        expect($attributes['created_at'])->toBe($createdAt->toJSON());
        if ($model->getAttribute('external_provider_id') !== null) {
            expect($attributes['external_provider_id'])->toBe($model->getAttribute('external_provider_id'));
        }
        if ($model->getAttribute('provider_resource_id') !== null) {
            expect($attributes['provider_resource_id'])->toBe($model->getAttribute('provider_resource_id'));
        }
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    #[DataProvider('entities')]
    public function test_declared_relationships_use_uuid_linkage_and_safe_included_resources(string $modelClass): void
    {
        $model = Factory::factoryForModel($modelClass)->createOne();
        $resource = $model->toResource();
        if (! $resource instanceof PublicJsonApiResource) {
            $this->fail('The model must use a public JSON:API resource.');
        }
        $relations = array_keys($resource->relationships);
        $model->load($relations);
        $request = Request::create('/resources', 'GET', ['include' => implode(',', $relations)]);
        $document = $resource->toResponse($request)->getData(true);

        foreach ($relations as $relation) {
            expect($this->arrayAt($document, 'data.relationships'))->toHaveKey($relation);
            $related = $model->getRelation($relation);
            $relationship = $this->arrayAt($document, 'data.relationships.'.$relation);
            expect($relationship)->toHaveKey('data');
            $linkage = $relationship['data'];
            if ($related instanceof Model) {
                expect(data_get($linkage, 'id'))->toBe($related->getAttribute('external_id'))
                    ->and(data_get($linkage, 'type'))->toBe($related->getTable());
            } elseif ($related === null) {
                expect($linkage)->toBeNull();
            } else {
                if (! $related instanceof Collection) {
                    $this->fail('A to-many relationship must load a collection.');
                }
                expect(array_column($this->arrayAt($linkage), 'id'))->toBe($related->pluck('external_id')->all());
            }
        }
        $this->assertPublicResourceObjects($this->arrayAt($document));
    }

    public function test_nested_includes_preserve_setlist_values_and_public_identifiers(): void
    {
        $entry = SetlistEntry::factory()->tape()->create(['position' => 3]);
        $request = Request::create('/resources', 'GET', ['include' => 'attendance.owner,song']);
        $document = (new SetlistEntryResource($entry))->toResponse($request)->getData(true);
        $attendance = $this->present($entry->attendance);

        expect(data_get($document, 'data.attributes.is_tape'))->toBeTrue()
            ->and(data_get($document, 'data.attributes.position'))->toBe(3)
            ->and(data_get($document, 'data.relationships.attendance.data.id'))->toBe($attendance->external_id);
        $included = collect($this->arraysAt($document, 'included'));
        $owner = $this->arrayAt($included->firstWhere('type', 'users'));
        expect($owner['id'])->toBe($this->present($attendance->owner)->external_id)
            ->and($this->arrayAt($included->firstWhere('type', 'songs'))['id'])->toBe($this->present($entry->song)->external_id);
        $this->assertPublicResourceObjects($this->arrayAt($document));
    }

    public function test_collections_sparse_fields_and_lineup_order_use_native_json_api_format(): void
    {
        $event = Event::factory()->create();
        $second = EventArtist::factory()->create(['event_id' => $event->id, 'position' => 2]);
        $first = EventArtist::factory()->create(['event_id' => $event->id, 'position' => 1]);
        $request = Request::create('/resources', 'GET', [
            'include' => 'artistLinks.artist',
            'fields' => ['events' => 'title,artistLinks,id,venue_id', 'artists' => 'name'],
        ]);
        $document = EventResource::collection(collect([$event]))->toResponse($request)->getData(true);

        expect(data_get($document, 'data.0.id'))->toBe($event->external_id)
            ->and(data_get($document, 'data.0.attributes'))->toBe(['title' => $event->title])
            ->and(array_column($this->arrayAt($document, 'data.0.relationships.artistLinks.data'), 'id'))->toBe([$first->external_id, $second->external_id]);
        $this->assertPublicResourceObjects($this->arrayAt($document));
    }

    public function test_null_and_empty_relationships_have_json_api_linkage(): void
    {
        $event = Event::factory()->create(['venue_id' => null]);
        $request = Request::create('/resources', 'GET', ['include' => 'venue,artistLinks']);
        $document = (new EventResource($event))->toResponse($request)->getData(true);
        $venue = $this->arrayAt($document, 'data.relationships.venue');
        expect($venue)->toHaveKey('data')
            ->and($venue['data'])->toBeNull()
            ->and(data_get($document, 'data.relationships.artistLinks.data'))->toBe([]);
    }

    public function test_preloaded_relationship_constraints_are_preserved_without_queries(): void
    {
        $attendance = Attendance::factory()->create();
        $first = SetlistEntry::factory()->create(['attendance_id' => $attendance->id, 'position' => 1]);
        $second = SetlistEntry::factory()->create(['attendance_id' => $attendance->id, 'position' => 2]);
        SetlistEntry::factory()->create(['attendance_id' => $attendance->id, 'position' => 3]);
        $attendance->load(['entries' => fn ($query) => $query
            ->whereKey([$first->id, $second->id])->reorder()->orderByDesc('position')]);
        $request = Request::create('/resources', 'GET', ['include' => 'entries']);

        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $document = $attendance->toResource()->toResponse($request)->getData(true);
            expect(DB::getQueryLog())->toBeEmpty();
        } finally {
            DB::disableQueryLog();
        }

        expect(array_column($this->arrayAt($document, 'data.relationships.entries.data'), 'id'))->toBe([$second->external_id, $first->external_id]);
        $this->assertPublicResourceObjects($this->arrayAt($document));
    }

    public function test_paginated_collections_use_native_json_api_structure(): void
    {
        Attendance::factory()->count(3)->create();
        $resourceClass = AttendanceResource::class;
        $document = $resourceClass::collection(Attendance::query()->orderBy('id')->paginate(2))
            ->toResponse(Request::create('/resources'))->getData(true);

        expect($this->arrayAt($document, 'data'))->toHaveCount(2)
            ->and(data_get($document, 'meta.total'))->toBe(3)
            ->and($this->arrayAt($document, 'links'))->toHaveKey('next');
        $this->assertPublicResourceObjects($this->arrayAt($document));
    }

    /**
     * @param  array<array-key, mixed>  $document
     */
    private function assertPublicResourceObjects(array $document): void
    {
        $data = $this->arrayAt($document, 'data');
        $objects = isset($data['id']) ? [$data] : $this->arraysAt($data);
        foreach ([...$objects, ...$this->arraysAt($document['included'] ?? [])] as $object) {
            expect(Str::isUuid($object['id']))->toBeTrue();
            foreach (array_keys($this->arrayAt($object['attributes'] ?? [])) as $key) {
                expect($key)->toBeString()
                    ->and($key === 'id' || $key === 'external_id' || str_ends_with($key, '_id') && ! in_array($key, ['provider_resource_id', 'external_provider_id'], true))->toBeFalse();
            }
            foreach ($this->arraysAt($object['relationships'] ?? []) as $relationship) {
                $linkage = $relationship['data'] ?? [];
                $identifiers = is_array($linkage) && isset($linkage['id']) ? [$linkage] : $this->arraysAt($linkage);
                foreach ($identifiers as $identifier) {
                    expect(Str::isUuid($identifier['id']))->toBeTrue();
                }
            }
        }
    }

    /**
     * @return list<array{class-string<Model>}>
     */
    public static function entities(): array
    {
        return array_map(fn (string $modelClass): array => [$modelClass], [
            Artist::class, ArtistProviderId::class, Attendance::class, AttendanceInvitation::class,
            AttendanceLink::class, AttendanceMember::class, AttendanceMemberSeat::class,
            Event::class, EventArtist::class, EventProviderReference::class, EventTour::class,
            SetlistEntry::class, SetlistEntryArtist::class, Song::class, SongLink::class, SongProviderId::class,
            Tour::class, TourArtist::class, TourProviderId::class, User::class, Venue::class, VenueProviderId::class,
        ]);
    }
}
