#!/bin/sh
set -eu
# This network namespace belongs only to the disposable load client.
/sbin/tc qdisc add dev eth0 root netem delay 25ms rate 20mbit
exec php tests/performance/run.php "$@"
