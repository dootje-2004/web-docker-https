# Notes

Setup of a Docker container with HTTPS access.

## The bare container

Start the *php:8-apache* image with ports 80 and 443 exposed, without additional configuration:

```yaml
version: '3'

services:
  app:
    build: .
    container_name: php8
    hostname: debian3
    ports:
      - "12080:80"
      - "12443:443"
```

```Dockerfile
FROM php:8-apache
```

Run the container with `docker-compose up -d --build`.

## Deprecation warning for the build option

Some investigation shows that *Docker Buildkit* isn't installed.
Execute `sudo apt-get install docker-buildx` and the warning is gone:

```tty
aldo@laptop-aldo:~/git/web-docker-https$ docker-compose up -d --build
Creating network "web-docker-https_default" with the default driver
Building app
[+] Building 0.5s (5/5) FINISHED                                                                                                                                                               docker:default
 => [internal] load build definition from Dockerfile                                                                                                                                                     0.1s
 => => transferring dockerfile: 400B                                                                                                                                                                     0.0s
 => [internal] load .dockerignore                                                                                                                                                                        0.1s
 => => transferring context: 2B                                                                                                                                                                          0.0s
 => [internal] load metadata for docker.io/library/php:8-apache                                                                                                                                          0.0s
 => [1/1] FROM docker.io/library/php:8-apache                                                                                                                                                            0.3s
 => exporting to image                                                                                                                                                                                   0.0s
 => => exporting layers                                                                                                                                                                                  0.0s
 => => writing image sha256:cf5230ba39f43dc71fcedff898090aa1fec4c0e3d1b66aa86c47143021669cb7                                                                                                             0.0s
 => => naming to docker.io/library/web-docker-https_app                                                                                                                                                  0.0s
Creating php8 ... done
```

> It is unnecessary to edit `/etc/docker/daemon.json`, or to use the shell
variable `DOCKER_BUILDKIT=1`.
> Docker Engine v23.0 and later default to Buildkit without further encouragement.
> [The Docker documentation](https://docs.docker.com/build/architecture/#install-buildx)
confirm this.

## Add HTML content and make it accessible

Point the browser to <http://localhost:12080> and we see the message
*You don't have permission to access this resource.*

Configure an *apache2* site `server.conf`:

```apache+
<VirtualHost *:80>
    ServerAdmin admin@localhost
    ServerName debian3
    ServerAlias www.debian3
    DocumentRoot /var/www/html
    
    ErrorLog ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
```

Make *Dockerfile* copy this site into the container, and have it deactivate
the standard site:

```Dockerfile
COPY server.conf /etc/apache2/sites-available/server.conf
RUN a2ensite server.conf && a2dissite 000-default.conf
```

Also insert some content:

```Dockerfile
COPY html/index.html /var/www/html/index.html
```

We now see 'Hello world!' on <http://localhost:12080/>.

## Mapping a volume

The web content is easier to edit through a mapped volume.
For that we define a volume in *docker-compose.yml*:

```yaml
    volumes:
      - "./html:/var/www/html"
```

We don't need the *COPY index.html* command in *Dockerfile* any more.

After rebuilding the container we can edit the file *html/index.html*
on the host, and see the result at <http://localhost:12080/>.
