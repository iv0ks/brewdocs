# Single source of truth for the PHP version.
ARG PHP_VERSION=8.3

FROM php:${PHP_VERSION}-apache AS runtime

# Apply available OS security patches on top of the base image.
RUN apt-get update && apt-get upgrade -y && rm -rf /var/lib/apt/lists/*

# Serve the app from public/ instead of the default /var/www/html, make the
# DATA_ROOT env var visible to PHP (Apache strips the environment otherwise)
# and render a styled 404 via error.php.
ENV APACHE_DOCUMENT_ROOT=/app/public \
    DATA_ROOT=/app
RUN sed -ri 's!DocumentRoot /var/www/html!DocumentRoot ${APACHE_DOCUMENT_ROOT}!' \
        /etc/apache2/sites-available/000-default.conf \
    && sed -ri 's!<Directory /var/www/>!<Directory /app/public/>!' /etc/apache2/apache2.conf \
    && printf 'PassEnv DATA_ROOT\nErrorDocument 404 /error.php\n' \
        > /etc/apache2/conf-available/brewdocs.conf \
    && a2enconf brewdocs

WORKDIR /app

# Application code (public front controllers + src) and the served knowledge
# base. The app uses plain `require` includes (no composer runtime deps), so
# there is no vendor/ to copy — composer is only used for the dev toolchain
# in CI.
COPY src ./src
COPY public ./public
COPY kb ./kb

# Bake the challenge flag INTO the image at build time, at /app/flag.txt —
# OUTSIDE the served kb/ directory on purpose, so path traversal has a target.
# The flag is intentionally NOT committed to the repo (it would spoil the lab).
# Override with --build-arg FLAG=...
ARG FLAG=FLAG{BREWDOCS_SPILLED_THE_BEANS}
RUN printf '%s\n' "$FLAG" > /app/flag.txt

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD php -r '$c=@file_get_contents("http://127.0.0.1:80/index.php");exit($c!==false?0:1);'
