<?php

namespace ResourceTs\Analyzers;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\File;
use ReflectionClass;
use ResourceTs\Attributes\Typescript;
use ResourceTs\DTO\FieldDefinition;
use ResourceTs\DTO\ResourceDefinition;

class ResourceAnalyzer
{
    public function __construct(
        protected StaticAnalyzer $staticAnalyzer,
        protected AttributeAnalyzer $attributeAnalyzer,
    ) {}

    /**
     * Analyze a single resource class and return its definition.
     */
    public function analyze(string $resourceClass): ResourceDefinition
    {
        // Get fields from static analysis
        $staticFields = $this->staticAnalyzer->analyze($resourceClass);

        // Get attribute overrides
        $attributeFields = $this->attributeAnalyzer->analyze($resourceClass);
        $attribute = $this->attributeAnalyzer->getAttribute($resourceClass);

        // Merge: attribute overrides take precedence over static analysis
        $fields = $this->mergeFields($staticFields, $attributeFields);

        // Apply exclusions from attribute
        if ($attribute !== null && ! empty($attribute->exclude)) {
            $fields = array_values(array_filter(
                $fields,
                fn (FieldDefinition $field) => ! in_array($field->name, $attribute->exclude),
            ));
        }

        return new ResourceDefinition(
            className: $resourceClass,
            typeName: $this->resolveTypeName($resourceClass, $attribute),
            fields: $fields,
        );
    }

    /**
     * Discover and analyze all resource classes from configured paths.
     *
     * @return ResourceDefinition[]
     */
    public function analyzeAll(): array
    {
        $paths = config('resource-ts.paths', []);
        $definitions = [];

        foreach ($paths as $path) {
            if (! is_dir($path)) {
                continue;
            }

            $classes = $this->discoverResourceClasses($path);

            foreach ($classes as $class) {
                $definitions[] = $this->analyze($class);
            }
        }

        if (config('resource-ts.sort_types', true)) {
            usort($definitions, fn (ResourceDefinition $a, ResourceDefinition $b) => strcmp($a->typeName, $b->typeName));
        }

        return $definitions;
    }

    /**
     * Discover all JsonResource subclasses in a directory.
     *
     * @return string[]
     */
    protected function discoverResourceClasses(string $path): array
    {
        $classes = [];

        $files = File::allFiles($path);

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $className = $this->resolveClassNameFromFile($file->getRealPath());

            if ($className === null) {
                continue;
            }

            if (! class_exists($className)) {
                continue;
            }

            $reflection = new ReflectionClass($className);

            if ($reflection->isAbstract()) {
                continue;
            }

            if (! $reflection->isSubclassOf(JsonResource::class)) {
                continue;
            }

            $classes[] = $className;
        }

        return $classes;
    }

    /**
     * Resolve the fully qualified class name from a PHP file.
     */
    protected function resolveClassNameFromFile(string $filePath): ?string
    {
        $contents = file_get_contents($filePath);

        if ($contents === false) {
            return null;
        }

        $namespace = null;
        $class = null;

        if (preg_match('/namespace\s+([\w\\\\]+)\s*;/', $contents, $matches)) {
            $namespace = $matches[1];
        }

        if (preg_match('/class\s+(\w+)/', $contents, $matches)) {
            $class = $matches[1];
        }

        if ($class === null) {
            return null;
        }

        return $namespace !== null ? "{$namespace}\\{$class}" : $class;
    }

    /**
     * Merge static analysis fields with attribute overrides.
     *
     * Attribute overrides take precedence for any field that exists in both.
     *
     * @param  FieldDefinition[]  $staticFields
     * @param  FieldDefinition[]  $attributeFields
     * @return FieldDefinition[]
     */
    protected function mergeFields(array $staticFields, array $attributeFields): array
    {
        $merged = [];

        // Index attribute fields by name for quick lookup
        $overridesByName = [];
        foreach ($attributeFields as $field) {
            $overridesByName[$field->name] = $field;
        }

        // Apply overrides to static fields
        foreach ($staticFields as $field) {
            $merged[] = $overridesByName[$field->name] ?? $field;
            unset($overridesByName[$field->name]);
        }

        // Append any attribute-only fields (not found in static analysis)
        foreach ($overridesByName as $field) {
            $merged[] = $field;
        }

        return $merged;
    }

    /**
     * Determine the TypeScript type name for a resource.
     */
    protected function resolveTypeName(string $resourceClass, ?Typescript $attribute): string
    {
        // Explicit name from attribute takes priority
        if ($attribute?->name !== null) {
            return $attribute->name;
        }

        $shortName = class_basename($resourceClass);
        $stripSuffix = config('resource-ts.strip_resource_suffix', true);

        if ($stripSuffix && str_ends_with($shortName, 'Resource')) {
            $shortName = substr($shortName, 0, -8);
        }

        $prefix = config('resource-ts.type_prefix', '');
        $suffix = config('resource-ts.type_suffix', '');

        return $prefix . $shortName . $suffix;
    }
}
