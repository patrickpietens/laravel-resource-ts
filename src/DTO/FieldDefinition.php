<?php

namespace ResourceTs\DTO;

class FieldDefinition
{
    public function __construct(
        public readonly string $name,
        public readonly string $typescriptType,
        public readonly bool $optional = false,
        public readonly bool $nullable = false,
    ) {}

    /**
     * Build the full TypeScript type string including null union if applicable.
     */
    public function toTypeString(): string
    {
        $type = $this->typescriptType;

        if ($this->nullable && ! str_contains($type, 'null')) {
            $type = "{$type} | null";
        }

        return $type;
    }

    /**
     * Build the full TypeScript field declaration line.
     */
    public function toDeclaration(): string
    {
        $optional = $this->optional ? '?' : '';

        return "{$this->name}{$optional}: {$this->toTypeString()}";
    }
}
