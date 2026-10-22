<?php

namespace Tests\Feature;

use App\Http\Resources\EventResource;
use App\Http\Resources\SetlistEntryResource;
use App\Models\Attendance;
use App\Models\Event;
use App\Models\EventArtist;
use App\Models\SetlistEntry;
use App\Models\User;
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

    #[DataProvider('entities')]
    public function test_entity_resources_use_public_identity_and_explicit_attributes(string $entity): void
    {
        $modelClass = 'App\\Models\\'.$entity;
        $model = $modelClass::factory()->create();
        $model->setAttribute('private_extra', 'must not leak');
        $resource = $model->toResource();
        $request = Request::create('/resources');

        DB::enableQueryLog();
        $response = $resource->toResponse($request);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $document = $response->getData(true);

        $this->assertSame('App\\Http\\Resources\\'.$entity.'Resource', $resource::class);
        $this->assertSame('application/vnd.api+json', $response->headers->get('Content-Type'));
        $this->assertSame($model->external_id, $document['data']['id']);
        $this->assertSame($model->getTable(), $document['data']['type']);
        $this->assertSame([], $queries);
        $this->assertArrayNotHasKey('relationships', $document['data']);
        $this->assertArrayNotHasKey('included', $document);
        $attributes = $document['data']['attributes'] ?? [];
        $this->assertArrayNotHasKey('private_extra', $attributes);
        $this->assertArrayNotHasKey('external_id', $attributes);
        $this->assertArrayNotHasKey('password', $attributes);
        $this->assertArrayNotHasKey('remember_token', $attributes);
        $this->assertArrayNotHasKey('email', $attributes);
        foreach (array_keys($model->getAttributes()) as $key) {
            if ($key === 'id' || (str_ends_with($key, '_id') && $key !== 'provider_resource_id')) {
                $this->assertArrayNotHasKey($key, $attributes);
            }
        }
        $this->assertSame($model->created_at->toJSON(), $attributes['created_at']);
        if ($model->getAttribute('provider_resource_id') !== null) {
            $this->assertSame($model->provider_resource_id, $attributes['provider_resource_id']);
        }
    }

    #[DataProvider('entities')]
    public function test_declared_relationships_use_uuid_linkage_and_safe_included_resources(string $entity): void
    {
        $modelClass = 'App\\Models\\'.$entity;
        $model = $modelClass::factory()->create();
        $resource = $model->toResource();
        $relations = array_keys($resource->relationships);
        $request = Request::create('/resources', 'GET', ['include' => implode(',', $relations)]);
        $viewer = Attendance::first()?->owner ?? User::factory()->create();
        $request->setUserResolver(fn () => $viewer);
        $document = $resource->toResponse($request)->getData(true);

        foreach ($relations as $relation) {
            $this->assertArrayHasKey($relation, $document['data']['relationships']);
            $related = $model->getRelation($relation);
            $linkage = $document['data']['relationships'][$relation]['data'];
            if ($related instanceof Model) {
                $this->assertSame($related->external_id, $linkage['id']);
                $this->assertSame($related->getTable(), $linkage['type']);
            } elseif ($related === null) {
                $this->assertNull($linkage);
            } else {
                $this->assertSame($related->pluck('external_id')->all(), array_column($linkage, 'id'));
            }
        }
        $this->assertPublicResourceObjects($document);
    }

    public function test_nested_includes_preserve_setlist_values_and_public_identifiers(): void
    {
        $entry = SetlistEntry::factory()->tape()->create(['position' => 3]);
        $request = Request::create('/resources', 'GET', ['include' => 'attendance.owner,song']);
        $request->setUserResolver(fn () => $entry->attendance->owner);
        $document = (new SetlistEntryResource($entry))->toResponse($request)->getData(true);

        $this->assertTrue($document['data']['attributes']['is_tape']);
        $this->assertSame(3, $document['data']['attributes']['position']);
        $this->assertSame($entry->attendance->external_id, $document['data']['relationships']['attendance']['data']['id']);
        $included = collect($document['included']);
        $owner = $included->firstWhere('type', 'users');
        $this->assertSame($entry->attendance->owner->external_id, $owner['id']);
        $this->assertSame($entry->song->external_id, $included->firstWhere('type', 'songs')['id']);
        $this->assertPublicResourceObjects($document);
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

        $this->assertSame($event->external_id, $document['data'][0]['id']);
        $this->assertSame(['title' => $event->title], $document['data'][0]['attributes']);
        $this->assertSame([$first->external_id, $second->external_id],
            array_column($document['data'][0]['relationships']['artistLinks']['data'], 'id'));
        $this->assertPublicResourceObjects($document);
    }

    public function test_null_and_empty_relationships_have_json_api_linkage(): void
    {
        $event = Event::factory()->create(['venue_id' => null]);
        $request = Request::create('/resources', 'GET', ['include' => 'venue,artistLinks']);
        $document = (new EventResource($event))->toResponse($request)->getData(true);
        $this->assertNull($document['data']['relationships']['venue']['data']);
        $this->assertSame([], $document['data']['relationships']['artistLinks']['data']);
    }

    private function assertPublicResourceObjects(array $document): void
    {
        $objects = isset($document['data']['id']) ? [$document['data']] : $document['data'];
        foreach ([...$objects, ...($document['included'] ?? [])] as $object) {
            $this->assertTrue(Str::isUuid($object['id']));
            foreach (array_keys($object['attributes'] ?? []) as $key) {
                $this->assertFalse($key === 'id' || $key === 'external_id'
                    || (str_ends_with($key, '_id') && $key !== 'provider_resource_id'), $key);
            }
            foreach ($object['relationships'] ?? [] as $relationship) {
                $data = $relationship['data'];
                foreach (isset($data['id']) ? [$data] : ($data ?? []) as $identifier) {
                    $this->assertTrue(Str::isUuid($identifier['id']));
                }
            }
        }
    }

    public static function entities(): array
    {
        return array_map(fn (string $entity): array => [$entity], [
            'Artist', 'ArtistProviderId', 'Attendance', 'AttendanceInvitation',
            'AttendanceLink', 'AttendanceMember', 'AttendanceMemberSeat',
            'Event', 'EventArtist', 'EventProviderReference', 'EventTour',
            'SetlistEntry', 'SetlistEntryArtist', 'Song', 'SongLink', 'SongProviderId',
            'Tour', 'TourArtist', 'TourProviderId', 'User', 'Venue', 'VenueProviderId',
        ]);
    }
}
