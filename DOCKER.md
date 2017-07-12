# Docker

This document describes how to use images.weserv.nl with Docker and [docker-compose (1.7 or higher)](https://docs.docker.com/compose/).

## Installation

1. Create a `config.php` from the `config.example.php` file. Adapt it according to your needs.

    ```bash
    cp config.example.php config.php
    ```

2. Build/run containers with (with and without detached mode)

    ```bash
    $ docker-compose build
    $ docker-compose up -d
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
    $ docker-compose exec php bash
    $ composer install
    ```

5. Enjoy :-)

## Usage

Just run `docker-compose up -d`, then visit [images.weserv.dev](http://images.weserv.dev)  

## Components

The dockerized images.weserv.nl consists of the following components:

- docker images
  1. a [PHP / libvips](docker/Dockerfile) image 
  2. a standard [nginx](https://hub.docker.com/_/nginx/) server image
  3. a standard [Redis](https://hub.docker.com/_/redis/) docker image
- a [composer-file](composer.json) for managing the images.weserv.nl dependencies
- and the [docker-compose.yml](docker-compose.yml)-file which connects all components

This results in the following running containers:

```bash
$ docker-compose ps
        Name                      Command               State                    Ports
--------------------------------------------------------------------------------------------------------
imagesweserv_nginx_1   nginx -g daemon off;             Up      0.0.0.0:443->443/tcp, 0.0.0.0:80->80/tcp
imagesweserv_php_1     docker-php-entrypoint php-fpm    Up      9000/tcp
imagesweserv_redis_1   docker-entrypoint.sh redis ...   Up      6379/tcp
`````

## Useful commands

```bash
# bash commands
$ docker-compose exec php bash

# Composer (e.g. composer update)
$ docker-compose exec php composer update

# Retrieve an IP Address (here for the nginx container)
$ docker inspect --format='{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' $(docker ps -f name=nginx -q)
$ docker inspect $(docker ps -f name=nginx -q) | grep IPAddress

# Access to redis-cli
$ docker-compose exec redis redis-cli

# Check CPU consumption
$ docker stats

# Delete all containers
$ docker rm $(docker ps -aq)

# Delete all images
$ docker rmi $(docker images -q)
```