#!/bin/bash
# Memory pressure across a docker compose stack.
#
# Container RSS is rarely the signal, so each service is also asked for its own
# saturation metric: full GCs for a JVM, evictions for memcached, buffer pool
# hit rate for mysql.
#
#   stack-mem.sh            the stack in the current directory
#   stack-mem.sh <project>  a compose project by name
#   stack-mem.sh <path>     the stack in that directory
#
# Services are matched on the compose service name and the image, not on
# container names, so a stack that calls its database "mariadb" or its
# triplestore "blazegraph" is handled the same way.

set -u
arg=${1:-}

project_from_dir() {
    local cid
    cid=$(cd "$1" 2>/dev/null && docker compose ps -q 2>/dev/null | head -1)
    [ -z "$cid" ] && return 1
    docker inspect "$cid" --format '{{index .Config.Labels "com.docker.compose.project"}}' 2>/dev/null
}

if [ -n "$arg" ] && [ -d "$arg" ]; then
    PROJECT=$(project_from_dir "$arg")
elif [ -n "$arg" ]; then
    PROJECT=$arg
else
    PROJECT=$(project_from_dir .)
fi

if [ -z "${PROJECT:-}" ]; then
    echo "No compose project found. Run inside a stack directory, or pass a project name or path." >&2
    exit 1
fi

mapfile -t ROWS < <(docker ps --filter "label=com.docker.compose.project=$PROJECT" \
    --format '{{.Names}}\t{{.Label "com.docker.compose.service"}}\t{{.Image}}' | sort)

if [ ${#ROWS[@]} -eq 0 ]; then
    echo "No running containers for project '$PROJECT'." >&2
    exit 1
fi

echo "project: $PROJECT"
echo
echo "== containers =="
printf '  %-34s %-14s %10s %10s %7s\n' NAME SERVICE MEM LIMIT MEM%
for r in "${ROWS[@]}"; do
    IFS=$'\t' read -r name svc _img <<<"$r"
    docker stats --no-stream --format '{{.MemUsage}} {{.MemPerc}}' "$name" 2>/dev/null \
      | sed 's#/# #' \
      | awk -v n="$name" -v s="$svc" '{printf "  %-34s %-14s %10s %10s %7s\n", n, s, $1, $2, $3}'
done

jvm_header=0
for r in "${ROWS[@]}"; do
    IFS=$'\t' read -r name svc img <<<"$r"
    line=""
    if printf '%s %s' "$svc" "$img" | grep -qiE 'elastic|opensearch'; then
        line=$(docker run --rm --network "container:$name" curlimages/curl:latest -s --max-time 8 \
                 'http://localhost:9200/_nodes/stats/jvm' 2>/dev/null \
               | python3 -c '
import json,sys
try:
    n=list(json.load(sys.stdin)["nodes"].values())[0]["jvm"]
    o=n["gc"]["collectors"].get("old",{})
    print("%s %s %s %s" % (n["mem"]["heap_used_percent"], "-",
          o.get("collection_count",0), round(o.get("collection_time_in_millis",0)/1000,1)))
except Exception: pass' 2>/dev/null)
    elif docker exec "$name" sh -c 'command -v jstat >/dev/null 2>&1' >/dev/null 2>&1; then
        pid=$(docker exec "$name" sh -c 'pgrep -f "[j]ava" | head -1' 2>/dev/null)
        [ -n "$pid" ] && line=$(docker exec "$name" \
            sh -c "jstat -gcutil $pid 2>/dev/null | awk 'NR==2{print \$4, \$5, \$9, \$10}'" 2>/dev/null)
    fi
    [ -z "$line" ] && continue
    if [ $jvm_header -eq 0 ]; then
        echo; echo "== jvm (full GC is stop the world; metaspace is not heap) =="
        printf '  %-34s %6s %7s %9s %10s\n' NAME OLD% META% FGC FGCT
        jvm_header=1
    fi
    # shellcheck disable=SC2086
    set -- $line
    printf '  %-34s %6s %7s %9s %10s\n' "$name" "${1:--}" "${2:--}" "${3:--}" "${4:--}"
done

for r in "${ROWS[@]}"; do
    IFS=$'\t' read -r name svc img <<<"$r"
    printf '%s %s' "$svc" "$img" | grep -qiE 'memcach' || continue
    echo; echo "== memcached (evictions are the signal, not bytes) =="
    docker run --rm --network "container:$name" busybox:latest \
        sh -c 'echo stats | nc -w 3 127.0.0.1 11211' 2>/dev/null | python3 -c '
import sys
v={p[1]:p[2] for p in (l.split() for l in sys.stdin.read().splitlines()) if len(p)==3}
if not v: print("  unreachable"); raise SystemExit
used=int(v.get("bytes",0)); cap=int(v.get("limit_maxbytes",0)) or 1
h=int(v.get("get_hits",0)); m=int(v.get("get_misses",0))
print("  used        %.1f MiB of %.0f MiB (%.0f%%)" % (used/2**20, cap/2**20, 100*used/cap))
print("  evictions   %s" % format(int(v.get("evictions",0)), ","))
print("  items       %s" % format(int(v.get("curr_items",0)), ","))
print("  hit rate    %.2f%%" % (100*h/(h+m) if h+m else 0))
print("  conns       %s of %s, limit hit %s times" % (v.get("curr_connections","?"), v.get("max_connections","?"), v.get("listen_disabled_num","?")))' 2>/dev/null || echo "  unreachable"
done

for r in "${ROWS[@]}"; do
    IFS=$'\t' read -r name svc img <<<"$r"
    printf '%s %s' "$svc" "$img" | grep -qiE 'mysql|mariadb|percona' || continue
    echo; echo "== $svc (pool hit rate matters more than pool size) =="
    docker exec "$name" sh -c '
      P=${MYSQL_ROOT_PASSWORD:-${MARIADB_ROOT_PASSWORD:-}}
      mysql -uroot -p"$P" -N -B -e "
        SELECT CONCAT(\"  buffer pool      \", ROUND(@@innodb_buffer_pool_size/1048576), \" MiB\");
        SELECT CONCAT(\"  data on disk     \", ROUND(SUM(data_length+index_length)/1048576), \" MiB\")
          FROM information_schema.tables
          WHERE table_schema NOT IN (\"information_schema\",\"performance_schema\",\"mysql\",\"sys\");
        SELECT CONCAT(\"  threads running  \", r2.v, \" (concurrency now, not connections)\")
          FROM (SELECT variable_value v FROM performance_schema.global_status
                 WHERE variable_name=\"Threads_running\") r2;
        SELECT CONCAT(\"  thread reuse     \", ROUND(100*(1-c.v/GREATEST(n.v,1)),2), \" % of \", FORMAT(n.v,0), \" connections\")
          FROM (SELECT variable_value v FROM performance_schema.global_status
                 WHERE variable_name=\"Threads_created\") c,
               (SELECT variable_value v FROM performance_schema.global_status
                 WHERE variable_name=\"Connections\") n;
        SELECT CONCAT(\"  conns peak       \", m.v, \" of \", @@max_connections, \" (now \", t.v, \")\")
          FROM (SELECT variable_value v FROM performance_schema.global_status
                 WHERE variable_name=\"Max_used_connections\") m,
               (SELECT variable_value v FROM performance_schema.global_status
                 WHERE variable_name=\"Threads_connected\") t;
        SELECT CONCAT(\"  pool hit rate    \", ROUND(100*(1-r.v/GREATEST(q.v,1)),4), \" %\")
          FROM (SELECT variable_value v FROM performance_schema.global_status
                 WHERE variable_name=\"Innodb_buffer_pool_reads\") r,
               (SELECT variable_value v FROM performance_schema.global_status
                 WHERE variable_name=\"Innodb_buffer_pool_read_requests\") q;
      " 2>/dev/null' || echo "  unreachable"
done

for r in "${ROWS[@]}"; do
    IFS=$'\t' read -r name svc img <<<"$r"
    docker exec "$name" sh -c 'command -v php >/dev/null 2>&1' >/dev/null 2>&1 || continue
    echo; echo "== $svc (php) =="
    docker exec "$name" sh -c '
      cur=$(pgrep -c "apache2|httpd|php-fpm" 2>/dev/null || echo 0)
      max=$(grep -RhoE "^[[:space:]]*(MaxRequestWorkers|pm.max_children)[[:space:]]+[0-9]+" \
              /etc/apache2/mods-enabled/ /usr/local/etc/php-fpm.d/ /etc/php/*/fpm/pool.d/ 2>/dev/null \
            | grep -oE "[0-9]+$" | head -1)
      echo "  web workers      ${cur} of ${max:-?}"
      # effective values, not the file: conf.d is read after the main php.ini
      # and this image ships its limits there, so grepping php.ini lies.
      # php -i avoids quoting trouble inside this single quoted sh -c block.
      cdir=$(ls -d /etc/php/*/apache2/conf.d 2>/dev/null | head -1)
      pini=$(ls /etc/php/*/apache2/php.ini 2>/dev/null | head -1)
      eff=$(PHP_INI_SCAN_DIR="$cdir" php -c "$pini" -i 2>/dev/null)
      mem=$(echo "$eff" | awk "/^memory_limit =>/{print \$3}")
      # the cli sapi forces max_execution_time to 0 whatever the ini says, so
      # it cannot be read the way memory_limit can. Report what the web config
      # is set from instead.
      tmo=${PHP_MAX_EXECUTION_TIME:-}
      echo "  php web memory   ${mem:-?}"
      echo "  php web time     ${tmo:-?}s   (a render exceeding this is killed)"
      echo "  php cli memory   $(php -r "echo ini_get(\"memory_limit\");")"' 2>/dev/null || echo "  unreachable"
done
