# syntax=docker/dockerfile:1
#
# Dev toolchain image for npm / Encore. Third-party JS runs here, not as your
# macOS user. Pinned by digest; bump tag + digest together.

FROM node:24-bookworm-slim@sha256:dc26ae92c9fb15cf6b1d63e20f0944b2efe0f115b20e00be48fb23fceb061c54

# Containers run as your host UID (see Makefile), which has no passwd entry.
RUN mkdir -p /home/dev /cache/npm \
 && chmod 1777 /home/dev /cache /cache/npm

ENV HOME=/home/dev \
    npm_config_cache=/cache/npm \
    npm_config_update_notifier=false \
    npm_config_fund=false

WORKDIR /app
