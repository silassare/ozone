#!/bin/sh
# HTTP throughput of OZone next to Laravel and Symfony (`make benchmark-http`).
#
# Each server runs alone, pinned to CPUs 0-3, and wrk (CPUs 4-7) measures two routes on it: one
# answering "pong" as text, one answering {"hello":"world"} as JSON. A warm-up run comes first, and
# is not counted. BENCH_SERVERS, BENCH_DURATION, BENCH_CONNECTIONS and BENCH_THREADS override the
# defaults below.
set -eu

cd "$(dirname "$0")"

DC="docker compose -f compose.yaml"
SERVERS="${BENCH_SERVERS:-ozone-classic ozone-worker laravel-classic laravel-octane symfony-classic symfony-worker}"
DURATION="${BENCH_DURATION:-20s}"
CONNECTIONS="${BENCH_CONNECTIONS:-64}"
THREADS="${BENCH_THREADS:-4}"

paths() {
	case "$1" in
		ozone-*) echo "/runtime-probe/ping /runtime-probe/json /runtime-probe/db" ;;
		laravel-*) echo "/api/ping /api/json /api/db" ;;
		symfony-*) echo "/ping /json /db" ;;
	esac
}

$DC build --quiet >/dev/null
$DC --profile tools build --quiet wrk >/dev/null

echo "| Server | Route | Requests/s | p50 | p99 | Errors |"
echo "| --- | --- | ---: | ---: | ---: | ---: |"

for server in $SERVERS; do
	$DC up -d --force-recreate --wait "$server" >/dev/null 2>&1 || {
		echo "| $server | - | failed to start | | | |"
		$DC logs --tail 30 "$server" >&2
		$DC stop "$server" >/dev/null 2>&1
		continue
	}

	for path in $(paths "$server"); do
		url="http://$server:8080$path"

		$DC run --rm wrk wrk -t"$THREADS" -c"$CONNECTIONS" -d5s "$url" >/dev/null 2>&1
		out=$($DC run --rm wrk wrk -t"$THREADS" -c"$CONNECTIONS" -d"$DURATION" --latency "$url" 2>/dev/null)

		rps=$(echo "$out" | awk '/Requests\/sec/ {print $2}')
		p50=$(echo "$out" | awk '$1 == "50%" {print $2}')
		p99=$(echo "$out" | awk '$1 == "99%" {print $2}')
		errors=$(echo "$out" | awk '/Non-2xx/ {n += $NF} /Socket errors/ {n += 1} END {print n + 0}')

		echo "| $server | $path | $rps | $p50 | $p99 | $errors |"
	done

	$DC stop "$server" >/dev/null 2>&1
done
