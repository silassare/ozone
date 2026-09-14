# Docker deployment samples

Samples to run an OZone project with Docker, next to the nginx (`conf/nginx/`) and Apache
(`conf/apache/`) configurations. Adapt them to your project and your hosting.

The default, recommended setup is nginx (`web`) in front of PHP-FPM (`app`). The Apache image
(`--profile apache`) is only an alternative for hosts that need it; it is only built when its
profile is used. Every file here is a starting point: change, drop or add services as the
project needs.

With nginx already running on the host as a reverse proxy, either keep `web` and proxy to
`OZ_HTTP_PORT`, or drop `web` and let the host nginx talk FastCGI to `app` (publish its port 9000
on `127.0.0.1` only, and serve the project's `public/` directory from the host).

| File                    | Role                                                                              |
| ----------------------- | --------------------------------------------------------------------------------- |
| `Dockerfile`            | Images: `app` (PHP-FPM with the project), `web` (nginx), `apache` (Apache, alone) |
| `compose.yaml`          | `app`, `web`, `cron`, `worker`, `db`; optional `redis`, `minio`, `clamav`, `apache` |
| `nginx/default.conf`    | nginx in front of `app`, serving the `api` scope                                  |
| `apache/ozone.conf`     | Lets the scope's `.htaccess` route requests to `index.php`                        |
| `php.ini`               | Production PHP settings (opcache without timestamp checks, upload limits)         |
| `docker-entrypoint.sh`  | Generates the ORM classes, runs migrations (opt-in), builds for production, starts |
| `install-extensions.sh` | The PHP extensions OZone needs (ext-redis on demand)                              |

## Use

1. Copy this directory to the project root as `docker/`.
2. In the project `.env` (read by OZone and by Compose), point OZone at the containers:

    ```sh
    OZ_DB_RDBMS=mysql
    OZ_DB_HOST=db
    OZ_DB_NAME=ozone
    OZ_DB_USER=ozone
    OZ_DB_PASS=a-long-random-password
    ```

3. Build and start, from the project root:

    ```sh
    docker compose --env-file .env -f docker/compose.yaml up -d --build
    ```

    Apply migrations with `OZ_MIGRATE_ON_START=1` (only `app` runs them) or once by hand:
    `docker compose --env-file .env -f docker/compose.yaml exec app php vendor/bin/oz migrations run`.

The site listens on port `OZ_HTTP_PORT` (8080 by default); put your TLS proxy in front of it and
list it in `oz.proxies` so OZone trusts its forwarding headers. `nginx/default.conf` serves the `api`
scope: for another scope, change its `root` (`public/<scope>`), or `APACHE_DOCUMENT_ROOT` in the
`apache` image.

Volumes: `data` (every state directory: stateful settings, private files, public files, TempFS and
the file-backed state stores -- `public/*/static` are symlinks into it),
`logs` (`.ozone/logs`), `db-data`. `cron` runs the scheduler, `oz cron work`: a tick every minute runs
the due tasks, the hourly garbage collection among them -- which requests then leave to cron by
themselves (`OZ_GC_PROBABILITY`) -- in its own container rather than in the ones answering requests.
`worker` runs `oz jobs work`.

## Production build and preloading

For the containers that answer requests (`app`, `apache`), the entrypoint runs `oz project build`
after the migrations: it compiles what requests would otherwise compile at first use -- the `.env`,
the settings, the route tables, a class map -- and writes `.ozone/preload.php`, which it points
`opcache.preload` to (`conf.d/zz-ozone-preload.ini`): PHP compiles the classes a request loads once,
when it starts. Set `OZ_PRELOAD=0` to opt out. Preloaded code only changes when PHP restarts:
recreate the containers after deploying new code, as a new image requires anyway.

## Optional services

- **Redis** (Redis job store and cache): build with `OZ_WITH_REDIS=1` and start with
  `--profile redis`; in `.env`: `OZ_REDIS_HOST=redis` and `OZ_REDIS_PASSWORD`.
- **MinIO** (object storage for files): start with `--profile minio`; in `.env`:
  `OZ_MINIO_ENDPOINT=http://minio:9000`, `OZ_MINIO_ACCESS_KEY`, `OZ_MINIO_SECRET_KEY`,
  `OZ_MINIO_BUCKET`, and `OZ_MINIO_PUBLIC_ENDPOINT` when clients reach MinIO at another URL.
  Then map the storage slots to the driver in `app/settings/oz.files.storages.php`:

    ```php
    use OZONE\Core\FS\Drivers\MinioStorage;
    use OZONE\Core\FS\FS;

    return [
    	FS::DEFAULT_STORAGE => MinioStorage::class,
    	FS::PUBLIC_STORAGE  => MinioStorage::class,
    	FS::PRIVATE_STORAGE => MinioStorage::class,
    ];
    ```

- **ClamAV** (virus scan of new files; clamd needs about 3 GB of memory): start with
  `--profile clamav`; in `.env`: `OZ_CLAMAV_HOST=clamav`. Then turn the scan on in
  `app/settings/oz.files.scan.php`:

    ```php
    return [
    	'OZ_FILE_SCAN_ENABLED' => true,
    	// Scan in the `worker` instead of during the upload:
    	// 'OZ_FILE_SCAN_MODE' => 'async',
    ];
    ```

    clamd takes a minute or two to load its signatures after a start: meanwhile, uploads fail with
    `OZ_FILE_SCAN_FAILED` in the default `sync` mode, and wait in the queue in `async` mode.

- **Apache** instead of nginx and PHP-FPM:
  `docker compose --env-file .env -f docker/compose.yaml --profile apache up -d db apache`
  (add `cron` and `worker` as needed).

PostgreSQL instead of MySQL: replace the `db` image with `postgres:16` (`POSTGRES_DB`,
`POSTGRES_USER`, `POSTGRES_PASSWORD`, data in `/var/lib/postgresql/data`) and set
`OZ_DB_RDBMS=postgresql`.
