# Brewdocs

[![GHCR](https://img.shields.io/badge/ghcr.io-iv0ks%2Fbrewdocs-6f4e37?logo=docker)](https://github.com/iv0ks/brewdocs/pkgs/container/brewdocs)


**Brewdocs is an intentionally vulnerable application built for security education.** It is a small coffee-shop knowledge base / help centre with a deliberate [path traversal](https://owasp.org/www-community/attacks/Path_Traversal)
(CWE-22) flaw — hidden behind a "start-of-path" check that forgets to normalise
the path — so you can practice finding and exploiting it in a safe and
controlled environment.

<br>

## 🗂️ Project layout

The repository is organised so the vulnerable code is easy to find and reason
about. Three areas, clearly separated: the **application** (PHP) with its public
front controllers in `public/` and its logic in `src/`, the **served data** in
`kb/` (the knowledge base the app is *meant* to expose), and the **tooling**
around it (Docker image with Apache + mod_php, CI/CD, linters). The challenge
flag is deliberately kept *out* of both the repo and the served directory — see
the note under the tree.

```
brewdocs/
├── public/                 # web root (DocumentRoot, served by Apache)
│   ├── index.php           # knowledge-base index (home)
│   ├── article.php         # the VULNERABLE article endpoint
│   ├── error.php           # styled 404 (Apache ErrorDocument)
│   ├── style.css           # help-centre UI styles (warm, cream/brown)
│   └── favicon.svg
├── src/                    # application logic (PSR-4 Brewdocs\)
│   ├── Article.php         # the start-of-path guard (the bug lives here)
│   ├── Config.php          # paths resolved from DATA_ROOT
│   ├── Articles.php        # demo article index
│   └── View.php            # layout + tiny markdown renderer
├── kb/articles/            # served knowledge-base articles (the "allowed" dir)
├── tests/                  # PHPUnit tests (assert the traversal)
├── tools/traefik/          # reverse-proxy config (static + dynamic routing)
├── SOLUTION.md             # exploit writeup + the fix (spoilers)
├── SECURITY.md             # security policy (it's a lab — read before reporting)
├── .github/workflows/      # integration · delivery · security · release
├── Dockerfile              # single image: php:8.3-apache (mod_php)
├── docker-compose.yml      # brewdocs + traefik stack
├── composer.json           # autoload + dev tools (phpunit, php-cs-fixer, phpstan)
├── phpstan.neon · .php-cs-fixer.php · phpunit.xml
└── .env.example            # Traefik ACME credentials template (copy to .env)
```

The PHP code lives in `public/` + `src/` and renders articles from
`kb/articles/`, just like a real help centre would. The target `flag.txt` is
**not** in the repo: the Docker image writes it to `/app/flag.txt` at build
time — *outside* the served `kb/` dir — so traversal can reach it while the flag
stays out of version control.

<br>

## 🚀 The Lab

Brewdocs runs as a Docker Compose stack:

- **`brewdocs`** — the help centre: a single `php:8.3-apache` image where Apache
  serves static files and executes the PHP directly. Published on `8101`.
- **`traefik`** — a reverse proxy that serves the app over HTTPS on a real
  hostname. Optional: only needed for the domain-based access.

Because the stack attaches to an **external** Docker network (`iv0ks-dev`),
create it once before the first run:

```bash
docker network create iv0ks-dev
```

#### Option A — just the app

Start only the `brewdocs` service (Compose skips `traefik` when you name a
service explicitly):

```bash
docker compose up -d --build brewdocs
```

The help centre is now reachable directly at:

- **<http://localhost:8101>**

That is all you need to start playing with Burp Suite.

#### Option B — full stack with Traefik (HTTPS + hostname)

Bring up everything to also expose the app through Traefik with TLS:

```bash
docker compose up -d --build
```

Now the *same* app is reachable two ways at once:

- **<http://localhost:8101>** — direct, no TLS (handy for quick testing).
- **<https://brewdocs.iv0ks.dev>** — proxied by Traefik with a real certificate.

Traefik routing is defined in `tools/traefik/`. TLS uses the ACME DNS-01
challenge via Namecheap, so Traefik needs those API credentials — copy
`.env.example` to `.env` and fill them in (the `.env` file is gitignored).

#### Developing without Docker

For quick frontend iteration you can use PHP's built-in server (the flag is
seeded locally and gitignored):

```bash
printf 'FLAG{BREWDOCS_SPILLED_THE_BEANS}\n' > flag.txt
DATA_ROOT="$PWD" php -S 127.0.0.1:8101 -t public
```

<br>

## 🎯 The Challenge

Brewdocs renders knowledge-base articles through an endpoint that only wants to
serve things from the `articles/` section — so it checks that the requested
document **starts with** `articles/`. Your goal:

> **Read a file that lives _outside_ the knowledge-base directory —
> specifically, capture the flag stored in `flag.txt`.**

The flag has the format `FLAG{...}`.

| | |
|---|---|
| **Vulnerability** | Path Traversal (CWE-22) |
| **Difficulty** | ⭐⭐⭐⭐ Level 6 — start-of-path validation without normalisation |
| **Stack** | PHP · Apache (mod_php) |
| **Entry point** | `GET /article.php?doc=<name>` |

<details>
<summary>Hint 1</summary>
Open an article and watch the request in Burp — the filename goes into a
<code>?doc=articles/&lt;name&gt;.md</code> parameter.
</details>

<details>
<summary>Hint 2</summary>
A plain <code>../flag.txt</code> is rejected. What exactly is the filter
checking — the <em>start</em> of your input? Can you satisfy that and still
climb out?
</details>

<details>
<summary>Hint 3</summary>
Keep the <code>articles/</code> prefix the filter wants, then traverse:
<code>articles/../../flag.txt</code>.
</details>

A full walkthrough lives in [SOLUTION.md](SOLUTION.md) (spoilers!).

<br>

## 🧰 About this repo (DevSecOps showcase)

Beyond the vulnerability, this repo is a template for shipping a small service
with sane engineering practices:

- **Conventional Commits** enforced via **commitlint** (pre-commit hook).
- **pre-commit** suite: `php-cs-fixer` + `phpstan` (local hooks), `gitleaks`
  (secret scanning), `hadolint` (Dockerfile lint), whitespace/EOF fixers.
- **Centralized CI/CD** via reusable workflows in
  [`iv0ks/cicd-hub`](https://github.com/iv0ks/cicd-hub): Integration
  (secrets → vuln → lint → tests on PR), Delivery (build & push the image to
  GHCR on tag), Security (scheduled CVE scan), Release (release-please).
- **Dependabot**: daily updates for Composer, Docker and GitHub Actions.
- **Hardened containers**: OS patch layer, non-root runtime, healthcheck.

> 💡 **Teaching note:** image/dependency scanners look for *known CVEs*. They
> will **not** flag Brewdocs's path traversal, because it is a business-logic
> flaw in our own code, not a vulnerable dependency. Catching that class of bug
> needs SAST / manual review / DAST — exactly what you're practicing here.

### Running the tests

```bash
composer install
composer test     # phpunit
composer stan     # phpstan
composer lint     # php-cs-fixer (dry-run)
```

The suite documents the behaviour executably: a real `articles/…` document
resolves inside the directory, a value without the prefix is rejected, and
`articles/../../flag.txt` passes the start-of-path guard and reaches the flag —
so if someone adds canonicalisation, the exploit test turns red.

<br>

## 📄 License

[MIT](LICENSE) — see also [SECURITY.md](SECURITY.md).
