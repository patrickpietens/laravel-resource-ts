<?php

namespace ResourceTs;

class TypeMapper
{
    /**
     * Eloquent cast types to TypeScript types.
     *
     * @var array<string, string>
     */
    protected static array $castMap = [
        'int' => 'number',
        'integer' => 'number',
        'float' => 'number',
        'double' => 'number',
        'real' => 'number',
        'string' => 'string',
        'bool' => 'boolean',
        'boolean' => 'boolean',
        'date' => 'string',
        'datetime' => 'string',
        'immutable_date' => 'string',
        'immutable_datetime' => 'string',
        'timestamp' => 'number',
        'array' => 'unknown[]',
        'json' => 'unknown',
        'object' => 'Record<string, unknown>',
        'collection' => 'unknown[]',
        'decimal' => 'number',
        'encrypted' => 'string',
        'hashed' => 'string',
    ];

    /**
     * PHP scalar type names to TypeScript types.
     *
     * @var array<string, string>
     */
    protected static array $phpTypeMap = [
        'int' => 'number',
        'integer' => 'number',
        'float' => 'number',
        'double' => 'number',
        'string' => 'string',
        'bool' => 'boolean',
        'boolean' => 'boolean',
        'array' => 'unknown[]',
        'null' => 'null',
        'mixed' => 'unknown',
        'void' => 'void',
    ];

    /**
     * Map an Eloquent cast type to a TypeScript type.
     */
    public static function fromCast(string $cast): string
    {
        // Handle parameterized casts like "decimal:2" or "encrypted:array"
        $baseCast = strtolower(explode(':', $cast)[0]);

        return static::$castMap[$baseCast] ?? 'unknown';
    }

    /**
     * Map a PHP type name to a TypeScript type.
     */
    public static function fromPhpType(string $phpType): string
    {
        $lower = strtolower($phpType);

        return static::$phpTypeMap[$lower] ?? 'unknown';
    }

    /**
     * Resolve a TypeScript type from model casts for a given property.
     *
     * @param  array<string, string>  $casts
     */
    public static function fromModelProperty(string $property, array $casts): ?string
    {
        if (! isset($casts[$property])) {
            return null;
        }

        $cast = $casts[$property];

        // Try the cast map first (handles simple casts like 'datetime', 'integer', etc.)
        $mapped = static::fromCast($cast);

        if ($mapped !== 'unknown') {
            return $mapped;
        }

        // Class-based casts (e.g. AsCollection::class) contain backslashes
        if (str_contains($cast, '\\') && class_exists($cast)) {
            return 'unknown';
        }

        return $mapped;
    }
}
