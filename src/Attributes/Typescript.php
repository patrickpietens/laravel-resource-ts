<?php

namespace ResourceTs\Attributes;

use Attribute;

/**
 * Annotate a JsonResource class with TypeScript generation metadata.
 *
 * Use this attribute to:
 * - Override the generated TypeScript type name
 * - Specify the underlying Eloquent model for cast-based type inference
 * - Override individual field types when static analysis cannot determine them
 * - Exclude fields from the generated type
 *
 * Example:
 *
 *     #[Typescript(
 *         name: 'User',
 *         model: \App\Models\User::class,
 *         overrides: ['metadata' => 'Record<string, unknown>'],
 *         exclude: ['internal_field'],
 *     )]
 *     class UserResource extends JsonResource { ... }
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Typescript
{
    /**
     * @param  string|null  $name  Custom TypeScript type name (overrides auto-generated name)
     * @param  string|null  $model  Fully qualified model class name for cast inference
     * @param  array<string, string>  $overrides  Field-level type overrides (e.g. ['avatar' => 'string | null'])
     * @param  string[]  $exclude  Fields to exclude from the generated type
     */
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $model = null,
        public readonly array $overrides = [],
        public readonly array $exclude = [],
    ) {}
}
