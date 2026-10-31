<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

abstract class PublicJsonApiResource extends JsonApiResource
{
    public function toId(Request $request): string
    {
        return $this->resource->external_id;
    }
}
