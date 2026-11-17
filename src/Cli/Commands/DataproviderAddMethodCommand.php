<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\SiteConfig;
use Damoony\Data\Db\DataProvider\Template\InsertTemplate;
use Damoony\Data\Db\DataProvider\Template\SelectWhereTemplate;
use RuntimeException;
use Throwable;

/**
 * dataprovider:add-method — генерация метода провайдера из шаблона.
 *
 * AI не пишет PHP: код метода приходит из шаблона (PDO named params).
 */
final class DataproviderAddMethodCommand implements CommandInterface
{
    private const ALIAS_RE = '/^[a-z][a-z0-9_]*$/';
    private const PROVIDER_RE = '/^[a-z][a-z0-9_]*$/';
    private const METHOD_RE = '/^[a-z][a-z0-9_]*$/';

    private const TEMPLATES = [
        'select_where' => SelectWhereTemplate::class,
        'insert' => InsertTemplate::class,
    ];

    public function __construct(
        private readonly Paths $paths,
        private readonly SiteConfig $site,
    ) {
    }

    public function name(): string
    {
        return 'dataprovider:add-method';
    }

    public function description(): string
    {
        return 'Сгенерировать метод провайдера из безопасного шаблона';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        try {
            $project = $this->site->resolve((string) ($args['domain'] ?? ''));
        } catch (RuntimeException $e) {
            $r->error($e->getMessage());

            return $r;
        }

        $alias = (string) ($args['alias'] ?? 'www');
        if (preg_match(self::ALIAS_RE, $alias) !== 1) {
            $r->error('Некорректный --alias');

            return $r;
        }

        $provider = (string) ($args['provider'] ?? '');
        if (preg_match(self::PROVIDER_RE, $provider) !== 1) {
            $r->error('Некорректный --provider');

            return $r;
        }

        $templateName = (string) ($args['template'] ?? '');
        $templateClass = self::TEMPLATES[$templateName] ?? null;
        if ($templateClass === null) {
            $r->error('Неизвестный шаблон: ' . $templateName . ' (доступны: ' . implode(', ', array_keys(self::TEMPLATES)) . ')');

            return $r;
        }

        $templateArgs = $this->templateArgs($args);
        if ($templateArgs === null) {
            $r->error('--args или --args_b64 должны быть JSON-объектом');

            return $r;
        }

        $methodName = (string) ($templateArgs['method_name'] ?? '');
        if (preg_match(self::METHOD_RE, $methodName) !== 1) {
            $r->error('Некорректный method_name в аргументах шаблона');

            return $r;
        }

        try {
            $methodCode = $templateClass::compile($templateArgs);
        } catch (Throwable $e) {
            $r->error('Шаблон не скомпилировался: ' . $e->getMessage());

            return $r;
        }

        $className = ucfirst($provider) . 'Provider';
        $filePath = $this->paths->allias($project->folder, $alias) . '/.dbprovider/' . $className . '.php';

        if (!is_file($filePath)) {
            Fs::mkdir(dirname($filePath));
            Fs::writeAtomic($filePath, $this->boilerplate($project->namespace . '\\' . $alias . '\\provider', $className));
            $r->file(Fs::rel($filePath, $this->paths->root()));
        }

        $content = (string) file_get_contents($filePath);

        if (preg_match('/function\s+' . preg_quote($methodName, '/') . '\s*\(/', $content) === 1) {
            $r->error(sprintf('Метод "%s" уже существует в %s', $methodName, $className));

            return $r;
        }

        $pos = strrpos($content, '}');
        if ($pos === false) {
            $r->error('Некорректный файл провайдера: ' . $filePath);

            return $r;
        }

        Fs::writeAtomic($filePath, substr($content, 0, $pos) . "\n" . $methodCode . "\n}\n");
        $r->file(Fs::rel($filePath, $this->paths->root()));

        $r->data = [
            'project' => $project->key,
            'alias' => $alias,
            'provider' => $provider,
            'method' => $methodName,
            'file' => Fs::rel($filePath, $this->paths->root()),
            'class' => $project->namespace . '\\' . $alias . '\\provider\\' . $className,
        ];

        $context->step(sprintf('Метод %s добавлен в %s', $methodName, $className), true);

        return $r;
    }

    /**
     * @param array<string, string> $args
     *
     * @return array<string, mixed>|null
     */
    private function templateArgs(array $args): ?array
    {
        $b64 = (string) ($args['args_b64'] ?? '');
        if ($b64 !== '') {
            $decoded = base64_decode($b64, true);
            if ($decoded === false) {
                return null;
            }
            $parsed = json_decode($decoded, true);

            return is_array($parsed) ? $parsed : null;
        }

        $parsed = json_decode((string) ($args['args'] ?? '{}'), true);

        return is_array($parsed) ? $parsed : null;
    }

    private function boilerplate(string $namespace, string $className): string
    {
        return "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "namespace {$namespace};\n\n"
            . "use Damoony\\Data\\Db\\DataProvider\\{$className} as BaseProvider;\n\n"
            . "final class {$className} extends BaseProvider\n"
            . "{\n"
            . "}\n";
    }
}
