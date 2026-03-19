<?php

namespace ResourceTs\Analyzers;

use ReflectionClass;
use ResourceTs\Attributes\Typescript;
use ResourceTs\Contracts\TypeInferrer;
use ResourceTs\DTO\FieldDefinition;

class AttributeAnalyzer implements TypeInferrer
{
    /**
     * Analyze a resource class using its #[Typescript] attribute.
     *
     * This only returns fields explicitly defined in the attribute's `overrides` array.
     * It is intended to be merged with results from the StaticAnalyzer.
     *
     * @return FieldDefinition[]
     */
    public function analyze(string $resourceClass): array
    {
        $attribute = $this->getAttribute($resourceClass);

        if ($attribute === null) {
            return [];
        }

        $fields = [];

        foreach ($attribute->overrides as $name => $type) {
            $nullable = str_contains($type, 'null');
            $cleanType = $nullable ? trim(str_replace(['| null', 'null |', 'null'], '', $type)) : $type;

            $fields[] = new FieldDefinition(
                name: $name,
                typescriptType: $cleanType ?: 'unknown',
                optional: false,
                nullable: $nullable,
            );
        }

        return $fields;
    }

    /**
     * Resolve the #[Typescript] attribute instance from a resource class.
     */
    public function getAttribute(string $resourceClass): ?Typescript
    {
        $reflection = new ReflectionClass($resourceClass);
        $attributes = $reflection->getAttributes(Typescript::class);

        if (empty($attributes)) {
            return null;
        }

        return $attributes[0]->newInstance();
    }
}
