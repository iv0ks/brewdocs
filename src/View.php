<?php

declare(strict_types=1);

/**
 * Tiny view helpers for Brewdocs.
 *
 * Plain PHP rendering (no template engine) — enough for a small help-centre /
 * knowledge-base UI. The layout gives every page the same chrome: a warm top
 * bar with a search box and a footer, so the app reads like a real product.
 */

namespace Brewdocs;

final class View
{
    public static function esc(string $value): string
    {
        return htmlspecialchars($value, \ENT_QUOTES, 'UTF-8');
    }

    /** Render the shared chrome around a page body. */
    public static function layout(string $title, string $body): string
    {
        $t = self::esc($title);

        return <<<HTML
        <!doctype html>
        <html lang="en">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>{$t} · Brewdocs Help Centre</title>
            <link rel="icon" type="image/svg+xml" href="/favicon.svg">
            <link rel="stylesheet" href="/style.css">
        </head>
        <body>
            <header class="masthead">
                <div class="masthead-inner">
                    <a class="brand" href="/index.php">
                        <span class="brand-mark" aria-hidden="true">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                                <path d="M4 8h12v5a5 5 0 0 1-5 5H9a5 5 0 0 1-5-5V8Z"
                                    stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                                <path d="M16 9h2.5a2.5 2.5 0 0 1 0 5H16"
                                    stroke="currentColor" stroke-width="1.8"/>
                                <path d="M8 3c-.6.8-.6 1.7 0 2.5M11.5 3c-.6.8-.6 1.7 0 2.5"
                                    stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                            </svg>
                        </span>
                        <span class="brand-name">Brewdocs</span>
                        <span class="brand-sub">Help Centre</span>
                    </a>
                    <form class="search" action="#" onsubmit="return false">
                        <input type="search" placeholder="Search articles…" aria-label="Search" disabled>
                    </form>
                </div>
            </header>
            <main class="content">{$body}</main>
            <footer class="footer">
                <span><strong>Brewdocs</strong> · the coffee people's knowledge base</span>
                <span class="muted">Articles are served via
                    <code>/article.php?doc=&lt;name&gt;</code></span>
            </footer>
        </body>
        </html>
        HTML;
    }

    /** Pill tag for an article category. */
    public static function categoryTag(string $category): string
    {
        $slug = strtolower($category);

        return '<span class="cat cat-' . self::esc($slug) . '">'
            . self::esc($category) . '</span>';
    }

    /**
     * Render a very small subset of Markdown (headings, paragraphs, bullet
     * lists) to HTML, so kb/*.md articles display nicely without a dependency.
     */
    public static function markdown(string $md): string
    {
        $html = '';
        $lines = preg_split('/\R/', $md) ?: [];
        $inList = false;

        foreach ($lines as $line) {
            $line = rtrim($line);

            if (str_starts_with($line, '- ')) {
                if (!$inList) {
                    $html .= '<ul>';
                    $inList = true;
                }
                $html .= '<li>' . self::esc(substr($line, 2)) . '</li>';
                continue;
            }

            if ($inList) {
                $html .= '</ul>';
                $inList = false;
            }

            if ($line === '') {
                continue;
            }

            if (str_starts_with($line, '## ')) {
                $html .= '<h2>' . self::esc(substr($line, 3)) . '</h2>';
            } elseif (str_starts_with($line, '# ')) {
                $html .= '<h1>' . self::esc(substr($line, 2)) . '</h1>';
            } else {
                $html .= '<p>' . self::esc($line) . '</p>';
            }
        }

        if ($inList) {
            $html .= '</ul>';
        }

        return $html;
    }
}
