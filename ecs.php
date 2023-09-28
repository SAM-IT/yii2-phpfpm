<?php

declare(strict_types=1);

// ecs.php
use PHP_CodeSniffer\Standards\Generic\Sniffs\PHP\ForbiddenFunctionsSniff;
use PhpCsFixer\Fixer\ArrayNotation\ArraySyntaxFixer;
use PhpCsFixer\Fixer\ClassNotation\FinalInternalClassFixer;
use PhpCsFixer\Fixer\Import\NoUnusedImportsFixer;
use PhpCsFixer\Fixer\Operator\NotOperatorWithSuccessorSpaceFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocAlignFixer;
use PhpCsFixer\Fixer\Strict\DeclareStrictTypesFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;
use Symplify\EasyCodingStandard\ValueObject\Set\SetList;

return static function (ECSConfig $ecsConfig): void {
    // Parallel
    $ecsConfig->parallel();

    // Paths
    $ecsConfig->paths([
        __DIR__ . '/src', __DIR__ . '/tests', __DIR__ . '/ecs.php'
    ]);

    // A. full sets
    $ecsConfig->sets([SetList::PSR_12, SetList::SPACES]);

    $ecsConfig->rule(NotOperatorWithSuccessorSpaceFixer::class);
    $ecsConfig->rule(ArraySyntaxFixer::class);
    $ecsConfig->rule(NoUnusedImportsFixer::class);
    $ecsConfig->rule(DeclareStrictTypesFixer::class);
    $ecsConfig->ruleWithConfiguration(FinalInternalClassFixer::class, [
        'annotation_exclude' => ['@not-fix'],
        'annotation_include' => [],
        'consider_absent_docblock_as_internal_class' => \true
    ]);
    $ecsConfig->rule(PhpdocAlignFixer::class);

    $ecsConfig->ruleWithConfiguration(ForbiddenFunctionsSniff::class, [
        'forbiddenFunctions' => [
            'passthru' => null,
            'var_dump' => null,
        ]
    ]);
    $ecsConfig->skip([
        NotOperatorWithSuccessorSpaceFixer::class,
        __DIR__ . '/tests/_support/_generated/*'
    ]);
};
