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
    hostname: example.com
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

```apache
<VirtualHost *:80>
    ServerAdmin admin@example.com
    ServerName example.com
    ServerAlias www.example.com
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

Remove the *COPY index.html* command from *Dockerfile*.

After rebuilding the container we can edit the file *html/index.html*
on the host, and see the result at <http://localhost:12080/>.

## SSL/TLS

> OpenSSL comes with documentation. Use `man openssl` and
`man openssl-<command>` to educate yourself.

### Private server key

It starts with a private key *server.key*:
`openssl genpkey -algorithm rsa -out server.key`.
This produces an unencrypted, PEM-formatted key of 2048 bits,
using 2 primes and an exponent value of 65537.
The file (ASCII only, so `cat`, `more` and `less` all work)
starts with *-----BEGIN PRIVATE KEY-----*.

> Many online examples use the `openssl genrsa` command.
The OpenSSL docs prefer `openssl genpkey`.

To encrypt is, we need to specify a cipher (like des3 or aes) and supply
a passphrase:
`openssl genpkey -algorithm rsa -aes256 -pass pass:helloworld -out server.key`.
The public key is included in the *server.key* file, so it is essentially a pair.

> We encrypt the key with AES. The common 3DES cipher
[is being withdrawn](https://www.cryptomathic.com/news-events/blog/3des-is-officially-being-retired).

If we omit the passphrase from the command, we get prompted
to enter a passphrase twice.
The file *server.key* (still ASCII-only) now starts with
*-----BEGIN ENCRYPTED PRIVATE KEY-----*.

> You can check if your passphrase is correct with
`openssl rsa -noout -in server.key -passin pass:helloworld`.

### Create a signing request

Have the key signed (i.e. verified) by a Certificate Authority (CA).
For this, create a Certificate Signing Request (CSR).
A CSR takes the public key from our public/private pair and adds some identifying
fields (company, domain, address, e-mail) to it.
This is what the `openssl req` command is for:
`openssl req -new -key server.key -out server.csr`
will read *server.key* and create *server.csr*.
It will ask us to repeat the passphrase for *server.key* to ensure
our signing request is legit.
It will also prompt us for identifying information like names and
an address.

To provide all inputs on the command line, use
`openssl req -new -key server.key -out server.csr -passin pass:helloworld -subj "/C=NL/ST=Limburg/L=Weert/O=example/OU=example/CN=example.com" -addext "subjectAltName=email:admin@example.com"`
The CSR file starts with the line *----BEGIN CERTIFICATE REQUEST-----*.

### Be your own Certificate Authority

The CA verifies the CSR's origin and converts it into a certificate (CRT) *server.crt*
using its CA root certificate *CAroot.crt*.
The CA root certificate is self-signed, because the chain of trust has to start somewhere.
To create a root certificate, the CA needs a private key, just like the server above:
`openssl genpkey -algorithm rsa -aes256 -pass pass:iamgroot -out ca-root.key`

From this CA root key we create a self-signed certificate:
`openssl req -x509 -new -noenc -key ca-root.key -passin pass:iamgroot -days 365 -out ca-root.crt -subj "/C=NL/ST=Limburg/L=Weert/O=CertOrg/OU=CertOrg/CN=CertOrg"`

The `-x509` argument produces a certificate instead of a signing request.
This is what makes the certificate self-signed.

We also specify `-noenc`, because we don't want to be prompted for a password.
This option was formerly called `-nodes`.

Optionally we could have included a *digest* argument, e.g. `-sha256`.
This specifies the hashing algorithm for the request or certificate.

> *Self-signed certificates* are CRTs for which the CA is the same as the issuer of
the public key.
Most browsers (like Firefox) distrust those, hence the extra step
involving a CA certificate.
The CA root certificate being self-signed is not a problem, because - again -
the chain of trust has to start somewhere.

### Sign the request

The last step is to validate the server CSR with the CA root certificate:
`openssl x509 -in server.csr -req -CA ca-root.crt -CAkey ca-root.key -passin pass:iamgroot -out server.crt`

### Deployment

The server holds both its public certificate *server.crt* and its private *server.key*.
In Apache, these are referred to as *SSLCertificateFile* and *SSLCertificateKeyFile*,
respectively.
Define a directory `/etc/apache2/ssl` to hold them.
This directory will be created by the `COPY` command in *Dockerfile*.

A client receives the server CRT (i.e. the CA-validated public key)
as part of an HTTPS response. They check it with the CA's CRT.
For that, the CA root certificate needs to be available.
The root certificates of commercial CAs are built into the browser.
Our *ca-root.crt* must be imported manually into Firefox or Chrome.

> Key and certificate files sometimes have a *.pem* extension, referring to
Privacy Enhanced Mail. It is more descriptive to use *.key* and
*.crt*, *.cer* or *.cert* instead.

<https://www.baeldung.com/openssl-self-signed-cert>

<https://arminreiter.com/2022/01/create-your-own-certificate-authority-ca-using-openssl/>

## Server name

On start, Docker gives the warning

```tty
php8   | AH00558: apache2: Could not reliably determine the server's fully qualified domain name, using 127.0.0.1. Set the 'ServerName' directive globally to suppress this message
```

First check: there is indeed an entry `192.168.176.2    localhost` in `/etc/hosts`.
Apparently this does not pass the Apache test.
Changing *docker-compose.yml* to read `hostname: example.com` solves the problem.
Also make sure to change the name in *server.conf*.
Apache demands that the server name and the domain name in the certificate match.

## Server key not found

The `SSLPassPhraseDialog` directive is [not allowed in a VirtualHost specification](https://www.apachelounge.com/viewtopic.php?t=7981).
It must live in another configuration file.
We should copy this file to `/etc/apache2/conf-available` en enable it with `a2enconf server-ssl`.
There is no `conf.d` directory to begin with.

## Certificate not trusted by Firefox

When reaching for <https://localhost:12443>, Firefox tells us
*Websites prove their identity via certificates.
Firefox does not trust this site because it uses a certificate that is not valid for localhost:12443.*

This makes sense, since the certificate was issued for *example.com*.
We switch to `laptop-aldo`, since that is the name of the host machine.
Re-create the certificates with this domain name and rebuild the container.

We can now reach <http://laptop-aldo:12080>, but <https://laptop-aldo:12443> is not even reachable.

This time, try `network_mode: host` in *docker-compose.yml*, as suggested
[here](https://stackoverflow.com/a/48284369/5548255).
This exposes the container's ports directly to the host, so we need to avoid conflicts
by changing the `Listen` directives in Apache and matching the ports in our
VirtualHosts accordingly. We pick ports 12080 and 12443.
This changes nothing.

Go back to bridged networking.
12080 now works (as it did before), but Firefox still complains about HTTPS.
Since there is nothing running on the host at port 443, we switch the container back
to the standard HTTPS port. Still no dice.

Firefox shows details of the problem:

```text
https://laptop-aldo/

Unable to communicate securely with peer: requested domain name does not match the server’s certificate.

HTTP Strict Transport Security: false
HTTP Public Key Pinning: false

Certificate chain:

-----BEGIN CERTIFICATE-----
MIIDVTCCAj0CFBQgF+6egNAsfH6ZLGM66gYBjI6aMA0GCSqGSIb3DQEBCwUAMGkx
CzAJBgNVBAYTAk5MMRAwDgYDVQQIDAdMaW1idXJnMQ4wDAYDVQQHDAVXZWVydDEQ
MA4GA1UECgwHQ2VydE9yZzEQMA4GA1UECwwHQ2VydE9yZzEUMBIGA1UEAwwLbGFw
dG9wLWFsZG8wHhcNMjMxMDI3MTI1MzUzWhcNMjMxMTI2MTI1MzUzWjBlMQswCQYD
VQQGEwJOTDEQMA4GA1UECAwHTGltYnVyZzEOMAwGA1UEBwwFV2VlcnQxDjAMBgNV
BAoMBU15T3JnMQ4wDAYDVQQLDAVNeU9yZzEUMBIGA1UEAwwLbGFwdG9wLWFsZG8w
ggEiMA0GCSqGSIb3DQEBAQUAA4IBDwAwggEKAoIBAQDRZfoCo2fKYgdz4z99qmnF
rmQv1hQpoAyzrX69vLoYvmcrIYC3qenbyQv5oLoj8KlgAko4lB/HdxmOh3o3+qCh
5hYZj9Fe6NzAfoyopc2ZLTLRfWYRXO4MFiaEeK7k79eVuig4IzuwVJSwjXtitfLT
ZQvd2j25XcPX0wW/Dh5LKaQmEu3mr1pP1bwBzPlN329y9rrAgF+Zyudr/fBwYxvD
0UHYkolsfU3harmcESiSgXby82Hrh72WMUsU/S86ZsVxFypW6ND8/KWOqHOE6fgR
ujwijmInVbTzEHl5bx1tJ/9BynBsjcIar+3H24M4n2N2J2kn8MQtBeeN67kuxI7x
AgMBAAEwDQYJKoZIhvcNAQELBQADggEBAAbDzvz5sNhH93tSC1TYFWSKkXErmYH3
PM4fYvRbApz5brdA4dN93zdNC1qUBa3PYBY/VnJR/G0Aast/YhIgMYsB1Nrv874M
gWZEEiGA43AjJIyyTNDBjvb88O/1lVv2SldtU57cQlkbgqXVQmHf8SWi3MKJAndm
9fMqGPwMgAFR1+xBrP9akmQ0uRrHCmff/YWhm0JlFvSW4Ycs6GTYG1amMoXrZe+T
TKnQzn0h6hEqpmiSHBcEnosvwpqSsXvYiVESIBuDw/dhamejNLXDXCfrh9Ud/oaf
P+isNhJyt5sHDrxsiFtKjlh6bdABmh0Ws1ShhIjrZ6t0YGLjSniQvN4=
-----END CERTIFICATE-----
```

### Tips

Check a certificate with

```bash
openssl x509 -in <certificate> -text -noout
```

Verify the passphrase of a key with

```bash
openssl rsa -noout -in <key> -passin pass:<passphrase>
```
