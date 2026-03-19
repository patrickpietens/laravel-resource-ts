<?php

use ResourceTs\Analyzers\StaticAnalyzer;
use ResourceTs\Tests\Fixtures\Resources\ConditionalResource;
use ResourceTs\Tests\Fixtures\Resources\PostResource;
use ResourceTs\Tests\Fixtures\Resources\SimpleResource;
use ResourceTs\Tests\Fixtures\Resources\TernaryResource;
use ResourceTs\Tests\Fixtures\Resources\UserResource;

beforeEach(function () {
    $this->analyzer = new StaticAnalyzer;
});

it('infers types from explicit php casts', function () {
    $fields = $this->analyzer->analyze(SimpleResource::class);

    $fieldsByName = collect($fields)->keyBy('name');

    expect($fieldsByName->get('count')->typescriptType)->toBe('number');
    expect($fieldsByName->get('label')->typescriptType)->toBe('string');
    expect($fieldsByName->get('active')->typescriptType)->toBe('boolean');
    expect($fieldsByName->get('score')->typescriptType)->toBe('number');
});

it('infers types from scalar literals', function () {
    $fields = $this->analyzer->analyze(SimpleResource::class);

    $fieldsByName = collect($fields)->keyBy('name');

    expect($fieldsByName->get('literal_string')->typescriptType)->toBe('string');
    expect($fieldsByName->get('literal_int')->typescriptType)->toBe('number');
    expect($fieldsByName->get('literal_float')->typescriptType)->toBe('number');
    expect($fieldsByName->get('literal_bool')->typescriptType)->toBe('boolean');
    expect($fieldsByName->get('literal_null')->typescriptType)->toBe('null');
    expect($fieldsByName->get('literal_null')->nullable)->toBeTrue();
});

it('infers types from model casts via attribute', function () {
    $fields = $this->analyzer->analyze(UserResource::class);

    $fieldsByName = collect($fields)->keyBy('name');

    expect($fieldsByName->get('id')->typescriptType)->toBe('number');
    expect($fieldsByName->get('name')->typescriptType)->toBe('string');
    expect($fieldsByName->get('email')->typescriptType)->toBe('string');
    expect($fieldsByName->get('is_admin')->typescriptType)->toBe('boolean');
    expect($fieldsByName->get('balance')->typescriptType)->toBe('number');
    expect($fieldsByName->get('email_verified_at')->typescriptType)->toBe('string');
    expect($fieldsByName->get('metadata')->typescriptType)->toBe('unknown[]');
    expect($fieldsByName->get('settings')->typescriptType)->toBe('Record<string, unknown>');
});

it('infers nested resource types from static calls', function () {
    $fields = $this->analyzer->analyze(PostResource::class);

    $fieldsByName = collect($fields)->keyBy('name');

    expect($fieldsByName->get('author')->typescriptType)->toBe('User');
});

it('marks conditional fields as optional', function () {
    $fields = $this->analyzer->analyze(ConditionalResource::class);

    $fieldsByName = collect($fields)->keyBy('name');

    expect($fieldsByName->get('id')->optional)->toBeFalse();
    expect($fieldsByName->get('secret')->optional)->toBeTrue();
    expect($fieldsByName->get('profile')->optional)->toBeTrue();
    expect($fieldsByName->get('avatar')->optional)->toBeTrue();
    expect($fieldsByName->get('nickname')->optional)->toBeTrue();
});

it('infers collection types', function () {
    $fields = $this->analyzer->analyze(ConditionalResource::class);

    $fieldsByName = collect($fields)->keyBy('name');

    expect($fieldsByName->get('posts')->typescriptType)->toBe('User[]');
});

it('infers nested resource from whenLoaded callback', function () {
    $fields = $this->analyzer->analyze(ConditionalResource::class);

    $fieldsByName = collect($fields)->keyBy('name');

    expect($fieldsByName->get('avatar')->typescriptType)->toBe('User');
});

it('handles ternary expressions', function () {
    $fields = $this->analyzer->analyze(TernaryResource::class);

    $fieldsByName = collect($fields)->keyBy('name');

    expect($fieldsByName->get('status')->typescriptType)->toBe('string');
});
