# HTTPS access to an Apache container

Demo project for setting up SSL on a container running an Apache or nginx web server.
Scripts are for Ubuntu / Debian.

## TL;DR

### Prerequisites

Linux with OpenSSL, Docker, bash and sed.
This demo uses docker-compose, but `docker run` will also work.

### Run the demo

To get started, clone or copy this repository.
Then run these commands:

```bash
./make-ca                      # create CA key and certificate
./make-signed-certificate      # create server key and certificate
./make-apache-setup 2345 3456  # put Apache settings in Docker config
./make-apache-setup 4567 5678  # put nginx settings in Docker config
docker-compose up -d           # start the two containers
```

> For subsequent runs, use the `--build` option for docker-compose to make
  sure the container is freshly re-initialized.

&nbsp;

> Run *docker-compose* without the `-d` or `--detach` option to see the
  server logs in the terminal.

If you haven't installed *docker-compose*:

```bash
docker run -d --rm --name ssl-test -h localhost -p 2345:80 -p 3456:443 -v ./html:/var/www/html $(docker build -q .)
```

> Docker may complain that the *build* option has been deprecated
  in favor of *buildx*.
  See [below](#deprecated-build-option) how to handle that.

Import `ca-root.key` into your browser of choice, and point it to
<http://localhost:2345/> and <https://localhost:3456/>
to verify that the container runs correctly.

Stop the container with `docker-compose down` or
`docker stop ssl-test-apache ssl-test-nginx`,
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
You can try out this demo without messing with existing websites
or having to install your own Apache web server.

We'll go through the demo step by step.

### Step 1: Be your own boss

Running the *make-ca* script establishes you as a Certificate Authority.
The first time you run this script you will be prompted for a
passphrase. This passphrase is reused when you run *make-ca*
again. For a different passphrase, edit or remove the *ca-passphrase* file.

> Make sure nobody has access to *ca-root.key* or *ca-passphrase*.

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
  The *apache.conf* file in this project provides a basic example.
* Copy the server certificate and key to the locations specified
  in *apache.conf*.
* Provide a (tiny) script that produces the passphrase, like the file
  *pk-passphrase-provider.sh* we create with the
  *make-apache-setup* script.
* Add a global configuration (i.e. outside your VirtualHosts) with the
  *SSLPassPhraseDialog* directive
  that tells Apache where that passphrase script resides
  ([docs](https://httpd.apache.org/docs/2.2/mod/mod_ssl.html#sslpassphrasedialog)).
* If Apache was already running, restart it for the changes to take effect.

All these steps are taken care of by the project's *Dockerfile.apache*.
For non-containerized Apache installs, the *Dockerfile.apache* basically shows you
what has to be done.
You could even convert it to a shell script with a little editing.

#### nginx

* Create a server configuration that supports SSL.
  The *nginx.conf* file in this project provides a basic example.
* Copy the server certificate and key to the locations specified
  in *nginx.conf*.
* Copy the private-key passphrase to the location specified in *nginx.conf*.
* If nginx was already running, restart it for the changes to take effect.

All these steps are taken care of by the project's *Dockerfile.nginx*.
For non-containerized nginx installs, the *Dockerfile.nginx* basically shows you
what has to be done.
You could even convert it to a shell script with a little editing.

Useful documentation:

* <https://docs.nginx.com/nginx/admin-guide/basic-functionality/managing-configuration-files/>
* <https://nginx.org/en/docs/http/configuring_https_servers.html>

### Distribution of the root certificate

How you distribute the root certificate depends on the browser.

#### Firefox

* To import the CA certificate, open Firefox and select *Settings* from the
  application menu in the upper right corner.
* In the left menu, select the *Privacy & Security* section.
  Alternatively, you can type `about:preferences#privacy` in the address bar.
* Scroll down until you can click the *View Certificates* button.
  This opens the *Certificate Manager*.
* Select the *Authorities* tab and click *Import*.
* Select the *ca-root.crt* file from the demo.
* Check the box that says *Trust this CA to identify websites*
  and click *OK*. You'll see a new Authorities entry *AAAA*.
* Navigate to  <https://localhost:3456/> to confirm the certificate works.

> Import of the CA certificate can be automated with *certutil* (part of the
*libnss3-tools* package), but that is beyond the scope of this demo.

#### Chrome

* Open Chrome and select *Settings* from the application menu.
* Select *Privacy and security* in the left menu.
* Click on the *Security* section.
* Click *Manage device certificates*.
  Alternatively, you can type `chrome://settings/certificates`
  in the address bar.
* Select the *Authorities* tab en click *Import*.
* Select the *ca-root.crt* file from the demo.
* Check the box that says *Trust this certificate for identifying websites*
  and click *OK*. You'll see a new Authorities entry *org-AAAA*.
* Navigate to  <https://localhost:3456/> to confirm the certificate works.

## Deprecated build option

If you get a warning about *docker build* being deprecated, install the
*Buildkit* utility with `sudo apt-get install docker-buildx`.

> It is no longer necessary to edit `/etc/docker/daemon.json`,
  or to use the shell variable `DOCKER_BUILDKIT=1`.
  Docker Engine v23.0 and later default to Buildkit without further encouragement.
  [The Docker documentation](https://docs.docker.com/build/architecture/#install-buildx)
  confirms this.
