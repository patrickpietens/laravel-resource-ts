<?php

// config for ResourceTs
return [

    /*
    |--------------------------------------------------------------------------
    | Output
    |--------------------------------------------------------------------------
    |
    | Configure where the generated TypeScript definitions will be written.
    | "path" is the directory, and "file" is the filename used when all
    | types are written to a single file (i.e. separate_files is false).
    |
    */

    'output' => [
        'path' => resource_path('js/types'),
        'file' => 'resources.d.ts',
    ],

    /*
    |--------------------------------------------------------------------------
    | Separate Files
    |--------------------------------------------------------------------------
    |
    | When enabled, each resource will be written to its own TypeScript file
    | in the output path directory (e.g. User.d.ts, Post.d.ts). When
    | disabled, all types are written to a single file.
    |
    */

    'separate_files' => true,

    /*
    |--------------------------------------------------------------------------
    | Resource Paths
    |--------------------------------------------------------------------------
    |
    | Directories to scan for API Resource classes. The package will
    | recursively search these directories for classes that extend
    | Illuminate\Http\Resources\Json\JsonResource.
    |
    */

    'paths' => [
        app_path('Http/Resources'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-discover Models
    |--------------------------------------------------------------------------
    |
    | When enabled, the package will attempt to resolve the underlying Eloquent
    | model for each resource and use its $casts array to infer field types.
    | Disable this if you prefer to rely solely on attributes and static analysis.
    |
    */

    'auto_discover_models' => true,

    /*
    |--------------------------------------------------------------------------
    | Strip Resource Suffix
    |--------------------------------------------------------------------------
    |
    | By default, the "Resource" suffix is stripped from the class name when
    | generating the TypeScript type name (e.g. UserResource -> User).
    | Set this to false to keep the full class name.
    |
    */

    'strip_resource_suffix' => true,

    /*
    |--------------------------------------------------------------------------
    | Sort Types
    |--------------------------------------------------------------------------
    |
    | When enabled, the generated TypeScript types will be sorted
    | alphabetically. This produces deterministic output that is easier
    | to review in version control diffs.
    |
    */

    'sort_types' => true,

];
