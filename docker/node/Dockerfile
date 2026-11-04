# syntax=docker/dockerfile:1
#
# Dev toolchain image for npm / Encore. Third-party JS runs here, not as your
# macOS user. Pinned by digest; bump tag + digest together.

FROM node:22-bookworm-slim@sha256:c3de60bf2f9dd0ac6370e6117950ff62d6e339527e7472301c9c78a017978392

# Containers run as your host UID (see Makefile), which has no passwd entry.
RUN mkdir -p /home/dev /cache/npm \
 && chmod 1777 /home/dev /cache /cache/npm

ENV HOME=/home/dev \
    npm_config_cache=/cache/npm \
    npm_config_update_notifier=false \
    npm_config_fund=false

WORKDIR /app
