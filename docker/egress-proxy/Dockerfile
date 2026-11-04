# syntax=docker/dockerfile:1
#
# Allowlisting HTTPS proxy. The toolchain containers have no route to the
# internet; during `make install` / `make audit` this is their only way out,
# and it only lets them reach the package registries in squid.conf.

FROM debian:bookworm-slim@sha256:99c0b25c46d0214d5a2091092e7908907dcaecd6a962cde0ac700acee222b6f3

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends squid; \
    rm -rf /var/lib/apt/lists/*

COPY squid.conf /etc/squid/squid.conf
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod 0755 /usr/local/bin/entrypoint.sh && squid -k parse -f /etc/squid/squid.conf

EXPOSE 3128
HEALTHCHECK --interval=2s --timeout=2s --retries=15 \
    CMD bash -c '</dev/tcp/127.0.0.1/3128' || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
