<?php

declare(strict_types=1);

/**
 * Tests for Brewdocs' article resolution.
 *
 * These document the behaviour executably (CWE-22, level 6):
 *   - a real "articles/..." document resolves inside the kb directory,
 *   - a value that does NOT start with the "articles/" section is rejected,
 *   - a value that DOES start with "articles/" but embeds "../../" passes the
 *     start-of-path guard and escapes the directory.
 *
 * The exploit test deliberately asserts that the vulnerability is present: if
 * someone adds canonicalisation (e.g. realpath + a containment check), the
 * escape assertion turns red and flags that the lab no longer teaches what it
 * claims to.
 */

namespace Brewdocs\Tests;

use Brewdocs\Article;
use PHPUnit\Framework\TestCase;

final class ArticleTest extends TestCase
{
    private string $kb = '/app/kb';

    public function testLegitimateArticleStaysInsideTheDirectory(): void
    {
        $path = Article::resolve($this->kb, 'articles/espresso-extraction.md');
        self::assertSame('/app/kb/articles/espresso-extraction.md', $path);
    }

    public function testValueWithoutTheSectionPrefixIsRejected(): void
    {
        $this->expectException(\RuntimeException::class);
        Article::resolve($this->kb, 'flag.txt');
    }

    public function testPlainTraversalWithoutPrefixIsRejected(): void
    {
        // "../flag.txt" does not start with "articles/", so the guard blocks it.
        $this->expectException(\RuntimeException::class);
        Article::resolve($this->kb, '../flag.txt');
    }

    public function testPrefixedTraversalBypassesTheGuardAndEscapes(): void
    {
        // Starts with "articles/" -> guard PASSES; the embedded "../../" then
        // escapes two levels up to the data root where the flag lives.
        $path = Article::resolve($this->kb, 'articles/../../flag.txt');

        self::assertSame('/app/kb/articles/../../flag.txt', $path);
        self::assertStringContainsString('/articles/../../', $path);
    }
}
