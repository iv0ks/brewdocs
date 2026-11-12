# Brewdocs: Learning Guide

![CWE-22](https://img.shields.io/badge/CWE--22-Path%20Traversal-red?style=flat-square)
![PHP](https://img.shields.io/badge/PHP-Apache-777BB4?style=flat-square&logo=php&logoColor=white)
![Docker](https://img.shields.io/badge/Lab-Docker-2496ED?style=flat-square&logo=docker&logoColor=white)

**Brewdocs is an intentionally vulnerable application built for security education.** It is a small coffee-shop knowledge base / help centre with a deliberate [path traversal](https://owasp.org/www-community/attacks/Path_Traversal)
(CWE-22) flaw — hidden behind a "start-of-path" check that forgets to normalise the path — so you can practice finding and exploiting it in a safe and controlled environment.

<br>

> [!TIP]
> A guided, **non-spoiler** path to solving this lab. It teaches the *method*, not the answer — the exact winning payload and the flag stay in [SOLUTION.md](SOLUTION.md).

<br>

## 🧬 What path traversal is

Here is the whole idea in one sentence:

> **Path traversal happens when an app builds a file path out of user input and
> then trusts that path to stay where it's supposed to.**

A web app usually wants to serve files from *one* folder — here, the
`kb/articles/` section. The intended deal is: "give me an article, I'll render
it *from this section*." The catch is that the folder boundary is a
**convention**, not a **rule** the code enforces. Filesystems understand `..` as
"go up one level", so a path like `articles/../something` is a perfectly valid
instruction to leave the section. If the guard checks the *raw string* instead
of the *resolved path*, the app obeys.

Think of `kb/articles/` as a room and the requested document as directions.
"`articles/espresso-extraction.md`" means "the espresso article in this room."
`articles/../../something` means "start at this room's door — then walk back out
down the hallway." Brewdocs checks that your directions *begin* at the right
door, but never checks where they actually *lead*.

That "filename → file read" shape is the **sink** you're training your eye to
spot. Once you can recognise it on sight, you can find this bug in seconds.

> Want the deeper background — history, variants, encoding tricks, and the full
> manual-testing workflow with **Burp Suite** and **OWASP ZAP**? Read the shared
> **[Path Traversal field guide](https://github.com/iv0ks/appsec-labs/blob/main/docs/path-traversal.md)**.
> This lab is a hands-on application of it.

<br>

## 🎓 What this level teaches

**Level 6 — start-of-path validation without normalisation.** Brewdocs confines
reads with a "start-of-path" check: the requested document must *begin with* the
`articles/` section. It sounds sound — "if it starts inside articles/, it is one
of our articles" — but the value is never canonicalised, so a request that
starts with the required prefix and then climbs out with `../` satisfies the
check and still escapes. This is the most convincing broken defence in the
series, and the closest to real-world mistakes.

- **Entry point:** `GET /article.php?doc=<name>`
- **The broken defence:** `doc` must start with `articles/`, checked on the raw
  (un-normalised) string.
- **Served directory:** `kb/articles/` · **target:** a file two levels above it.

## 🪧 How to approach it

1. Open an article through your proxy and capture the `doc` parameter — note it
   already carries the `articles/` prefix. Re-send it for your **baseline**.
2. Probe: a plain `../flag.txt` is rejected, because it lacks the required
   prefix. The filter clearly inspects the *start* of your input.
3. So satisfy the filter **and** traverse: can you keep the `articles/` prefix
   the check wants, and *then* walk back out? Think about how many levels up the
   target sits from `kb/articles/`.
4. Confirm the escape. Keep the path **as-is** so `../` isn't normalised before
   it is sent (`curl --path-as-is`, or edit the raw request).

## 🕵️‍♂️ Going further

Start-of-path and prefix checks are everywhere — URL routing, S3 key policies,
allow-lists. The same flaw (comparing a prefix on an un-normalised string)
recurs well beyond the filesystem. The labs run as a non-root user inside
Docker, so the bug is real but the blast radius is contained on purpose.

## 🤔 Why it matters

This is the bug that most often survives code review, because the check looks
genuinely defensive. Learning that containment is only real *after*
canonicalisation — resolve the path, *then* compare — is the capstone lesson of
the whole series.

---

When you have captured the flag (or want to check the fix and the *why* behind
it), continue to **[SOLVE → SOLUTION.md](SOLUTION.md)**.
