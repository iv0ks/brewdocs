<?php

declare(strict_types=1);

/**
 * Brewdocs — styled 404 page (wired as Apache's ErrorDocument 404).
 */

require __DIR__ . '/../src/View.php';

use Brewdocs\View;

http_response_code(404);

$body = <<<HTML
<div class="error-box">
    <h1>404</h1>
    <p class="sub">We couldn't find that page.</p>
    <p><a class="back" href="/index.php">← Back to the help centre</a></p>
</div>
HTML;

echo View::layout('Not found', $body);
