<?php

namespace App\Http\Resources;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\AnonymousResourceCollection;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

abstract class PublicJsonApiResource extends JsonApiResource
{
    public function toId(Request $request): string
    {
        return $this->resource->external_id;
    }

    /** @return array<string, class-string<PublicJsonApiResource>|\Closure> */
    public function toRelationships(Request $request): array
    {
        $relationships = $this->relationships;

        foreach ($relationships as $name => $resourceClass) {
            $membershipPath = match ($resourceClass) {
                AttendanceResource::class => 'members',
                AttendanceMemberResource::class, AttendanceInvitationResource::class,
                AttendanceLinkResource::class, SetlistEntryResource::class => 'attendance.members',
                AttendanceMemberSeatResource::class => 'member.attendance.members',
                SetlistEntryArtistResource::class => 'entry.attendance.members',
                default => null,
            };

            if ($membershipPath === null) {
                continue;
            }

            $relationships[$name] = function (Model $model) use ($name, $resourceClass, $membershipPath, $request): PublicJsonApiResource|AnonymousResourceCollection|null {
                $relation = $model->{$name}();
                $userId = $request->user()?->getAuthIdentifier();

                $relation->where(function (Builder $query) use ($membershipPath, $userId, $resourceClass): void {
                    if ($userId === null) {
                        $query->whereRaw('1 = 0');

                        return;
                    }

                    $query->whereHas($membershipPath, fn (Builder $members) => $members->where('user_id', $userId));

                    if ($resourceClass === AttendanceInvitationResource::class) {
                        $query->orWhere('recipient_user_id', $userId);
                    }
                });

                $related = $relation->getResults();

                return $related instanceof Collection
                    ? $resourceClass::collection($related)
                    : ($related === null ? null : new $resourceClass($related));
            };
        }

        return $relationships;
    }
}
