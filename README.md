# Brewdocs

![CWE-22](https://img.shields.io/badge/CWE--22-Path%20Traversal-red?style=flat-square)
![PHP](https://img.shields.io/badge/PHP-Apache-777BB4?style=flat-square&logo=php&logoColor=white)
![Docker](https://img.shields.io/badge/Lab-Docker-2496ED?style=flat-square&logo=docker&logoColor=white)
[![GHCR](https://img.shields.io/badge/ghcr.io-iv0ks%2Fbrewdocs-6f4e37?logo=docker)](https://github.com/iv0ks/brewdocs/pkgs/container/brewdocs)

**Brewdocs is an intentionally vulnerable application built for security education.** It is a small coffee-shop knowledge base / help centre with a deliberate [path traversal](https://owasp.org/www-community/attacks/Path_Traversal)
(CWE-22) flaw — hidden behind a "start-of-path" check that forgets to normalise the path — so you can practice finding and exploiting it in a safe and controlled environment.

<br>

<div align="center">

| 🛡️ Vulnerability | 🎯 Difficulty | 🛠️ Stack | 🚩 Entry point |
| :--- | :--- | :--- | :--- |
| Path Traversal (CWE-22) | ⭐⭐⭐⭐ Level 6 (Start-of-path) | PHP · Apache | `GET /article.php?doc=<name>` |

</div>

<br>

> [!NOTE]
> The guided, spoiler-free path is [GUIDE.md](GUIDE.md); the full answer waits in [SOLUTION.md](SOLUTION.md) whenever you want it.

<br>

## 📂 Project layout

The repository is organised so the vulnerable code is easy to find and reason
about. Three areas, clearly separated: the **application** (PHP) with its public
front controllers in `public/` and its logic in `src/`, the **served data** in
`kb/` (the knowledge base the app is _meant_ to expose), and the **tooling**
around it (Docker image with Apache + mod_php, CI/CD, linters). The challenge
flag is deliberately kept _out_ of both the repo and the served directory — see
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
time — _outside_ the served `kb/` dir — so traversal can reach it while the flag
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

<br>

#### Option A — just the app

Start only the `brewdocs` service (Compose skips `traefik` when you name a
service explicitly):

```bash
docker compose up -d --build brewdocs
```

The help centre is now reachable directly at:

- **<http://localhost:8101>**

That is all you need to start playing with Burp Suite.

<br>

#### Option B — full stack with Traefik (HTTPS + hostname)

Bring up everything to also expose the app through Traefik with TLS:

```bash
docker compose up -d --build
```

Now the _same_ app is reachable two ways at once:

- **<http://localhost:8101>** — direct, no TLS (handy for quick testing).
- **<https://brewdocs.iv0ks.dev>** — proxied by Traefik with a real certificate.

Traefik routing is defined in `tools/traefik/`. TLS uses the ACME DNS-01
challenge via Namecheap, so Traefik needs those API credentials — copy
`.env.example` to `.env` and fill them in (the `.env` file is gitignored).

<br>

> [!CAUTION]
> The `brewdocs.iv0ks.dev` hostname must resolve to the host running the stack.
> For purely local experiments, Option A (localhost) is simpler and needs no
> DNS or certificates.

<br>

#### Developing without Docker

For quick frontend iteration you can use PHP's built-in server (the flag is
seeded locally and gitignored):

```bash
printf 'FLAG{BREWDOCS_SPILLED_THE_BEANS}\n' > flag.txt
DATA_ROOT="$PWD" php -S 127.0.0.1:8101 -t public
```

<br>

## 🎯 The Challenge

### The Scenario

Brewdocs is a coffee-shop help centre: it renders knowledge-base articles and,
to stay inside its content, only serves documents whose path **starts with** the
`articles/` section. The developer treats that start-of-path check as the fence.
You're going to prove that a prefix check on an un-normalised path keeps nothing
in.

### Your objective

Read a file that lives _outside_ the knowledge-base directory — specifically, capture the flag in `flag.txt`.

> [!NOTE]
> The flag has the format `FLAG{...}`.

### The rules

Everything happens over HTTP against the running app — no need to
read the source to win (though you can). The flag is **not** in the repo: it's
baked into the container, outside `kb/`, so you have to actually reach it.

> [!TIP]
> For the full method, the **[Learning Guide](GUIDE.md)** walks you through it.
