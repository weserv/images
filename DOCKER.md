# Docker

This document describes how to use images.weserv.nl with Docker.

## Installation

1. Build/run containers with (with and without detached mode)

    ```bash
    docker build . \
        -t imagesweserv \
        --build-arg NGINX_VERSION=1.17.6
    docker run \
        --shm-size=1gb \
        -p 80:80 \
        -d \
        --name=imagesweserv \
        imagesweserv
    ```

2. Update your system host file (add images.weserv.test)

    ```bash
    # UNIX only: get containers IP address and update host (replace IP according to your configuration) (on Windows, edit C:\Windows\System32\drivers\etc\hosts)
    sudo echo $(docker network inspect bridge | grep Gateway | grep -o -E '[0-9\.]+') "images.weserv.test" >> /etc/hosts
    ```

    **Note:** For **OS X**, please take a look [here](https://docs.docker.com/docker-for-mac/networking/) and for **Windows** read [this](https://docs.docker.com/docker-for-windows/networking/).

3. Enjoy :-)

## Usage

Just run:
```bash
docker run \
    --shm-size=1gb \
    -p 80:80 \
    -d \
    --name=imagesweserv \
    imagesweserv
```
then visit [images.weserv.test](http://images.weserv.test)

## Useful commands

```bash
# bash commands
docker exec -it imagesweserv bash

# Check nginx configuration for correct syntax
docker exec imagesweserv nginx -t

# Reload the nginx configuration file
docker exec imagesweserv nginx -s reload

# Retrieve an IP Address
docker inspect --format='{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' $(docker ps -f name=imagesweserv -q)
docker inspect $(docker ps -f name=imagesweserv -q) | grep IPAddress

# Access to logs
docker logs imagesweserv

# Check CPU consumption
docker stats

# Stop all containers
docker stop $(docker ps -aq)

# Delete all containers
docker rm $(docker ps -aq)

# Delete all images
docker rmi $(docker images -q)
```
