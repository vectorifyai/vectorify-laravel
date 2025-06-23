<?php

namespace Vectorify\Laravel\Support;

class ConfigResolver
{
    public static function getConfig(string $collection): array|string
    {
        $collections = config('vectorify.collections', []);

        // Check if collection exists as a named array configuration
        if (array_key_exists($collection, $collections)) {
            return $collections[$collection];
        }

        return $collection;
    }

    public static function getCollectionName(mixed $collection): string
    {
        return str_contains($collection, '\\')
            ? class_basename($collection)
            : ucfirst($collection);
    }
}
