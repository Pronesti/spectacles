<?php

namespace App\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

abstract class PublicJsonApiResource extends JsonApiResource
{
    /** @var Model&object{external_id: string} */
    public $resource;

    /** @var array<string, class-string<PublicJsonApiResource>> */
    public $relationships = [];

    public function toId(Request $request): string
    {
        return $this->resource->external_id;
    }
}
