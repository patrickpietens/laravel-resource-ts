<?php

use ResourceTs\DTO\FieldDefinition;

it('generates a basic field declaration', function () {
    $field = new FieldDefinition('name', 'string');

    expect($field->toDeclaration())->toBe('name: string');
    expect($field->toTypeString())->toBe('string');
});

it('generates an optional field declaration', function () {
    $field = new FieldDefinition('secret', 'string', optional: true);

    expect($field->toDeclaration())->toBe('secret?: string');
});

it('generates a nullable field declaration', function () {
    $field = new FieldDefinition('avatar', 'string', nullable: true);

    expect($field->toDeclaration())->toBe('avatar: string | null');
    expect($field->toTypeString())->toBe('string | null');
});

it('generates an optional nullable field declaration', function () {
    $field = new FieldDefinition('bio', 'string', optional: true, nullable: true);

    expect($field->toDeclaration())->toBe('bio?: string | null');
});

it('does not duplicate null in type string', function () {
    $field = new FieldDefinition('value', 'string | null', nullable: true);

    expect($field->toTypeString())->toBe('string | null');
});
