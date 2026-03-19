<?php

namespace ResourceTs\Contracts;

use ResourceTs\DTO\FieldDefinition;

interface TypeInferrer
{
    /**
     * Analyze a resource class and return its field definitions.
     *
     * @return FieldDefinition[]
     */
    public function analyze(string $resourceClass): array;
}
