<?php

namespace ResourceTs\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use ResourceTs\Analyzers\AttributeAnalyzer;
use ResourceTs\Analyzers\ResourceAnalyzer;
use ResourceTs\Analyzers\StaticAnalyzer;
use ResourceTs\TypescriptGenerator;

class GenerateTypescriptCommand extends Command
{
    public $signature = 'typescript:generate
                        {--output= : Output directory path (overrides config)}
                        {--resource= : Generate for a specific resource class only}
                        {--separate-files : Generate a separate file for each resource type}';

    public $description = 'Generate TypeScript type definitions from Laravel API Resources';

    public function handle(): int
    {
        $this->components->info('Generating TypeScript definitions...');

        $analyzer = new ResourceAnalyzer(
            new StaticAnalyzer,
            new AttributeAnalyzer,
        );

        $resourceClass = $this->option('resource');

        if ($resourceClass !== null) {
            if (! class_exists($resourceClass)) {
                $this->components->error("Class [{$resourceClass}] does not exist.");

                return self::FAILURE;
            }

            $definitions = [$analyzer->analyze($resourceClass)];
        } else {
            $definitions = $analyzer->analyzeAll();
        }

        if (empty($definitions)) {
            $this->components->warn('No API Resources found.');

            return self::SUCCESS;
        }

        $generator = new TypescriptGenerator;
        $separateFiles = $this->option('separate-files') || config('resource-ts.separate_files', false);

        $outputPath = $this->option('output') ?? config('resource-ts.output.path');
        $outputFile = config('resource-ts.output.file');

        // Ensure the output directory exists
        if (! is_dir($outputPath)) {
            File::makeDirectory($outputPath, 0755, true);
        }

        if ($separateFiles) {
            $this->generateSeparateFiles($generator, $definitions, $outputPath);
        } else {
            $this->generateSingleFile($generator, $definitions, $outputPath, $outputFile);
        }

        $count = count($definitions);
        $this->components->info("Generated {$count} " . str('type')->plural($count) . " in {$outputPath}");

        $this->table(
            ['Type', 'Fields'],
            collect($definitions)->map(fn($def) => [
                $def->typeName,
                count($def->fields),
            ])->toArray(),
        );

        return self::SUCCESS;
    }

    /**
     * Generate all types into a single file.
     */
    protected function generateSingleFile(TypescriptGenerator $generator, array $definitions, string $outputPath, string $outputFile): void
    {
        $typescript = $generator->generate($definitions);
        $fullPath = $outputPath . '/' . $outputFile;

        File::put($fullPath, $typescript);
    }

    /**
     * Generate a separate file for each resource definition.
     */
    protected function generateSeparateFiles(TypescriptGenerator $generator, array $definitions, string $outputPath): void
    {
        foreach ($definitions as $definition) {
            $typescript = $generator->generateSingle($definition);
            $filePath = $outputPath . '/' . $definition->typeName . '.d.ts';

            File::put($filePath, $typescript);
        }
    }
}
