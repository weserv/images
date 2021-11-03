# Images.weserv.nl dockerized

This document describes how to use images.weserv.nl with Docker.

## Hosted image on [GitHub Container Registry](https://github.com/orgs/weserv/packages/container/package/images)

1. Pull and run the `ghcr.io/weserv/images` container.
   ```bash
   docker run -d -p 8080:80 --shm-size=1gb --name=imagesweserv ghcr.io/weserv/images:5.x
   ```
   (this maps TCP port 80 in the container to port 8080 on the Docker host)

2. Visit [`http://localhost:8080/`](http://localhost:8080/).

3. Enjoy!

## Manual installation

1. Build the container (with a specified nginx version).
   ```bash
   docker build --build-arg NGINX_VERSION=1.21.4 -t weserv/images -f docker/Dockerfile .
   ```

2. Run the container (same as above, but using the recently built tag).
   ```bash
   docker run -d -p 8080:80 --shm-size=1gb --name=imagesweserv weserv/images
   ```

3. Visit [`http://localhost:8080/`](http://localhost:8080/).

4. Enjoy!

## Useful commands

```bash
# bash commands
docker exec -it imagesweserv bash

# Check nginx configuration for correct syntax
docker exec imagesweserv nginx -t

# Reload the nginx configuration file
docker exec imagesweserv nginx -s reload

# Update RPM packages
docker exec imagesweserv dnf update -y

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
