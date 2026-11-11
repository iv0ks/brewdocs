<?php

declare(strict_types=1);

/**
 * Brewdocs demo content.
 *
 * A small, in-memory index of knowledge-base articles so the help centre
 * behaves like a real product. Each entry's ``doc`` maps to a file under the
 * kb directory (see Config::kbDir()); they are rendered through article.php.
 */

namespace Brewdocs;

final class Articles
{
    /**
     * @return list<array{
     *   doc:string, title:string, category:string, excerpt:string,
     *   updated:string, reading:string
     * }>
     */
    public static function all(): array
    {
        return [
            [
                'doc' => 'articles/espresso-extraction.md',
                'title' => 'Dialling in the perfect espresso shot',
                'category' => 'Brewing',
                'excerpt' => 'Grind size, dose and yield — how to chase a '
                    . '25–30s extraction without chasing your tail.',
                'updated' => '2024-06-21',
                'reading' => '6 min',
            ],
            [
                'doc' => 'articles/milk-steaming.md',
                'title' => 'Steaming silky microfoam',
                'category' => 'Brewing',
                'excerpt' => 'Stretch, then texture. Positioning the wand for '
                    . 'glossy, paint-like milk every time.',
                'updated' => '2024-06-14',
                'reading' => '4 min',
            ],
            [
                'doc' => 'articles/grinder-maintenance.md',
                'title' => 'Weekly grinder maintenance',
                'category' => 'Equipment',
                'excerpt' => 'Burr cleaning, retention and when to recalibrate '
                    . 'after swapping beans.',
                'updated' => '2024-05-30',
                'reading' => '5 min',
            ],
            [
                'doc' => 'articles/loyalty-program.md',
                'title' => 'How the Brewdocs loyalty program works',
                'category' => 'Store',
                'excerpt' => 'Stamps, rewards and how to link a card to the '
                    . 'mobile app.',
                'updated' => '2024-06-02',
                'reading' => '3 min',
            ],
        ];
    }

    /**
     * @return array{
     *   doc:string, title:string, category:string, excerpt:string,
     *   updated:string, reading:string
     * }|null
     */
    public static function find(string $doc): ?array
    {
        foreach (self::all() as $article) {
            if ($article['doc'] === $doc) {
                return $article;
            }
        }

        return null;
    }
}
