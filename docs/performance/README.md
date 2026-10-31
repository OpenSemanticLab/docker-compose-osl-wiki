# Performance

## stack-mem.sh

Memory pressure across a compose stack. Container RSS is rarely the signal, so
each service is also asked for its own saturation metric.

```bash
./docs/performance/stack-mem.sh            # the stack in the current directory
./docs/performance/stack-mem.sh <project>  # a compose project by name
./docs/performance/stack-mem.sh <path>     # the stack in that directory
```

Services are matched on the compose service name and the image rather than on
container names, so a stack that calls its database `mariadb` or runs no
triplestore is handled without changes.

### Reading the output

| Section | What matters |
|---|---|
| containers | Only useful to spot a container near its cgroup limit. Usually everything looks idle here even when a service is struggling |
| jvm | `FGC`, the number of full stop the world collections, and `FGCT`, the seconds spent in them. `OLD%` sustained above roughly 80, without falling after a collection, is the one to act on: the heap is full and the process collects instead of working. `META%` is a separate pool and is usually a false alarm, because metaspace is uncapped unless someone set `MaxMetaspaceSize`, so a high reading is utilisation of a region that grows on demand rather than a limit being reached. Check `jstat -gc` for `MC` against `MU` before believing it |
| memcached | `evictions`. A high hit rate hides eviction churn, because the cache still answers most reads while constantly discarding entries to make room |
| mysql | The pool hit rate, not the pool size. A pool smaller than the data is fine as long as the working set fits |
| php | `web workers` against the configured ceiling. A saturated pool queues requests and looks exactly like slowness, while container memory still reads as idle. `php web time` is the execution limit a request gets: a render that exceeds it is killed, so a page measured at minutes on the command line may simply fail in a browser. The cli limits are usually unbounded and say nothing about page rendering |
| connections | `conns peak` for the database and `limit hit` for memcached. A pool that has touched its ceiling once will do so again under the same load |
| db threads | `threads running` is the database's real concurrency, which is not the connection count: most connections sit idle. `thread reuse` below roughly 90 percent means `thread_cache_size` is too small and every connection pays for a new thread |

### Example reading

From one instance, as an illustration of what pressure looks like. These are a
starting point for comparison, not targets.

```
graphdb     OLD 43%  META 98.02%  FGC 16390  FGCT 3194 s
memcached   41 MiB of 64 MiB, 6,402,312 evictions, hit rate 96.67%
db          pool 128 MiB, data 301 MiB, pool hit rate 99.99%
mediawiki   web limit 128M
```

Two of those are worth acting on. Blazegraph metaspace sits at 98% with 16k full
collections, and memcached has evicted 6.4 million entries from a 64 MiB cache,
which on this stack holds sessions, parser cache and message cache. The database
is healthy despite the small pool: the working set fits, so the pool hit rate is
99.99% and enlarging it would buy nothing.
