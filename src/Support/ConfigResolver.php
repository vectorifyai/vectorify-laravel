<?php

namespace Vectorify\Laravel\Support;

use Illuminate\Support\Str;

class ConfigResolver
{
    public static function getConfig(string $collectionId): array|string
    {
        $collections = config('vectorify.collections', []);

        // Check if collection exists as a named array configuration
        if (array_key_exists($collectionId, $collections)) {
            return $collections[$collectionId];
        }

        return $collectionId;
    }

    public static function getCollectionSlug(mixed $collectionId): string
    {
        $string = str_contains($collectionId, '\\')
            ? class_basename($collectionId)
            : $collectionId;

        return Str::slug(Str::snake($string));
    }
}
