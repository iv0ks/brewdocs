<?php

declare(strict_types=1);

/**
 * Brewdocs — knowledge-base index (home).
 */

require __DIR__ . '/../src/View.php';
require __DIR__ . '/../src/Articles.php';

use Brewdocs\Articles;
use Brewdocs\View;

$articles = Articles::all();

$cards = '';
foreach ($articles as $a) {
    $enc = rawurlencode($a['doc']);
    $cards .= sprintf(
        '<a class="kb-card" href="/article.php?doc=%s">'
            . '%s'
            . '<h3 class="kb-title">%s</h3>'
            . '<p class="kb-excerpt">%s</p>'
            . '<p class="kb-meta"><span>Updated %s</span><span>%s read</span></p>'
            . '</a>',
        $enc,
        View::categoryTag($a['category']),
        View::esc($a['title']),
        View::esc($a['excerpt']),
        View::esc($a['updated']),
        View::esc($a['reading'])
    );
}

$count = count($articles);

$body = <<<HTML
<section class="hero">
    <p class="eyebrow">Help Centre</p>
    <h1>How can we help you brew better?</h1>
    <p class="lede">Guides, recipes and store answers from the Brewdocs team.
        Search {$count} articles or browse the latest below.</p>
    <form class="hero-search" action="#" onsubmit="return false">
        <input type="search" placeholder="Search the knowledge base…"
            aria-label="Search the knowledge base" disabled>
        <button type="submit" disabled>Search</button>
    </form>
</section>
<section class="kb">
    <div class="kb-head">
        <h2>Popular articles</h2>
        <p class="sub">Updated weekly</p>
    </div>
    <div class="kb-grid">{$cards}</div>
</section>
HTML;

echo View::layout('Help Centre', $body);
