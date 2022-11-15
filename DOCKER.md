# Docker instructions

This document describes how to use weserv/images with Docker.

## Installation

1. Create a `config.php` from the `config.example.php` file. Adapt it according to your needs.
    ```bash
    cp config.example.php config.php
    ```

2. Build/run the container.
    ```bash
    $ docker build . -t weserv/images
    $ docker run \
        -d \
        -v $(pwd):/var/www/imagesweserv \
        -v $(pwd)/logs:/var/log/supervisor \
        -p 8080:80 \
        --shm-size=1gb \
        --name=weserv \
        weserv/images
    ```
    (this maps TCP port 80 in the container to port 8080 on the Docker host)

3. Install composer packages.
    ```bash
    $ docker exec weserv composer install
    ```

4. Visit [`http://localhost:8080/`](http://localhost:8080/).

5. Enjoy :-)

## Useful commands

```bash
# bash commands
$ docker exec -it weserv bash

# Composer (e.g. composer update)
$ docker exec weserv composer update

# Retrieve an IP Address
$ docker inspect --format='{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' $(docker ps -f name=weserv -q)
$ docker inspect $(docker ps -f name=weserv -q) | grep IPAddress

# Access to redis-cli
$ docker exec -it weserv redis-cli

# When you have reached the rate-limit (where 127.0.0.1 is your IP address)
$ docker exec weserv redis-cli DEL c_127.0.0.1:lockout

# Make configuration changes available
$ docker exec weserv supervisorctl reread

# Restarts the applications whose configuration has changed
$ docker exec weserv supervisorctl update

# Access to logs
$ docker logs weserv

# Check CPU consumption
$ docker stats

# Stop all containers
$ docker stop $(docker ps -aq)

# Delete all containers
$ docker rm $(docker ps -aq)

# Delete all images
$ docker rmi $(docker images -q)
```