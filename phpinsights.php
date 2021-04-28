<?php
declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Preset
    |--------------------------------------------------------------------------
    |
    | This option controls the default preset that will be used by PHP Insights
    | to make your code reliable, simple, and clean. However, you can always
    | adjust the `Metrics` and `Insights` below in this configuration file.
    |
    | Supported: "default", "laravel", "symfony", "magento2", "drupal"
    |
    */

    'preset' => 'default',

    /*
    |--------------------------------------------------------------------------
    | IDE
    |--------------------------------------------------------------------------
    |
    | This options allow to add hyperlinks in your terminal to quickly open
    | files in your favorite IDE while browsing your PhpInsights report.
    |
    | Supported: "textmate", "macvim", "emacs", "sublime", "phpstorm",
    | "atom", "vscode".
    |
    | If you have another IDE that is not in this list but which provide an
    | url-handler, you could fill this config with a pattern like this:
    |
    | myide://open?url=file://%f&line=%l
    |
    */

    'ide' => null,

    /*
    |--------------------------------------------------------------------------
    | Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may adjust all the various `Insights` that will be used by PHP
    | Insights. You can either add, remove or configure `Insights`. Keep in
    | mind, that all added `Insights` must belong to a specific `Metric`.
    |
    */

    'exclude' => [
        'phpinsights.php'
    ],

    'add' => [
        //  ExampleMetric::class => [
        //      ExampleInsight::class,
        //  ]
    ],

    'remove' => [
        //  ExampleInsight::class,
        \PHP_CodeSniffer\Standards\Squiz\Sniffs\PHP\GlobalKeywordSniff::class,
        \NunoMaduro\PhpInsights\Domain\Insights\ForbiddenGlobals::class,
        // TODO: TEMPORARY UNTIL THIS FIX GETS RELEASED
        \NunoMaduro\PhpInsights\Domain\Insights\ForbiddenSecurityIssues::class,
        \PhpCsFixer\Fixer\Whitespace\NoExtraBlankLinesFixer::class,
        \PhpCsFixer\Fixer\Whitespace\NoSpacesInsideParenthesisFixer::class,
        \PhpCsFixer\Fixer\FunctionNotation\FunctionDeclarationFixer::class,
        \SlevomatCodingStandard\Sniffs\TypeHints\UselessConstantTypeHintSniff::class,
        \SlevomatCodingStandard\Sniffs\Classes\SuperfluousExceptionNamingSniff::class
    ],

    'config' => [
        //  ExampleInsight::class => [
        //      'key' => 'value',
        //  ],
        \SlevomatCodingStandard\Sniffs\TypeHints\DeclareStrictTypesSniff::class => [
            'newlinesCountBetweenOpenTagAndDeclare' => 1,
            'newlinesCountAfterDeclare' => 2,
            'spacesCountAroundEqualsSign' => 0,
        ],
        \SlevomatCodingStandard\Sniffs\TypeHints\PropertyTypeHintSniff::class => [
            'enableNativeTypeHint' => false,
        ],
        \PhpCsFixer\Fixer\FunctionNotation\FunctionDeclarationFixer::class => [
            'closure_function_spacing' => 'one',
            // possible values ['one', 'none']
        ],
        \PhpCsFixer\Fixer\Basic\BracesFixer::class => [
            'allow_single_line_closure' => true,
            'position_after_anonymous_constructs' => 'same',
            // possible values ['same', 'next']
            'position_after_control_structures' => 'next',
            // possible values ['same', 'next']
            'position_after_functions_and_oop_constructs' => 'next',
            // possible values ['same', 'next']
        ],
        \ObjectCalisthenics\Sniffs\Files\FunctionLengthSniff::class => [
            'maxLength' => 25,
        ],
        \PhpCsFixer\Fixer\Operator\BinaryOperatorSpacesFixer::class => [
            'default' => 'align_single_space', // default fix strategy: possibles values ['align', 'align_single_space', 'align_single_space_minimal', 'single_space', 'no_space', null]
        ],
        \PHP_CodeSniffer\Standards\Generic\Sniffs\Formatting\SpaceAfterNotSniff::class => [
            'spacing' => 0
        ],
        \PhpCsFixer\Fixer\Import\OrderedImportsFixer::class => [
            'imports_order' => ['class', 'function', 'const',],
            'sort_algorithm' => 'alpha', // possible values ['alpha', 'length', 'none']
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Requirements
    |--------------------------------------------------------------------------
    |
    | Here you may define a level you want to reach per `Insights` category.
    | When a score is lower than the minimum level defined, then an error
    | code will be returned. This is optional and individually defined.
    |
    */

    'requirements' => [
        'min-quality' => 90,
        'min-complexity' => 80,
        'min-architecture' => 95,
        'min-style' => 95,
//        'disable-security-check' => false,
    ],

];
