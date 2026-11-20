<?php

declare(strict_types=1);

namespace App\Http\Integrations\Wikimedia\Data;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;

/**
 * The attribution fields of a Commons file's extended metadata.
 */
class CommonsMetadataPayloadData extends Data
{
    public function __construct(
        #[MapInputName('Artist')]
        public readonly ?CommonsMetadataValuePayloadData $artist = null,
        #[MapInputName('LicenseShortName')]
        public readonly ?CommonsMetadataValuePayloadData $licenseShortName = null,
        #[MapInputName('LicenseUrl')]
        public readonly ?CommonsMetadataValuePayloadData $licenseUrl = null,
    ) {}
}
