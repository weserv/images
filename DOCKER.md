# Docker

This document describes how to use images.weserv.nl with Docker.

## Installation

1. Create a `app/config.lua` from the `app/config.example.lua` file. Adapt it according to your needs.

    ```bash
    cp app/config.example.lua app/config.lua
    ```

2. Build/run containers with (with and without detached mode)

    ```bash
    $ docker build . -t imagesweserv
    $ docker run \
        -v $(pwd):/var/www/imagesweserv \
        -v $(pwd)/config/nginx/conf.d:/usr/local/openresty/nginx/conf/conf.d/ \
        -v $(pwd)/logs/supervisor:/var/log/supervisor \
        -v /dev/shm:/dev/shm \
        -p 80:80 \
        -d \
        --name=imagesweserv \
        imagesweserv
    ```

3. Update your system host file (add images.weserv.local)

    ```bash
    # UNIX only: get containers IP address and update host (replace IP according to your configuration) (on Windows, edit C:\Windows\System32\drivers\etc\hosts)
    $ sudo echo $(docker network inspect bridge | grep Gateway | grep -o -E '[0-9\.]+') "images.weserv.local" >> /etc/hosts
    ```

    **Note:** For **OS X**, please take a look [here](https://docs.docker.com/docker-for-mac/networking/) and for **Windows** read [this](https://docs.docker.com/docker-for-windows/#/step-4-explore-the-application-and-run-examples) (4th step).

4. Install images.weserv.nl

    ```bash
    $ docker exec imagesweserv make dev
    ```

5. Enjoy :-)

## Usage

Just run:
```bash
$ docker run \
    -v $(pwd):/var/www/imagesweserv \
    -v $(pwd)/config/nginx/conf.d:/usr/local/openresty/nginx/conf/conf.d/ \
    -v $(pwd)/logs/supervisor:/var/log/supervisor \
    -v /dev/shm:/dev/shm \
    -p 80:80 \
    -d \
    --name=imagesweserv \
    imagesweserv
```
then visit [images.weserv.local](http://images.weserv.local)  

## Useful commands

```bash
# bash commands
$ docker exec -it imagesweserv bash

# Fancy command-line utilities for OpenResty.
$ docker exec -it imagesweserv resty

# Check nginx configuration for correct syntax
$ docker exec imagesweserv nginx -t

# Reload the nginx configuration file
$ docker exec imagesweserv nginx -s reload

# Retrieve an IP Address
$ docker inspect --format='{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' $(docker ps -f name=imagesweserv -q)
$ docker inspect $(docker ps -f name=imagesweserv -q) | grep IPAddress

# Access to redis-cli
$ docker exec -it imagesweserv redis-cli

# When you have reached the rate-limit (where 127.0.0.1 is your IP address)
$ docker exec imagesweserv redis-cli DEL c_127.0.0.1:lockout

# Make configuration changes available
$ docker exec imagesweserv supervisorctl reread

# Restarts the applications whose configuration has changed
$ docker exec imagesweserv supervisorctl update

# Access to logs
$ docker logs imagesweserv

# Check CPU consumption
$ docker stats

# Stop all containers
$ docker stop $(docker ps -aq)

# Delete all containers
$ docker rm $(docker ps -aq)

# Delete all images
$ docker rmi $(docker images -q)
```