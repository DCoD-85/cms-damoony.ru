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
        }

        $code .= "\nfinal class {$classChain} extends Page\n{\n";
        $code .= "    protected function content(): Node|Response|null\n    {\n";
        $code .= "        \$content = Builder::div('page-content');\n\n";

        $code .= BlockCodegen::render($blocks, '$content', '$this->context', 8);

        $code .= "        return \$content;\n";

        $code .= "    }\n}\n";

        return $code;
    }
}
