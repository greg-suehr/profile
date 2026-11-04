#!/bin/sh
# Run squid in the foreground and stream its access log to `docker compose logs`,
# so denied requests (TCP_DENIED) are easy to spot when tuning the allowlist.
set -e
install -d -o proxy -g proxy /var/log/squid
: > /var/log/squid/access.log
chown proxy:proxy /var/log/squid/access.log
tail -F /var/log/squid/access.log &
exec squid -N -d 1 -f /etc/squid/squid.conf
