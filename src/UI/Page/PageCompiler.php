<?php

declare(strict_types=1);

namespace Damoony\UI\Page;

use Damoony\UI\BlockCodegen;

final class PageCompiler
{
    /**
     * @param list<array{type: string, content?: string, items?: mixed, url?: string, class?: string, id?: string, style?: string, alt?: string}> $blocks
     */
    public function compile(string $namespace, string $classChain, array $blocks, bool $withSchema = false): string
    {
        $code = "<?php\n\ndeclare(strict_types=1);\n\n";
        $code .= "namespace {$namespace};\n\n";
        $code .= "use Damoony\\UI\\Algoritm\\Page;\n";
        $code .= "use Damoony\\UI\\Builder;\n";
        $code .= "use Damoony\\UI\\Node;\n";
        $code .= "use Damoony\\UI\\Text;\n";
        $code .= "use Damoony\\UI\\UrlSanitizer;\n";
        $code .= "use Damoony\\Core\\Http\\Response;\n";

        if ($withSchema) {
            $code .= "use Damoony\\Core\\Files\\Paths;\n";
            $code .= "use Damoony\\UI\\Scheme\\SchemeLoader;\n";
            $code .= "use Damoony\\UI\\Scheme\\SchemeRenderer;\n";
        }

        $code .= "\nfinal class {$classChain} extends Page\n{\n";
        $code .= "    public function default(): Node|Response|null\n    {\n";
        $code .= "        \$content = Builder::div('page-content');\n\n";

        $code .= BlockCodegen::render($blocks, '$content', '$this->context', 8);

        if ($withSchema) {
            $code .= "        \$this->setContent('center', \$content);\n\n";
            $code .= "        \$paths = \$this->context->service('paths');\n";
            $code .= "        if (!\$paths instanceof Paths) {\n";
            $code .= "            return Response::internalError('Paths service missing');\n";
            $code .= "        }\n\n";
            $code .= "        \$sid = \$this->context->service('session_id');\n";
            $code .= "        \$loader = new SchemeLoader(\$paths, is_string(\$sid) ? \$sid : null);\n";
            $code .= "        \$manifest = \$loader->load(\$this->context->domain, \$this->context->domain->scheme);\n";
            $code .= "        \$renderer = new SchemeRenderer();\n\n";
            $code .= "        return \$renderer->render(\$manifest, \$this->regions(), \$this->context);\n";
        } else {
            $code .= "        return \$content;\n";
        }

        $code .= "    }\n}\n";

        return $code;
    }
}
