<?php

namespace App\Http\Integrations;

use Spatie\LaravelData\Data;

/**
 * Hydrates a decoded provider document, or Data being re-checked, into its payload Data class.
 *
 * Provider documents are untrusted: a document that does not fit its class is
 * reported with the provider's own unavailable exception, never as a 422.
 */
final class UpstreamPayload
{
    /**
     * @template TData of Data
     *
     * @param  class-string<TData>  $class
     * @param  callable(): \Throwable  $unavailable
     * @return TData
     */
    public static function parse(string $class, mixed $document, callable $unavailable): Data
    {
        if (! is_array($document) && ! $document instanceof Data) {
            throw $unavailable();
        }
        try {
            return $class::from($document);
        } catch (\Throwable) {
            throw $unavailable();
        }
    }
}
