# Docker

This document describes how to use images.weserv.nl with Docker.

## Installation

1. Create a `config.php` from the `config.example.php` file. Adapt it according to your needs.

    ```bash
    cp config.example.php config.php
    ```

2. Build/run containers with (with and without detached mode)

    ```bash
    $ docker build . -t imagesweserv
    $ docker run \
        -v $(pwd):/var/www/imagesweserv \
        -v $(pwd)/logs/supervisor:/var/log/supervisor \
        -v /dev/shm:/dev/shm \
        -p 80:80 \
        -d \
        --name=imagesweserv \
        imagesweserv
    ```

3. Update your system host file (add images.weserv.dev)

    ```bash
    # UNIX only: get containers IP address and update host (replace IP according to your configuration)
    $ docker network inspect bridge | grep Gateway

    # unix only (on Windows, edit C:\Windows\System32\drivers\etc\hosts)
    $ sudo echo "# 172.20.0.4 images.weserv.dev" >> /etc/hosts
    ```

    **Note:** For **OS X**, please take a look [here](https://docs.docker.com/docker-for-mac/networking/) and for **Windows** read [this](https://docs.docker.com/docker-for-windows/#/step-4-explore-the-application-and-run-examples) (4th step).

4. Install images.weserv.nl

    ```bash
    $ docker exec imagesweserv composer install
    ```

5. Enjoy :-)

## Usage

Just run:
```bash
$ docker run \
    -v $(pwd):/var/www/imagesweserv \
    -v $(pwd)/logs/supervisor:/var/log/supervisor \
    -v /dev/shm:/dev/shm \
    -p 80:80 \
    -d \
    --name=imagesweserv \
    imagesweserv
```
then visit [images.weserv.dev](http://images.weserv.dev)  

## Useful commands

```bash
# bash commands
$ docker exec -it imagesweserv bash

# Composer (e.g. composer update)
$ docker exec imagesweserv composer update

# Retrieve an IP Address
$ docker inspect --format='{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' $(docker ps -f name=imagesweserv -q)
$ docker inspect $(docker ps -f name=imagesweserv -q) | grep IPAddress

# Access to redis-cli
$ docker exec -it imagesweserv redis-cli

# Access to logs
$ docker logs imagesweserv

# Check CPU consumption
$ docker stats

# Delete all containers
$ docker rm $(docker ps -aq)

# Delete all images
$ docker rmi $(docker images -q)
```