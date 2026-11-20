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

        $this->assertSame('App\\Http\\Resources\\'.class_basename($modelClass).'Resource', $resource::class);
        $this->assertSame('application/vnd.api+json', $response->headers->get('Content-Type'));
        $this->assertSame($model->getAttribute('external_id'), data_get($document, 'data.id'));
        $this->assertSame($model->getTable(), data_get($document, 'data.type'));
        $this->assertSame([], $queries);
        $this->assertArrayNotHasKey('relationships', $this->arrayAt($document, 'data'));
        $this->assertArrayNotHasKey('included', $this->arrayAt($document));
        $attributes = $this->arrayAt(data_get($document, 'data.attributes', []));
        $this->assertArrayNotHasKey('private_extra', $attributes);
        $this->assertArrayNotHasKey('external_id', $attributes);
        $this->assertArrayNotHasKey('password', $attributes);
        $this->assertArrayNotHasKey('remember_token', $attributes);
        $this->assertArrayNotHasKey('email', $attributes);
        foreach (array_keys($model->getAttributes()) as $key) {
            if ($key === 'id' || (str_ends_with($key, '_id') && ! in_array($key, ['provider_resource_id', 'external_provider_id'], true))) {
                $this->assertArrayNotHasKey($key, $attributes);
            }
        }
        $createdAt = $model->getAttribute('created_at');
        $this->assertInstanceOf(CarbonInterface::class, $createdAt);
        $this->assertSame($createdAt->toJSON(), $attributes['created_at']);
        if ($model->getAttribute('external_provider_id') !== null) {
            $this->assertSame($model->getAttribute('external_provider_id'), $attributes['external_provider_id']);
        }
        if ($model->getAttribute('provider_resource_id') !== null) {
            $this->assertSame($model->getAttribute('provider_resource_id'), $attributes['provider_resource_id']);
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
        $this->assertInstanceOf(PublicJsonApiResource::class, $resource);
        $relations = array_keys($resource->relationships);
        $model->load($relations);
        $request = Request::create('/resources', 'GET', ['include' => implode(',', $relations)]);
        $document = $resource->toResponse($request)->getData(true);

        foreach ($relations as $relation) {
            $this->assertArrayHasKey($relation, $this->arrayAt($document, 'data.relationships'));
            $related = $model->getRelation($relation);
            $relationship = $this->arrayAt($document, 'data.relationships.'.$relation);
            $this->assertArrayHasKey('data', $relationship);
            $linkage = $relationship['data'];
            if ($related instanceof Model) {
                $this->assertSame($related->getAttribute('external_id'), data_get($linkage, 'id'));
                $this->assertSame($related->getTable(), data_get($linkage, 'type'));
            } elseif ($related === null) {
                $this->assertNull($linkage);
            } else {
                $this->assertInstanceOf(Collection::class, $related);
                $this->assertSame($related->pluck('external_id')->all(), array_column($this->arrayAt($linkage), 'id'));
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

        $this->assertTrue(data_get($document, 'data.attributes.is_tape'));
        $this->assertSame(3, data_get($document, 'data.attributes.position'));
        $this->assertSame($attendance->external_id, data_get($document, 'data.relationships.attendance.data.id'));
        $included = collect($this->arraysAt($document, 'included'));
        $owner = $this->arrayAt($included->firstWhere('type', 'users'));
        $this->assertSame($this->present($attendance->owner)->external_id, $owner['id']);
        $this->assertSame($this->present($entry->song)->external_id, $this->arrayAt($included->firstWhere('type', 'songs'))['id']);
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

        $this->assertSame($event->external_id, data_get($document, 'data.0.id'));
        $this->assertSame(['title' => $event->title], data_get($document, 'data.0.attributes'));
        $this->assertSame([$first->external_id, $second->external_id],
            array_column($this->arrayAt($document, 'data.0.relationships.artistLinks.data'), 'id'));
        $this->assertPublicResourceObjects($this->arrayAt($document));
    }

    public function test_null_and_empty_relationships_have_json_api_linkage(): void
    {
        $event = Event::factory()->create(['venue_id' => null]);
        $request = Request::create('/resources', 'GET', ['include' => 'venue,artistLinks']);
        $document = (new EventResource($event))->toResponse($request)->getData(true);
        $venue = $this->arrayAt($document, 'data.relationships.venue');
        $this->assertArrayHasKey('data', $venue);
        $this->assertNull($venue['data']);
        $this->assertSame([], data_get($document, 'data.relationships.artistLinks.data'));
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
            $this->assertSame([], DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }

        $this->assertSame([$second->external_id, $first->external_id],
            array_column($this->arrayAt($document, 'data.relationships.entries.data'), 'id'));
        $this->assertPublicResourceObjects($this->arrayAt($document));
    }

    public function test_paginated_collections_use_native_json_api_structure(): void
    {
        Attendance::factory()->count(3)->create();
        $resourceClass = AttendanceResource::class;
        $document = $resourceClass::collection(Attendance::query()->orderBy('id')->paginate(2))
            ->toResponse(Request::create('/resources'))->getData(true);

        $this->assertCount(2, $this->arrayAt($document, 'data'));
        $this->assertSame(3, data_get($document, 'meta.total'));
        $this->assertArrayHasKey('next', $this->arrayAt($document, 'links'));
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
            $this->assertTrue(Str::isUuid($object['id']));
            foreach (array_keys($this->arrayAt($object['attributes'] ?? [])) as $key) {
                $this->assertIsString($key);
                $this->assertFalse($key === 'id' || $key === 'external_id'
                    || (str_ends_with($key, '_id') && ! in_array($key, ['provider_resource_id', 'external_provider_id'], true)), $key);
            }
            foreach ($this->arraysAt($object['relationships'] ?? []) as $relationship) {
                $linkage = $relationship['data'] ?? [];
                $identifiers = is_array($linkage) && isset($linkage['id']) ? [$linkage] : $this->arraysAt($linkage);
                foreach ($identifiers as $identifier) {
                    $this->assertTrue(Str::isUuid($identifier['id']));
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
