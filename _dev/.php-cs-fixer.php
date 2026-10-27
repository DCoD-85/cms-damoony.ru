<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/../src')
    ->in(__DIR__ . '/tests')
    ->exclude('var')
    ->exclude('vendor');

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        '@PSR12:risky' => true,
        '@PHP84Migration' => true,
        'declare_strict_types' => true,
        'array_syntax' => ['syntax' => 'short'],
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'no_unused_imports' => true,
        'single_quote' => true,
        'trailing_comma_in_multiline' => ['elements' => ['arrays', 'arguments', 'parameters']],
        'concat_space' => ['spacing' => 'one'],
        'no_superfluous_phpdoc_tags' => true,
        'phpdoc_align' => ['align' => 'left'],
        'phpdoc_order' => true,
        'phpdoc_separation' => true,
        'yoda_style' => false,
        'final_class' => false,
        'final_public_method_for_abstract_class' => true,
        // 'native_function_invocation' удалён: в namespace-коде даёт шумный `\` перед каждой функцией, выигрыш нулевой
    ])
    ->setFinder($finder);