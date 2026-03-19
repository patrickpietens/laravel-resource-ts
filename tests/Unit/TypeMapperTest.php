<?php

use ResourceTs\TypeMapper;

it('maps eloquent cast types to typescript types', function (string $cast, string $expected) {
    expect(TypeMapper::fromCast($cast))->toBe($expected);
})->with([
    ['int', 'number'],
    ['integer', 'number'],
    ['float', 'number'],
    ['double', 'number'],
    ['real', 'number'],
    ['string', 'string'],
    ['bool', 'boolean'],
    ['boolean', 'boolean'],
    ['date', 'string'],
    ['datetime', 'string'],
    ['immutable_date', 'string'],
    ['immutable_datetime', 'string'],
    ['timestamp', 'number'],
    ['array', 'unknown[]'],
    ['json', 'unknown'],
    ['object', 'Record<string, unknown>'],
    ['collection', 'unknown[]'],
    ['encrypted', 'string'],
    ['hashed', 'string'],
]);

it('handles parameterized casts', function () {
    expect(TypeMapper::fromCast('decimal:2'))->toBe('number');
    expect(TypeMapper::fromCast('encrypted:array'))->toBe('string');
});

it('returns unknown for unrecognized casts', function () {
    expect(TypeMapper::fromCast('custom_cast'))->toBe('unknown');
});

it('maps php types to typescript types', function (string $phpType, string $expected) {
    expect(TypeMapper::fromPhpType($phpType))->toBe($expected);
})->with([
    ['int', 'number'],
    ['integer', 'number'],
    ['float', 'number'],
    ['string', 'string'],
    ['bool', 'boolean'],
    ['boolean', 'boolean'],
    ['array', 'unknown[]'],
    ['null', 'null'],
    ['mixed', 'unknown'],
]);

it('resolves model property types from casts', function () {
    $casts = [
        'id' => 'integer',
        'name' => 'string',
        'is_admin' => 'boolean',
    ];

    expect(TypeMapper::fromModelProperty('id', $casts))->toBe('number');
    expect(TypeMapper::fromModelProperty('name', $casts))->toBe('string');
    expect(TypeMapper::fromModelProperty('is_admin', $casts))->toBe('boolean');
    expect(TypeMapper::fromModelProperty('unknown_field', $casts))->toBeNull();
});
