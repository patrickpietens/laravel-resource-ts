<?php

namespace ResourceTs\Analyzers;

use Illuminate\Http\Resources\Json\JsonResource;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use ReflectionClass;
use ResourceTs\Contracts\TypeInferrer;
use ResourceTs\DTO\FieldDefinition;
use ResourceTs\TypeMapper;

class StaticAnalyzer implements TypeInferrer
{
    protected NodeFinder $nodeFinder;

    public function __construct()
    {
        $this->nodeFinder = new NodeFinder;
    }

    /**
     * @return FieldDefinition[]
     */
    public function analyze(string $resourceClass): array
    {
        $reflection = new ReflectionClass($resourceClass);
        $filePath = $reflection->getFileName();

        if ($filePath === false) {
            return [];
        }

        $code = file_get_contents($filePath);
        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $ast = $parser->parse($code);

        if ($ast === null) {
            return [];
        }

        $toArrayMethod = $this->findToArrayMethod($ast);

        if ($toArrayMethod === null) {
            return [];
        }

        $casts = $this->resolveModelCasts($resourceClass);

        return $this->extractFieldsFromMethod($toArrayMethod, $casts, $resourceClass);
    }

    /**
     * Find the toArray method node in the AST.
     *
     * @param  Node[]  $ast
     */
    protected function findToArrayMethod(array $ast): ?Node\Stmt\ClassMethod
    {
        /** @var Node\Stmt\ClassMethod|null */
        return $this->nodeFinder->findFirst($ast, function (Node $node) {
            return $node instanceof Node\Stmt\ClassMethod
                && $node->name->toString() === 'toArray';
        });
    }

    /**
     * Extract field definitions from the toArray method body.
     *
     * @param  array<string, string>  $casts
     * @return FieldDefinition[]
     */
    protected function extractFieldsFromMethod(
        Node\Stmt\ClassMethod $method,
        array $casts,
        string $resourceClass,
    ): array {
        $fields = [];

        // Find return statements that return arrays
        $returnStmts = $this->nodeFinder->findInstanceOf(
            $method->stmts ?? [],
            Node\Stmt\Return_::class,
        );

        foreach ($returnStmts as $returnStmt) {
            if ($returnStmt->expr instanceof Expr\Array_) {
                $fields = array_merge(
                    $fields,
                    $this->extractFieldsFromArray($returnStmt->expr, $casts, $resourceClass),
                );
            }
        }

        return $fields;
    }

    /**
     * Extract fields from an array expression node.
     *
     * @param  array<string, string>  $casts
     * @return FieldDefinition[]
     */
    protected function extractFieldsFromArray(
        Expr\Array_ $array,
        array $casts,
        string $resourceClass,
    ): array {
        $fields = [];

        foreach ($array->items as $item) {
            if ($item === null || $item->key === null) {
                continue;
            }

            // Only handle string keys
            if (! $item->key instanceof Scalar\String_) {
                continue;
            }

            $name = $item->key->value;
            $result = $this->inferType($item->value, $casts, $resourceClass);

            $fields[] = new FieldDefinition(
                name: $name,
                typescriptType: $result['type'],
                optional: $result['optional'],
                nullable: $result['nullable'],
            );
        }

        return $fields;
    }

    /**
     * Infer a TypeScript type from an expression node.
     *
     * @param  array<string, string>  $casts
     * @return array{type: string, optional: bool, nullable: bool}
     */
    protected function inferType(Expr $expr, array $casts, string $resourceClass): array
    {
        $optional = false;
        $nullable = false;

        // $this->property
        if ($expr instanceof Expr\PropertyFetch && $this->isThisExpr($expr->var)) {
            $property = $this->getPropertyName($expr);
            if ($property !== null) {
                $castType = TypeMapper::fromModelProperty($property, $casts);

                return [
                    'type' => $castType ?? 'unknown',
                    'optional' => false,
                    'nullable' => false,
                ];
            }
        }

        // Explicit casts: (int), (string), (bool), (float), (double), (array)
        if ($expr instanceof Expr\Cast) {
            return [
                'type' => $this->inferCastType($expr),
                'optional' => false,
                'nullable' => false,
            ];
        }

        // Scalar literals
        if ($expr instanceof Scalar\Int_ || $expr instanceof Scalar\Float_) {
            return ['type' => 'number', 'optional' => false, 'nullable' => false];
        }

        if ($expr instanceof Scalar\String_) {
            return ['type' => 'string', 'optional' => false, 'nullable' => false];
        }

        // true, false, null
        if ($expr instanceof Expr\ConstFetch) {
            $name = strtolower($expr->name->toString());

            return match ($name) {
                'true', 'false' => ['type' => 'boolean', 'optional' => false, 'nullable' => false],
                'null' => ['type' => 'null', 'optional' => false, 'nullable' => true],
                default => ['type' => 'unknown', 'optional' => false, 'nullable' => false],
            };
        }

        // SomeResource::make($this->relation) or SomeResource::collection($this->relations)
        if ($expr instanceof Expr\StaticCall && $expr->class instanceof Node\Name) {
            return $this->inferStaticCallType($expr, $resourceClass);
        }

        // new SomeResource($this->relation)
        if ($expr instanceof Expr\New_ && $expr->class instanceof Node\Name) {
            $className = $expr->class->toString();
            $fqcn = $this->resolveClassName($className, $resourceClass);

            if ($fqcn !== null && is_subclass_of($fqcn, JsonResource::class)) {
                return [
                    'type' => $this->resourceToTypeName($fqcn),
                    'optional' => false,
                    'nullable' => false,
                ];
            }
        }

        // $this->when(condition, value) / $this->whenHas(...) / $this->whenNotNull(...)
        if ($expr instanceof Expr\MethodCall && $this->isThisExpr($expr->var)) {
            return $this->inferMethodCallType($expr, $casts, $resourceClass);
        }

        // Ternary: condition ? a : b
        if ($expr instanceof Expr\Ternary) {
            return $this->inferTernaryType($expr, $casts, $resourceClass);
        }

        // Null coalescing: $a ?? $b
        if ($expr instanceof Expr\BinaryOp\Coalesce) {
            $left = $this->inferType($expr->left, $casts, $resourceClass);

            return [
                'type' => $left['type'],
                'optional' => false,
                'nullable' => false,
            ];
        }

        // Array literal []
        if ($expr instanceof Expr\Array_) {
            return ['type' => 'unknown[]', 'optional' => false, 'nullable' => false];
        }

        return ['type' => 'unknown', 'optional' => $optional, 'nullable' => $nullable];
    }

    /**
     * Infer the type from a PHP cast expression.
     */
    protected function inferCastType(Expr\Cast $cast): string
    {
        return match (true) {
            $cast instanceof Expr\Cast\Int_ => 'number',
            $cast instanceof Expr\Cast\Double => 'number',
            $cast instanceof Expr\Cast\String_ => 'string',
            $cast instanceof Expr\Cast\Bool_ => 'boolean',
            $cast instanceof Expr\Cast\Array_ => 'unknown[]',
            $cast instanceof Expr\Cast\Object_ => 'Record<string, unknown>',
            default => 'unknown',
        };
    }

    /**
     * Infer type from static method calls like SomeResource::make() or ::collection().
     *
     * @return array{type: string, optional: bool, nullable: bool}
     */
    protected function inferStaticCallType(Expr\StaticCall $call, string $resourceClass): array
    {
        $className = $call->class->toString();
        $methodName = $call->name instanceof Node\Identifier ? $call->name->toString() : null;

        if ($methodName === null) {
            return ['type' => 'unknown', 'optional' => false, 'nullable' => false];
        }

        $fqcn = $this->resolveClassName($className, $resourceClass);

        if ($fqcn === null || ! is_subclass_of($fqcn, JsonResource::class)) {
            return ['type' => 'unknown', 'optional' => false, 'nullable' => false];
        }

        $typeName = $this->resourceToTypeName($fqcn);

        return match ($methodName) {
            'make' => ['type' => $typeName, 'optional' => false, 'nullable' => false],
            'collection' => ['type' => "{$typeName}[]", 'optional' => false, 'nullable' => false],
            default => ['type' => 'unknown', 'optional' => false, 'nullable' => false],
        };
    }

    /**
     * Infer type from $this->when(), $this->whenLoaded(), etc.
     *
     * @param  array<string, string>  $casts
     * @return array{type: string, optional: bool, nullable: bool}
     */
    protected function inferMethodCallType(
        Expr\MethodCall $call,
        array $casts,
        string $resourceClass,
    ): array {
        $methodName = $call->name instanceof Node\Identifier ? $call->name->toString() : null;

        if ($methodName === null) {
            return ['type' => 'unknown', 'optional' => false, 'nullable' => false];
        }

        return match ($methodName) {
            // $this->when(condition, value)
            'when' => $this->inferWhenType($call, $casts, $resourceClass),

            // $this->whenLoaded('relation') or $this->whenLoaded('relation', callback)
            'whenLoaded' => $this->inferWhenLoadedType($call, $casts, $resourceClass),

            // $this->whenNotNull($this->property)
            'whenNotNull' => $this->inferWhenNotNullType($call, $casts, $resourceClass),

            default => ['type' => 'unknown', 'optional' => false, 'nullable' => false],
        };
    }

    /**
     * Infer type from $this->when(condition, value).
     *
     * @param  array<string, string>  $casts
     * @return array{type: string, optional: bool, nullable: bool}
     */
    protected function inferWhenType(
        Expr\MethodCall $call,
        array $casts,
        string $resourceClass,
    ): array {
        $args = $call->getArgs();

        // when(condition, value) - the value is the second argument
        if (count($args) >= 2) {
            $valueExpr = $args[1]->value;

            // If the value is a closure, we can't easily infer the type
            if ($valueExpr instanceof Expr\Closure || $valueExpr instanceof Expr\ArrowFunction) {
                $returnType = $this->inferClosureReturnType($valueExpr, $casts, $resourceClass);

                return ['type' => $returnType, 'optional' => true, 'nullable' => false];
            }

            $result = $this->inferType($valueExpr, $casts, $resourceClass);

            return ['type' => $result['type'], 'optional' => true, 'nullable' => $result['nullable']];
        }

        return ['type' => 'unknown', 'optional' => true, 'nullable' => false];
    }

    /**
     * Infer type from $this->whenLoaded('relation', ...).
     *
     * @param  array<string, string>  $casts
     * @return array{type: string, optional: bool, nullable: bool}
     */
    protected function inferWhenLoadedType(
        Expr\MethodCall $call,
        array $casts,
        string $resourceClass,
    ): array {
        $args = $call->getArgs();

        // whenLoaded('relation', callback) - try to infer from callback
        if (count($args) >= 2) {
            $valueExpr = $args[1]->value;

            if ($valueExpr instanceof Expr\Closure || $valueExpr instanceof Expr\ArrowFunction) {
                $returnType = $this->inferClosureReturnType($valueExpr, $casts, $resourceClass);

                return ['type' => $returnType, 'optional' => true, 'nullable' => false];
            }

            $result = $this->inferType($valueExpr, $casts, $resourceClass);

            return ['type' => $result['type'], 'optional' => true, 'nullable' => $result['nullable']];
        }

        // whenLoaded('relation') with no callback - type depends on the relation
        return ['type' => 'unknown', 'optional' => true, 'nullable' => false];
    }

    /**
     * Infer type from $this->whenNotNull($this->property).
     *
     * @param  array<string, string>  $casts
     * @return array{type: string, optional: bool, nullable: bool}
     */
    protected function inferWhenNotNullType(
        Expr\MethodCall $call,
        array $casts,
        string $resourceClass,
    ): array {
        $args = $call->getArgs();

        if (count($args) >= 1) {
            $result = $this->inferType($args[0]->value, $casts, $resourceClass);

            return ['type' => $result['type'], 'optional' => true, 'nullable' => false];
        }

        return ['type' => 'unknown', 'optional' => true, 'nullable' => false];
    }

    /**
     * Attempt to infer the return type of a closure or arrow function.
     *
     * @param  array<string, string>  $casts
     */
    protected function inferClosureReturnType(
        Expr\Closure|Expr\ArrowFunction $closure,
        array $casts,
        string $resourceClass,
    ): string {
        // Arrow function: fn() => expr
        if ($closure instanceof Expr\ArrowFunction) {
            $result = $this->inferType($closure->expr, $casts, $resourceClass);

            return $result['type'];
        }

        // Regular closure: look for return statements
        $returnStmts = $this->nodeFinder->findInstanceOf(
            $closure->stmts ?? [],
            Node\Stmt\Return_::class,
        );

        if (count($returnStmts) === 1 && $returnStmts[0]->expr !== null) {
            $result = $this->inferType($returnStmts[0]->expr, $casts, $resourceClass);

            return $result['type'];
        }

        return 'unknown';
    }

    /**
     * Infer type from a ternary expression.
     *
     * @param  array<string, string>  $casts
     * @return array{type: string, optional: bool, nullable: bool}
     */
    protected function inferTernaryType(
        Expr\Ternary $expr,
        array $casts,
        string $resourceClass,
    ): array {
        $ifResult = $expr->if !== null
            ? $this->inferType($expr->if, $casts, $resourceClass)
            : $this->inferType($expr->cond, $casts, $resourceClass);

        $elseResult = $this->inferType($expr->else, $casts, $resourceClass);

        // If both sides are the same type, use that
        if ($ifResult['type'] === $elseResult['type']) {
            return [
                'type' => $ifResult['type'],
                'optional' => false,
                'nullable' => $ifResult['nullable'] || $elseResult['nullable'],
            ];
        }

        // Otherwise create a union
        $types = array_unique([$ifResult['type'], $elseResult['type']]);

        return [
            'type' => implode(' | ', $types),
            'optional' => false,
            'nullable' => $ifResult['nullable'] || $elseResult['nullable'],
        ];
    }

    /**
     * Resolve model casts for a given resource class.
     *
     * @return array<string, string>
     */
    protected function resolveModelCasts(string $resourceClass): array
    {
        if (! config('resource-ts.auto_discover_models', true)) {
            return [];
        }

        // Check for #[Typescript(model: ...)] attribute
        $reflection = new ReflectionClass($resourceClass);
        $attributes = $reflection->getAttributes(\ResourceTs\Attributes\Typescript::class);

        $modelClass = null;

        if (! empty($attributes)) {
            $instance = $attributes[0]->newInstance();
            $modelClass = $instance->model;
        }

        // Try to guess from @mixin docblock
        if ($modelClass === null) {
            $docComment = $reflection->getDocComment();
            if ($docComment !== false && preg_match('/@mixin\s+([\w\\\\]+)/', $docComment, $matches)) {
                $modelClass = $matches[1];

                // Resolve relative class names
                if (! class_exists($modelClass)) {
                    $namespace = $reflection->getNamespaceName();
                    $fqcn = $namespace.'\\'.$modelClass;
                    if (class_exists($fqcn)) {
                        $modelClass = $fqcn;
                    }
                }
            }
        }

        if ($modelClass === null || ! class_exists($modelClass)) {
            return [];
        }

        try {
            $model = new $modelClass;

            return method_exists($model, 'getCasts') ? $model->getCasts() : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Check if an expression is $this.
     */
    protected function isThisExpr(Expr $expr): bool
    {
        return $expr instanceof Expr\Variable && $expr->name === 'this';
    }

    /**
     * Get the property name from a property fetch expression.
     */
    protected function getPropertyName(Expr\PropertyFetch $expr): ?string
    {
        if ($expr->name instanceof Node\Identifier) {
            return $expr->name->toString();
        }

        return null;
    }

    /**
     * Resolve a short class name to its FQCN using the resource class file's use statements.
     */
    protected function resolveClassName(string $shortName, string $contextClass): ?string
    {
        // Already fully qualified
        if (class_exists($shortName)) {
            return $shortName;
        }

        $reflection = new ReflectionClass($contextClass);
        $filePath = $reflection->getFileName();

        if ($filePath === false) {
            return null;
        }

        $code = file_get_contents($filePath);
        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $ast = $parser->parse($code);

        if ($ast === null) {
            return null;
        }

        // Search use statements
        $useStatements = $this->nodeFinder->findInstanceOf($ast, Node\Stmt\Use_::class);

        foreach ($useStatements as $use) {
            foreach ($use->uses as $useUse) {
                $alias = $useUse->alias?->toString() ?? $useUse->name->getLast();

                if ($alias === $shortName) {
                    $fqcn = $useUse->name->toString();

                    return class_exists($fqcn) ? $fqcn : null;
                }
            }
        }

        // Try same namespace
        $namespace = $reflection->getNamespaceName();
        $fqcn = $namespace.'\\'.$shortName;

        return class_exists($fqcn) ? $fqcn : null;
    }

    /**
     * Convert a resource FQCN to a TypeScript type name reference.
     */
    protected function resourceToTypeName(string $resourceClass): string
    {
        $shortName = class_basename($resourceClass);
        $stripSuffix = config('resource-ts.strip_resource_suffix', true);

        if ($stripSuffix && str_ends_with($shortName, 'Resource')) {
            $shortName = substr($shortName, 0, -8);
        }

        $prefix = config('resource-ts.type_prefix', '');
        $suffix = config('resource-ts.type_suffix', '');

        return $prefix.$shortName.$suffix;
    }
}
