<?php

namespace App\Http;

use Spatie\LaravelData\Data;

/**
 * Converts Data objects to and from the plain arrays the cache stores.
 *
 * The cache refuses to unserialize objects, so provider results are written as
 * arrays and rehydrated into their Data class as soon as they are read back.
 */
final class CachedData
{
    /**
     * @param  Data|list<Data>  $value
     * @return array<mixed>
     */
    public static function toCache(Data|array $value): array
    {
        return $value instanceof Data ? $value->toArray() : array_map(fn (Data $item): array => $item->toArray(), $value);
    }

    /**
     * Rehydrates one cached Data object.
     *
     * @template TData of Data
     *
     * @param  class-string<TData>  $class
     * @param  array<mixed>  $cached
     * @return TData
     */
    public static function fromCache(string $class, array $cached): Data
    {
        return $class::from($cached);
    }

    /**
     * Rehydrates a cached list of Data objects.
     *
     * @template TData of Data
     *
     * @param  class-string<TData>  $class
     * @param  array<mixed>  $cached
     * @return list<TData>
     */
    public static function listFromCache(string $class, array $cached): array
    {
        return array_values($class::collect($cached));
    }
}
