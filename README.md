# HTTPS access to an Apache container

Demo project for setting up SSL on a container running an Apache web server.
Scripts are for Ubuntu / Debian.

## TL;DR

Prerequisites: Linux with OpenSSL, Docker and bash.
This demo uses docker-compose, but `docker run` will also work.

To get started, clone or copy this repository.
Then run these commands:

```bash
./make-ca                      # create CA key and certificate
./make-signed-certificate      # create server key and certificate
./make-docker-setup 2345 3456  # put settings in Docker config
docker-compose up -d --build   # start the Apache container
```

> The `--build` option is not needed on the first run, but it makes
sure the container is freshly initialized at each subsequent start.

If you haven't installed *docker-compose*:

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

## What is happening here exactly?

This demo demonstrates how you can set up server validation
on a local network, where the server is a Docker container
running Apache.
This has the advantage that you can try this out without
messing with existing websites, or having to install your own
Apache web server.

We'll go through the demo step by step.

### Step 1: Be your own boss

Running the *make-ca* script establishes you as a Certificate Authority.
The first time you run this script you will be prompted for a
passphrase. This passphrase is reused when you run *make-ca*
again. For a different passphrase, edit or remove the *ca-passphrase* file.

>Make sure nobody has access to *ca-root.key* or *ca-passphrase*.

You will need to run *make-ca* again when your first root certificate
expires, which we set to one year for this demo. Change it as you please.

### Step 2: Wield your powers

For each domain (i.e. server or website) that you want to validate,
run the *make-signed-certificate* script.
This uses the CA root certificate *ca-root.crt* to sign (validate)
a server key *server.key*, resulting in a signed server certificate
*server.crt*.

To create a certificate for a server other than localhost,
supply the domain name to *make-signed-certificate*.
Find out the hostname of your machine with `echo $HOSTNAME`.
Suppose your machine is called *my-laptop*.
Then the command to use is `./make-signed-certificate my-laptop`.

> If you run a website in a container (like we are doing here),
make sure to use the hostname of the Docker host as the domain name.
For the demo we use *localhost*, because that name is always valid
locally. You can visit the server from your host environment, but
you can't reach the HTTPS page from other machines.
For that you'd need a URL with your machine's network name or IP address,
but that name or address does not match the domain name in the
container's certificate. You *can* vist the unencrypted page, though.

### Step 3: Put it to work

This is where you install the signed certificate and key on
the target server, and distribute the root certificate to the clients
on your local network (*clients* being tech-speak for browsers).

The installation procedure depends on the type of web server and how it
is deployed (container or bare-metal).

#### Apache

* Create a server configuration for a VirtualHost that supports SSL.
  The *server.conf* file in this project provides a basic example.
* Copy the server certificate and key to the locations specified in
  the SSL-enabled VirtualHost configuration.
* Provide a (tiny) script that produces the passphrase, like the file
  *pk-passphrase-for-apache.sh* we created with the
  *make-signed-certificate* script.
* Add a global configuration (i.e. outside your VirtualHosts) with the
  *SSLPassPhraseDialog* directive
  that tells Apache where that passphrase script resides
  ([docs](https://httpd.apache.org/docs/2.2/mod/mod_ssl.html#sslpassphrasedialog)).
* If Apache was already running, restart it for the changes to take effect.

### Other web servers

We are not familiar with nginx, IIS or others.

### Distribution of the root certificate

How you distribute the root certificate depends on the browser.

#### Firefox

Importing the root certificate can be automated with *certutil*.
Install it with `sudo apt install libnss3-tools`.

...

#### Chrome

...

## References

<https://dockerwebdev.com/tutorials/docker-php-development/>

<https://docs.docker.com/build/architecture/#install-buildx>
