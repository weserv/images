# Docker instructions

This document describes how to use weserv/images with Docker.

## Installation

1. Create a `app/config.lua` from the `app/config.example.lua` file. Adapt it according to your needs.
    ```bash
    cp app/config.example.lua app/config.lua
    ```

2. Build/run the container.
    ```bash
    $ docker build . -t weserv/images
    $ docker run \
        --shm-size=1gb \
        -p 8080:80 \
        -d \
        --name=weserv \
        weserv/images
    ```
    (this maps TCP port 80 in the container to port 8080 on the Docker host)

3. Visit [`http://localhost:8080/`](http://localhost:8080/).

4. Enjoy :-)

## Useful commands

```bash
# bash commands
$ docker exec -it weserv bash

# Check nginx configuration for correct syntax
$ docker exec weserv nginx -t

# Reload the nginx configuration file
$ docker exec weserv nginx -s reload

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

# View the error logs
$ docker exec weserv tail /usr/local/openresty/nginx/logs/error.log
$ docker exec weserv tail /usr/local/openresty/nginx/logs/lua-error.log

# Check CPU consumption
$ docker stats

# Stop all containers
$ docker stop $(docker ps -aq)

# Delete all containers
$ docker rm $(docker ps -aq)

# Delete all images
$ docker rmi $(docker images -q)
```