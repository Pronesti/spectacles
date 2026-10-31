<?php

namespace App\Http\Controllers;

use App\Http\CatalogDraftResolver;
use App\Http\EventDraftResolver;
use App\Http\Requests\SaveConcertRequest;
use App\Http\Resources\CollectionResource;
use App\Http\VenueDraftResolver;
use App\Models\Artist;
use App\Models\Attendance;
use App\Models\AttendanceMember;
use App\Models\SetlistEntry;
use App\Models\Song;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CollectionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $attendances = Attendance::query()->whereNull('deleted_at')->whereHas('members', fn ($query) => $query->where('user_id', $request->user()->id))
            ->with(['event.venue', 'event.artistLinks.artist', 'event.tourLinks.tour', 'members' => fn ($query) => $query->where('user_id', $request->user()->id), 'entries.song', 'entries.artistLinks.artist'])
            ->orderBy('id')->get();

        return (new CollectionResource($attendances))->response()->header('Cache-Control', 'private, no-store');
    }

    public function store(SaveConcertRequest $request): JsonResponse
    {
        $this->persist($request, null);

        return $this->index($request);
    }

    public function update(SaveConcertRequest $request, Attendance $attendance): JsonResponse
    {
        $this->persist($request, $attendance);

        return $this->index($request);
    }

    public function destroy(Request $request, Attendance $attendance): JsonResponse
    {
        DB::transaction(function () use ($request, $attendance): void {
            User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $locked = Attendance::query()->lockForUpdate()->findOrFail($attendance->id);
            abort_unless($locked->owner_user_id === $request->user()->id, 404);
            $locked->forceFill(['deleted_at' => now()])->save();
        });

        return $this->index($request);
    }

    private function persist(SaveConcertRequest $request, ?Attendance $attendance): void
    {
        DB::transaction(function () use ($request, $attendance): void {
            User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $data = $request->validated('concert');
            if ($attendance !== null) {
                $attendance = Attendance::query()->lockForUpdate()->findOrFail($attendance->id);
                abort_if($attendance->getAttribute('deleted_at') !== null, 404);
                abort_unless($attendance->members()->where('user_id', $request->user()->id)->exists(), 404);
                abort_unless($attendance->external_id === $data['id'], 422);
            } else {
                $existingAttendance = Attendance::query()->where('external_id', $data['id'])->lockForUpdate()->first();
                if ($existingAttendance !== null) {
                    abort_unless($existingAttendance->getAttribute('deleted_at') !== null && $existingAttendance->owner_user_id === $request->user()->id, 409);
                    $existingAttendance->forceFill(['deleted_at' => null])->save();
                    $attendance = $existingAttendance;
                }
            }
            $pending = $request->validated('pendingCatalog', []);
            if (! isset($data['catalog']['eventId'])) {
                abort_if($attendance !== null && ($attendance->owner_user_id !== $request->user()->id || $attendance->members()->count() > 1), 403, 'Shared Event changes are not available here.');
            }
            $data = app(CatalogDraftResolver::class)->resolve($data, $pending);
            $venue = isset($data['catalog']['eventId']) ? null : app(VenueDraftResolver::class)->resolve($data, $request->validated('venue'), $pending['venue'] ?? null);
            $event = app(EventDraftResolver::class)->resolve($data, $venue, $pending['event'] ?? null);
            $duplicate = AttendanceMember::query()->where('user_id', $request->user()->id)->where('event_id', $event->id);
            if ($attendance !== null) {
                $duplicate->where('attendance_id', '!=', $attendance->id);
            }
            $duplicateMember = $duplicate->with('attendance')->first();
            if ($attendance === null && $duplicateMember !== null && $duplicateMember->attendance->getAttribute('deleted_at') !== null && $duplicateMember->attendance->owner_user_id === $request->user()->id) {
                $attendance = $duplicateMember->attendance;
                $attendance->forceFill(['deleted_at' => null])->save();
            } else {
                abort_if($duplicateMember !== null, 409, 'This Event is already in your collection.');
            }
            if ($attendance === null) {
                $attendance = new Attendance(['event_id' => $event->id, 'owner_user_id' => $request->user()->id, 'notes' => $data['notes']]);
                $attendance->external_id = $data['id'];
                $attendance->save();
                $member = $attendance->members()->create(['event_id' => $event->id, 'user_id' => $request->user()->id]);
            } else {
                if ($attendance->event_id !== $event->id) {
                    abort_unless($attendance->owner_user_id === $request->user()->id && $attendance->members()->count() === 1, 403);
                    $member = $attendance->members()->firstOrFail();
                    $seats = $member->seats()->get()->map->getAttributes()->all();
                    $attributes = $member->getAttributes();
                    $member->delete();
                    $attendance->update(['event_id' => $event->id]);
                    $attributes['event_id'] = $event->id;
                    DB::table('attendance_members')->insert($attributes);
                    if ($seats !== []) {
                        DB::table('attendance_member_seats')->insert($seats);
                    }
                }
                $member = $attendance->members()->where('user_id', $request->user()->id)->firstOrFail();
            }
            $attendance->update(['notes' => $data['notes']]);
            $member->update(['interested' => $data['interested'], 'tickets_bought' => $data['ticketsBought']]);
            $existing = $attendance->entries()->with('artistLinks.artist')->get()->keyBy('external_id');
            $ids = array_column($data['setlist'], 'id');
            $attendance->entries()->whereNotIn('external_id', $ids)->delete();
            $offset = ((int) $attendance->entries()->max('position')) + count($ids) + 1;
            $attendance->entries()->increment('position', $offset);
            foreach ($data['setlist'] as $index => $row) {
                $entry = $existing->get($row['id']);
                if ($entry === null) {
                    abort_if(SetlistEntry::query()->where('external_id', $row['id'])->exists(), 409);
                    $entry = $attendance->entries()->make();
                    $entry->external_id = $row['id'];
                }
                if ($entry->exists) {
                    $entry->refresh();
                }
                $songId = isset($row['songCatalogId']) ? Song::query()->where('external_id', $row['songCatalogId'])->firstOrFail()->id : null;
                $oldPerformer = $entry->exists ? $entry->artistLinks->pluck('artist.name')->join(' · ') : '';
                $entry->fill(['song_id' => $songId, 'position' => $index + 1, 'type' => $row['type'], 'is_tape' => $row['tape'], 'display_text' => $row['title']]);
                $entry->save();
                if ($row['type'] === 'note' || isset($row['artistCatalogId']) || ($row['artist'] ?? '') !== $oldPerformer) {
                    $entry->artistLinks()->delete();
                    if ($row['type'] === 'song' && ($row['artist'] ?? '') !== '') {
                        $artist = isset($row['artistCatalogId']) ? Artist::query()->where('external_id', $row['artistCatalogId'])->firstOrFail() : Artist::query()->create(['name' => $row['artist']]);
                        $entry->artistLinks()->create(['artist_id' => $artist->id]);
                    }
                }
            }
        });
    }
}
