# Brewdocs — Learning Guide

> A guided, **non-spoiler** path to solving this lab. It teaches the *method*,
> not the answer — the exact winning payload and the flag stay in
> [SOLUTION.md](SOLUTION.md).

## Before you start

Read the shared **[Path Traversal field guide](https://github.com/iv0ks/appsec-labs/blob/main/docs/path-traversal.md)**
first. It covers what path traversal (CWE-22) is, why it appears, and the manual
testing workflow with **Burp Suite** and **OWASP ZAP**. This guide assumes it.

## What this level teaches

**Level 6 — start-of-path validation without normalisation.** Brewdocs serves
knowledge-base articles and confines reads with a "start-of-path" check: the
requested document must *begin with* the `articles/` section. It sounds sound —
"if it starts inside articles/, it is one of our articles" — but the value is
never canonicalised, so a request that starts with the required prefix and then
climbs out with `../` satisfies the check and still escapes. This is the most
convincing broken defence in the series, and the closest to real-world mistakes.

- **Entry point:** `GET /article.php?doc=<name>`
- **The broken defence:** `doc` must start with `articles/`, checked on the raw
  (un-normalised) string.
- **Served directory:** `kb/articles/` · **target:** a file two levels above it.

## How to approach it

1. Open an article through your proxy and capture the `doc` parameter — note it
   already carries the `articles/` prefix.
2. Baseline it, then try a plain `../flag.txt`: rejected, because it lacks the
   required prefix. The filter clearly inspects the *start* of your input.
3. So satisfy the filter **and** traverse: keep the `articles/` prefix the check
   wants, then walk back out. How many levels up is the target from
   `kb/articles/`?
4. Send the path as-is and confirm the escape.

## Going further

Start-of-path and prefix checks are everywhere (URL routing, S3 key policies,
allow-lists). The same flaw — comparing a prefix on an un-normalised string —
recurs well beyond the filesystem.

## Why it matters

This is the bug that most often survives code review, because the check looks
genuinely defensive. Learning that containment is only real *after*
canonicalisation — `realpath()` then compare — is the capstone lesson of the
series.

When you have captured the flag (or want to check the fix), read
[SOLUTION.md](SOLUTION.md).
