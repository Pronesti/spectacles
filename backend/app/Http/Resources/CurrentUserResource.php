<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;

/** @mixin User */
class CurrentUserResource extends PublicJsonApiResource
{
    /** @var list<string> */
    public $attributes = ['name', 'email', 'locale'];

    public function toType(Request $request): string
    {
        return 'users';
    }
}
