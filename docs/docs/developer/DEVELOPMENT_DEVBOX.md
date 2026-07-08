---
sidebar_position: 9
---

# Developing with Devbox

Jethro is an open source (GPL) project, with collaboration taking place mainly
on [issues on Github](https://github.com/tbar0970/jethro-pmm).

## Local development with Devbox

Jethro's system requirements are listed in README.md.

A fast way to get Jethro running from source is with [Devbox](https://www.jetify.com/devbox), a tool for isolated
development environments. Jethro's source distribution comes with a devbox install script and a basic Devbox
configuration, requiring just 1 command to bring up a Nginx web server, MariaDB database and PHP-FPM backend running
Jethro.

To get started, run `./devbox services up -b`:

```sh
$ ./devbox services up -b
Starting all services: nginx-access, nginx-error, php-fpm, jethro, jethro_database_setup, mariadb, mariadb_logs, nginx 
Process-compose is now running on port 38489
To stop your services, run `devbox services stop`
```

This will:

 - download the full `devbox` binary if you don't already have it (`./devbox` is a stub [^devbox] ).
 - download and launch MariaDB database and Nginx web server in the background (`-b`).
 - create a `jethro` database in the Devbox MariaDB

Point your browser at http://localhost:8081, and you should see Jethro's setup wizard.

### Devbox overview

With Devbox, you can:

 - `devbox shell` to enter the Jethro development environment, containing local versions of PHP, Nginx, MariaDB and
 other dev tools as specified in `devbox.json`. In this shell, `mariadb` (or `mysql`) connects to the devbox's `jethro`
 database (see below), other commands from `devbox.d/bin` are added to your `$PATH`. Ctrl-C to exit the shell.

 - `devbox run <script>` to run one of the scripts defined in `devbox.json`. E.g. `devbox run lint`.

 - `devbox services [up|up -b|down|ls]` to manage the Nginx, MariaDB and other services Devbox provides, preconfigured
 to run Jethro from source. For example, if you ran `devbox services up -b` earlier to start things, `devbox services
 ls` will show something like:

```sh
$ devbox services ls
Services running in process-compose:
PID         NAME                         NAMESPACE        STATUS           AGE          HEALTH        RESTARTS        EXIT CODE
6908        jethro                       default          Completed        0s           -             0               0
6699        nginx                        default          Running          1m48s        Ready         0               0
6698        nginx-error                  default          Running          1m48s        -             0               0
6697        mariadb                      default          Running          1m48s        Ready         0               0
6701        mariadb_logs                 default          Running          1m48s        -             0               0
6826        jethro_database_setup        default          Completed        0s           -             0               0
6696        nginx-access                 default          Running          1m48s        -             0               0
6700        php-fpm                      default          Running          1m48s        -             0               0
```

#### Devbox services

 Devbox services are defined in `process-compose.yml`, augmented with services that devbox plugins provide. Devbox
 services can be configured by editing:

  - `devbox.d/nginx/nginx.template` 
  - `devbox.d/php/php-fpm.conf`
  - `devbox.d/mariadb/my.cnf`

You will see references to environment variables in those config files, and often it's possible to customize Devbox
services by setting a relevant environment variable rather than tweaking `devbox.d/` files. E.g. to change Nginx's port
from 8081 to 80, edit `devbox.json` and set:

```json
  "env": {
    "NGINX_WEB_PORT": "80",
    ...
  },
```

Run `devbox info mariadb` / `devbox info php` / `devbox info nginx` to see what's available.


You will also see a `.devbox` directory created. This is where Devbox constructs its virtual environment. If deleted it
will be recreated on next `devbox` command. Of interest is:
 - `.devbox/process-compose.log` for service logs
 - `.devbox/virtenv/nginx/error.log` for Jethro stdout/stderr
 - `.devbox/virtenv/{mariadb,nginx,php}/process-compose.yaml` for definitions of default devbox services, augmenting `./process-compose.yml`.

#### How devbox services run Jethro

As `devbox info nginx` explains, Devbox's Nginx points to web root `devbox.d/web/`. This contains `../..` symlinks to Jethro PHP files, except for `devbox.d/web/conf.php` which configures Jethro to connect to the `jethro` MariaDB database on 127.0.0.1:3307.

We could actually just rely on Devbox's built-in `.devbox/virtenv/*/process-compose.yaml` services to bring up Jethro.
The `./process-compose.yml` adds usability tweaks, notably a `jethro_database_setup` "service" which creates the
`jethro` MariaDB user and database if it does not exist, by invoking `devbox.d/bin/jethro_db_init` (which you are free to
invoke directly). `devbox.d/bin/` contains other development scripts, and that directory is added to your `$PATH` in a
devbox shell.

Initially http://localhost:8081 will show the Jethro setup wizard, reflecting the empty state of the `jethro` database. If you'd like to load sample data, run `devbox run demodata`. To wipe the `jethro` database again, run `devbox run initdb`.


[^devbox]: The `./devbox` file in the repo root is not the devbox binary itself, but a launcher shell script. On first run it:

    1. Downloads the real `devbox` binary for this repo's pinned version from the
       [jefft/devbox](https://github.com/jefft/devbox) GitHub releases to `~/.cache/devbox/bin/v0.17-6-jeff_linux_amd64/devbox`.
    2. Creates a symlink `devbox.d/bin/devbox` → that cached binary.
    3. `exec`s the binary with the same arguments you passed.

    On subsequent runs, the script skips the download and re-execs the cached binary directly.

    The `devbox.d/bin/devbox` symlink exists so that `devbox` is always findable on `$PATH` for internal subcommands.

