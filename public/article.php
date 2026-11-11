<?php

declare(strict_types=1);

/**
 * Brewdocs — article view (intentionally vulnerable).
 *
 *     GET /article.php?doc=espresso-extraction.md   -> renders the article
 *     GET /article.php?doc=../flag.txt              -> escapes via the broken
 *                                                      start-of-path guard
 *
 * See src/Article.php for the vulnerability writeup (CWE-22, level 6): the
 * guard requires the built path to START WITH the kb directory, but never
 * normalises it, so "<kbDir>/../flag.txt" passes the prefix check yet resolves
 * one level above kb/.
 */

require __DIR__ . '/../src/Config.php';
require __DIR__ . '/../src/Article.php';
require __DIR__ . '/../src/Articles.php';
require __DIR__ . '/../src/View.php';

use Brewdocs\Article;
use Brewdocs\Articles;
use Brewdocs\Config;
use Brewdocs\View;

$doc = $_GET['doc'] ?? '';

if (!is_string($doc) || $doc === '') {
    http_response_code(400);
    echo View::layout('Missing article', '<div class="error-box"><h1>400</h1>'
        . '<p class="sub">No article requested.</p>'
        . '<p><a class="back" href="/index.php">← Back to the help centre</a></p></div>');

    return;
}

try {
    $path = Article::resolve(Config::kbDir(), $doc);
} catch (\RuntimeException $e) {
    http_response_code(400);
    echo View::layout('Blocked', '<div class="error-box"><h1>400</h1>'
        . '<p class="sub">' . View::esc($e->getMessage()) . '</p>'
        . '<p><a class="back" href="/index.php">← Back to the help centre</a></p></div>');

    return;
}

if (!is_file($path)) {
    http_response_code(404);
    echo View::layout('Not found', '<div class="error-box"><h1>404</h1>'
        . '<p class="sub">That article does not exist.</p>'
        . '<p><a class="back" href="/index.php">← Back to the help centre</a></p></div>');

    return;
}

$raw = (string) file_get_contents($path);
$meta = Articles::find($doc);

$title = $meta['title'] ?? $doc;
$crumbCat = $meta['category'] ?? 'Article';
$rendered = View::markdown($raw);

$safeTitle = View::esc($title);
$safeCrumbCat = View::esc($crumbCat);

$body = <<<HTML
<p class="crumbs"><a href="/index.php">Help Centre</a> ›
    <span>{$safeCrumbCat}</span></p>
<article class="article">
    <header class="article-head">
        <h1>{$safeTitle}</h1>
    </header>
    <div class="article-body">{$rendered}</div>
</article>
<p><a class="back" href="/index.php">← Back to the help centre</a></p>
HTML;

echo View::layout($title, $body);
