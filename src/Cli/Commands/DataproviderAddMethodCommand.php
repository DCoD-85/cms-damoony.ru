<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Commands\Concerns\ResolvesRealDomain;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Files\Paths;
use Damoony\Data\Db\DataProvider\Template\InsertTemplate;
use Damoony\Data\Db\DataProvider\Template\SelectWhereTemplate;

final class DataproviderAddMethodCommand implements CommandInterface
{
    use ResolvesRealDomain;

    public function __construct(
        private readonly Paths $paths,
    ) {
    }

    public function name(): string
    {
        return 'dataprovider:add-method';
    }
    public function description(): string
    {
        return 'Generate a provider method from a safe template';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();
        $domain = $args['domain'] ?? '';
        $alias = $args['alias'] ?? 'www';
        $provider = $args['provider'] ?? '';
        $templateName = $args['template'] ?? '';

        $b64 = $args['args_b64'] ?? '';
        $decoded = base64_decode($b64, true);
        $templateArgs = json_decode((string)$decoded, true);

        if (!is_array($templateArgs)) {
            $r->error('Invalid --args_b64 JSON');
            return $r;
        }

        $templates = [
            'select_where' => SelectWhereTemplate::class,
            'insert' => InsertTemplate::class,
        ];

        $templateClass = $templates[$templateName] ?? null;
        if ($templateClass === null) {
            $r->error('Unknown template: ' . $templateName);
            return $r;
        }

        try {
            $realDomain = $this->resolveRealDomain($this->paths, $domain);
        } catch (\RuntimeException $e) {
            $r->error($e->getMessage());
            return $r;
        }

        try {
            $methodCode = $templateClass::compile($templateArgs);
        } catch (\Throwable $e) {
            $r->error('Compile failed: ' . $e->getMessage());
            return $r;
        }

        $namespace = str_replace(['.', '-'], '_', $realDomain) . '\\' . $alias . '\\provider';
        $className = ucfirst($provider) . 'Provider';
        $filePath = $this->paths->appRoot() . '/' . $realDomain . '/' . $alias . '/.dbprovider/' . $className . '.php';

        if (!is_file($filePath)) {
            $dir = dirname($filePath);
            if (!is_dir($dir) && !mkdir($dir, 0o755, true) && !is_dir($dir)) {
                $r->error('Cannot create directory: ' . $dir);
                return $r;
            }

            $coreClass = 'Damoony\\Data\\Db\\DataProvider\\' . $className;
            $boilerplate = <<<PHP
                <?php

                declare(strict_types=1);

                namespace {$namespace};

                use {$coreClass} as BaseProvider;

                final class {$className} extends BaseProvider
                {
                }
                PHP;
            file_put_contents($filePath, $boilerplate);
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            $r->error('Cannot read file: ' . $filePath);
            return $r;
        }

        $pos = strrpos($content, '}');
        if ($pos === false) {
            $r->error('Invalid class file: ' . $filePath);
            return $r;
        }

        $newContent = substr($content, 0, $pos) . "\n" . $methodCode . "\n}\n";
        file_put_contents($filePath, $newContent);

        $r->data = [
            'domain' => $domain,
            'alias' => $alias,
            'provider' => $provider,
            'method' => $templateArgs['method_name'],
            'file' => $filePath,
            'namespace' => $namespace . '\\' . $className,
        ];
        $context->step("Method {$templateArgs['method_name']} added to {$className}", true);

        return $r;
    }
}
