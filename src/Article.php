<?php

declare(strict_types=1);

/**
 * Brewdocs article resolution — intentionally vulnerable (CWE-22, level 6).
 *
 * The developer confines reads to published articles with a "start-of-path"
 * check on the REQUESTED value: every real article lives under the "articles/"
 * section, so the code requires the ``doc`` parameter to START WITH
 * "articles/". The reasoning sounds right ("if it starts with articles/, it is
 * one of our articles") but it is wrong, because the value is never NORMALISED
 * before being joined onto the kb directory:
 *
 *     require str_starts_with($doc, 'articles/')     // "articles/../../flag.txt" PASSES
 *     $full = $kbDir . '/' . $doc;                    // /app/kb/articles/../../flag.txt
 *
 * "articles/../../flag.txt" begins with "articles/", so the guard is satisfied
 * — yet the embedded "../../" makes the filesystem resolve the path to
 * "/app/flag.txt", two levels ABOVE the articles directory. The check looked at
 * the prefix of the raw string and never saw the traversal.
 *
 * This is a start-of-path / prefix check without canonicalisation and is the
 * whole point of this lab. Do NOT reuse this pattern in real software.
 */

namespace Brewdocs;

final class Article
{
    /**
     * Section prefix every published article is expected to start with.
     * Real content lives under ``<kbDir>/articles/``.
     */
    public const SECTION_PREFIX = 'articles/';

    /**
     * Resolve the requested article to an on-disk path, applying the (broken)
     * start-of-path guard.
     *
     * VULNERABLE BY DESIGN: the guard uses ``str_starts_with`` on the raw,
     * un-normalised ``doc`` value, so ``articles/../../flag.txt`` passes the
     * prefix check and escapes the articles (and kb) directory.
     *
     * @throws \RuntimeException when the value does not start with the section
     */
    public static function resolve(string $kbDir, string $doc): string
    {
        // --- The guard (string prefix on the request, no canonicalisation) ---
        // "Only serve things from the articles/ section." It checks the literal
        // prefix of the un-normalised request, so "articles/../../flag.txt" —
        // which starts with "articles/" — sails straight through.
        if (!str_starts_with($doc, self::SECTION_PREFIX)) {
            throw new \RuntimeException('Rejected: not an articles/ document.');
        }
        // ---------------------------------------------------------------------

        return $kbDir . \DIRECTORY_SEPARATOR . $doc;
    }
}
