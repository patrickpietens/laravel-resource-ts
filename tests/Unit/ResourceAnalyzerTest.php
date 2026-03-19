<?php

use ResourceTs\Analyzers\AttributeAnalyzer;
use ResourceTs\Analyzers\ResourceAnalyzer;
use ResourceTs\Analyzers\StaticAnalyzer;
use ResourceTs\Tests\Fixtures\Resources\OverriddenResource;
use ResourceTs\Tests\Fixtures\Resources\UserResource;

beforeEach(function () {
    $this->analyzer = new ResourceAnalyzer(
        new StaticAnalyzer,
        new AttributeAnalyzer,
    );
});

it('analyzes a resource with model casts', function () {
    $definition = $this->analyzer->analyze(UserResource::class);

    expect($definition->typeName)->toBe('User');
    expect($definition->className)->toBe(UserResource::class);
    expect($definition->fields)->toHaveCount(8);
});

it('applies attribute overrides', function () {
    $definition = $this->analyzer->analyze(OverriddenResource::class);

    $fieldsByName = collect($definition->fields)->keyBy('name');

    expect($definition->typeName)->toBe('CustomType');
    expect($fieldsByName->get('metadata')->typescriptType)->toBe('Record<string, string>');
    expect($fieldsByName->get('tags')->typescriptType)->toBe('string[]');
});

it('excludes fields specified in attribute', function () {
    $definition = $this->analyzer->analyze(OverriddenResource::class);

    $fieldNames = collect($definition->fields)->pluck('name')->toArray();

    expect($fieldNames)->not->toContain('internal');
});

it('uses custom type name from attribute', function () {
    $definition = $this->analyzer->analyze(OverriddenResource::class);

    expect($definition->typeName)->toBe('CustomType');
});

it('strips Resource suffix by default', function () {
    $definition = $this->analyzer->analyze(UserResource::class);

    expect($definition->typeName)->toBe('User');
});
