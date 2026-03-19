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
                        {--output= : Output file path (overrides config)}
                        {--resource= : Generate for a specific resource class only}';

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
        $typescript = $generator->generate($definitions);

        $outputPath = $this->option('output') ?? config('resource-ts.output');

        // Ensure the directory exists
        $directory = dirname($outputPath);
        if (! is_dir($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        File::put($outputPath, $typescript);

        $count = count($definitions);
        $this->components->info("Generated {$count} ".str('type')->plural($count)." in {$outputPath}");

        $this->table(
            ['Type', 'Fields'],
            collect($definitions)->map(fn ($def) => [
                $def->typeName,
                count($def->fields),
            ])->toArray(),
        );

        return self::SUCCESS;
    }
}
