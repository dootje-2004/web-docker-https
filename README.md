# HTTPS access to an Apache container

Demo project for setting up SSL on a container running an Apache web server.
Scripts are for Ubuntu / Debian.

## TL;DR

* Prerequisites: Linux with OpenSSL, Docker and bash.
This demo uses docker-compose, but `docker run` will also work.
* Clone or copy this repository.

```bash
./make-ca  # create CA key and certificate
./make-signed-certificate  # create server key and certificate
./make-docker-setup 2345 3456  # put settings in Docker config
docker-compose up -d  # start the Apache container.
```

If you haven't installed *docker-compose*, do

```bash
docker run -d --rm --name webtest -h localhost -p 2345:80 -p 3456:443 -v ./html:/var/www/html $(docker build -q .)
```

Import `ca-root.key` into your browser, and point it to
<http://localhost:2345/> and <https://localhost:3456/>
to verify that the container runs correctly.

Stop the container with `docker-compose down` or `docker stop webtest`,
depending on how you started it.

## Context

Picture this: You are running a web app on your local network.
The app is for personal use and is not exposed to the outside world.
Even so, you want to connect to the app over SSL/TLS.
One reason could be that your app requires a username and password,
and some browsers (like Firefox) complain that unencrypted logins
are a bad idea, even on a local network.

You could equip your server with a self-signed SSL certificate,
something that e.g. Apache provides out of the box (the *snakeoil*
certificate and key).
The problem with that is that browsers mistrust self-signed server
certificates, and with good reason. Your visitors will see
your web page, but only after moving an annoying warning out of the way.

Homebrew websites that are accessible from the internet can be
certified by utilities like *Let's Encrypt* aka *certbot*.
Internal sites don't have that option, since validation through
certbot requires internet exposure: certbot will interrogate your
site over both HTTP and HTTPS.

The least intrusive way (for your visitors) of validating
your internal website is to act as your own Certificate Authority,
and in that role issue a signed certificate for each of your
internal sites. The only annoying thing this leaves you with is
the distribution of the CA root certificate to all of your
visitors' browsers, but this is a one-time action (until your
CA root certificate expires, of course :grin:).

## Setup

This project demonstrates how you can set up server validation
on a local network, where the server is a Docker container
running Apache.
This has the advantage that you can try this out without
messing with existing websites, or having to install your own
Apache web server.

To get going, clone this repository. Next, establish yourself
as a Certificate Authority by running
the *make-ca* script with `./make-ca`.
The first time you run this script you will be prompted for a
CA passphrase. This passphrase is reused when you run *make-ca*
again, unless you edit or remove the file *ca-passphrase*.
Make sure nobody has access to the *ca-root.key* or *ca-passphrase*
files.

For each domain (i.e. server or website) that you want to validate,
run the *make-signed-certificate* script.
This uses the CA root certificate to sign (validate) each server
certificate.

> If you run a website in a container (like we are doing here),
make sure to use the hostname of the Docker host as the domain name.
> If they differ, the host can't be reached if the URL uses the
container hostname, or the container refuses the connection if the URL
uses the host's name (because the URL refers to a domain that is not
in the SSL certificate).

Find out the hostname for your machine by running `echo $HOSTNAME`.
Suppose your machine is called *my-laptop*.
Then the command to use is `./make-signed-certificate my-laptop`.



## References

<https://dockerwebdev.com/tutorials/docker-php-development/>

<https://docs.docker.com/build/architecture/#install-buildx>
