<?php

namespace App\Http;

use Spatie\LaravelData\Data;

/**
 * Hydrates an untrusted document into its Data class: a provider response, a decrypted
 * proof payload, request input, a stored value, or Data being re-checked.
 *
 * A document that does not fit its class is reported with the caller's own exception.
 */
final class UntrustedPayload
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
