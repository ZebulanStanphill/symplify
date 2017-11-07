<?php declare(strict_types=1);

namespace Symplify\CodingStandard\FixerTokenWrapper\Naming;

use PhpCsFixer\Fixer\Import\NoUnusedImportsFixer;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use PhpCsFixer\Tokenizer\TokensAnalyzer;
use ReflectionMethod;

/**
 * Mimics @see \Symplify\CodingStandard\Helper\Naming.
 */
final class ClassFqnResolver
{
    /**
     * @var string
     */
    private const NAMESPACE_SEPARATOR = '\\';

    /**
     * @var string[][]
     */
    private static $namespaceUseDeclarationsPerTokens = [];

    public static function resolveForNamePosition(Tokens $tokens, int $classNameEndPosition): string
    {
        $classNameParts = [];
        $classNameParts[] = $tokens[$classNameEndPosition]->getContent();

        $previousTokenPointer = $classNameEndPosition - 1;

        while ($tokens[$previousTokenPointer]->getId() === T_NS_SEPARATOR) {
            --$previousTokenPointer;
            $classNameParts[] = $tokens[$previousTokenPointer]->getContent();
            --$previousTokenPointer;
        }

        $completeClassName = implode(self::NAMESPACE_SEPARATOR, $classNameParts);

        return self::resolveForName($tokens, $completeClassName);
    }

    /**
     * @return mixed[]
     */
    public static function resolveDataFromEnd(Tokens $tokens, int $end): array
    {
        $nameTokens = [];

        $previousTokenPointer = $end;

        while ($tokens[$previousTokenPointer]->isGivenKind([T_NS_SEPARATOR, T_STRING])) {
            $nameTokens[] = $tokens[$previousTokenPointer];
            --$previousTokenPointer;
        }

        /** @var Token[] $nameTokens */
        $nameTokens = array_reverse($nameTokens);
        if ($nameTokens[0]->isGivenKind(T_NS_SEPARATOR)) {
            unset($nameTokens[0]);
            // reset array keys
            $nameTokens = array_values($nameTokens);
        }

        $name = '';
        foreach ($nameTokens as $nameToken) {
            $name .= $nameToken->getContent();
        }

        return [
            'start' => $previousTokenPointer,
            'end' => $end,
            'name' => $name,
            'lastPart' => $nameTokens[count($nameTokens) - 1]->getContent(),
            'nameTokens' => $nameTokens,
        ];
    }

    public static function resolveForName(Tokens $tokens, string $className): string
    {
        // probably not a class name, skip
        if (ctype_lower($className[0])) {
            return $className;
        }

        $tokensAnalyzer = new TokensAnalyzer($tokens);
        $useDeclarations = self::getNamespaceUseDeclarations($tokens, $tokensAnalyzer->getImportUseIndexes());

        foreach ($useDeclarations as $name => $settings) {
            if ($className === $name) {
                return $settings['fullName'];
            }
        }

        return $className;
    }

    /**
     * Mimics @see NoUnusedImportsFixer::getNamespaceUseDeclarations().
     *
     * @param int[] $useIndexes
     * @return string[]
     */
    private static function getNamespaceUseDeclarations(Tokens $tokens, array $useIndexes): array
    {
        if (isset(self::$namespaceUseDeclarationsPerTokens[$tokens->getCodeHash()])) {
            return self::$namespaceUseDeclarationsPerTokens[$tokens->getCodeHash()];
        }

        $methodReflection = new ReflectionMethod(
            NoUnusedImportsFixer::class,
            'getNamespaceUseDeclarations'
        );

        $methodReflection->setAccessible(true);

        $namespaceUseDeclarations = $methodReflection->invoke(new NoUnusedImportsFixer, $tokens, $useIndexes);

        self::$namespaceUseDeclarationsPerTokens[$tokens->getCodeHash()] = $namespaceUseDeclarations;

        return $namespaceUseDeclarations;
    }
}
