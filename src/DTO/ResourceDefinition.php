<?php

namespace ResourceTs\DTO;

class ResourceDefinition
{
    /**
     * @param  string  $className  Fully qualified PHP class name
     * @param  string  $typeName  TypeScript type name
     * @param  FieldDefinition[]  $fields
     */
    public function __construct(
        public readonly string $className,
        public readonly string $typeName,
        public readonly array $fields,
    ) {}
}
