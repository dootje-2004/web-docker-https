# Notes on OpenSSL

> OpenSSL comes with documentation. Use `man openssl` and
`man openssl-<command>` to educate yourself.

## Private server key

It starts with a private key *server.key*:

```bash
openssl genpkey -algorithm rsa -out server.key
```

This produces an unencrypted, PEM-formatted key of 2048 bits,
using 2 primes and an exponent value of 65537.
The file (ASCII only, so `cat`, `more` and `less` all work)
starts with *-----BEGIN PRIVATE KEY-----*.

> Many online examples use the `openssl genrsa` command.
The OpenSSL docs prefer `openssl genpkey`.

To create an encrypted key we need to specify a cipher
(like des3 or aes) and supply a passphrase:

```bash
openssl genpkey -algorithm rsa -aes256 -pass pass:helloworld -out server.key
```

The public key is included in the *server.key* file, so it is essentially a pair.

> We encrypt the key with AES. The common 3DES cipher
[is being withdrawn](https://www.cryptomathic.com/news-events/blog/3des-is-officially-being-retired).

If we omit the passphrase from the command, we get prompted
to enter a passphrase twice.

The file *server.key* (still ASCII-only) now starts with
*-----BEGIN ENCRYPTED PRIVATE KEY-----*.

> You can check if your passphrase is correct with
`openssl rsa -noout -in server.key -passin pass:helloworld`.

## Create a signing request

Have the key signed (i.e. verified) by a Certificate Authority (CA).
For this, create a Certificate Signing Request (CSR).
A CSR takes the public key from our public/private pair and adds some identifying
fields (company, domain, address, e-mail) to it.
This is what the `openssl req` command is for:

```bash
openssl req -new -key server.key -out server.csr
```

This will read *server.key* and create *server.csr*.
It will ask us to repeat the passphrase for *server.key* to ensure
our signing request is legit.
It will also prompt us for identifying information like names and an address.

To provide all inputs on the command line, use

```bash
openssl req -new -key server.key -out server.csr -passin pass:helloworld -subj "/C=NL/ST=Limburg/L=Weert/O=example/OU=example/CN=example.com" -addext "subjectAltName=email:admin@example.com"
```

The CSR file starts with the line *----BEGIN CERTIFICATE REQUEST-----*.

## Be your own Certificate Authority

The CA verifies the CSR's origin and converts it into a certificate (CRT) *server.crt*
using its CA root certificate *CAroot.crt*.
The CA root certificate is self-signed, because the chain of trust has to start somewhere.
To create a root certificate, the CA needs a private key, just like the server above:

```bash
openssl genpkey -algorithm rsa -aes256 -pass pass:iamgroot -out ca-root.key
```

From this CA root key we create a self-signed certificate:

```bash
openssl req -x509 -new -noenc -key ca-root.key -passin pass:iamgroot -days 365 -out ca-root.crt -subj "/C=NL/ST=Limburg/L=Weert/O=CertOrg/OU=CertOrg/CN=CertOrg"
```

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

References:

* <https://www.baeldung.com/openssl-self-signed-cert>
* <https://arminreiter.com/2022/01/create-your-own-certificate-authority-ca-using-openssl/>

## Sign the request

The last step is to validate the server CSR with the CA root certificate:

```bash
openssl x509 -in server.csr -req -CA ca-root.crt -CAkey ca-root.key -passin pass:iamgroot -out server.crt
```

## Deployment on Apache

The server holds both its public certificate *server.crt* and its private *server.key*.
In Apache, these are referred to as *SSLCertificateFile* and *SSLCertificateKeyFile*,
respectively.
Define a directory `/etc/apache2/ssl` to hold them.
This directory will be created by the `COPY` command in *Dockerfile.apache*.

> It is a good idea to start the Apache container interactively by
  omitting the `--detach` of `-d` option from the `docker-compose up` command.
  This makes troubleshooting easier.
  Stop the container with `Ctrl+C`, followd by `docker-compose down`.

A client receives the server CRT (i.e. the CA-validated public key)
as part of an HTTPS response. They check it with the CA's CRT.
For that, the CA root certificate needs to be available.
The root certificates of commercial CAs are built into the browser.
Our *ca-root.crt* must be imported manually into Firefox or Chrome.

> Key and certificate files sometimes have a *.pem* extension, referring to
  Privacy Enhanced Mail. We think it is more descriptive to use *.key* and
  *.crt*, *.cer* or *.cert*.

## Server name

On start, Docker gives the warning

```tty
php8   | AH00558: apache2: Could not reliably determine the server's fully qualified domain name, using 127.0.0.1. Set the 'ServerName' directive globally to suppress this message
```

This is caused by a mismatch in domain name between the container hostname
set in *docker-compose*, the Apache *ServerName* directive in *demo.conf*
and/or the domain name in the server certificate.
All three must be identical.

If they are not, you may also get a Firefox warning:
*Websites prove their identity via certificates.
Firefox does not trust this site because it uses a certificate that is not
valid for localhost:12443.*
Firefox will also give you a hint if you look at the details of the warning:

```text
https://localhost/

Unable to communicate securely with peer: requested domain name does not match the server’s certificate.
```

## Server key not found

Most likely there is something wrong with the passphrase resolution.

The `SSLPassPhraseDialog` directive is [not allowed in a VirtualHost specification](https://www.apachelounge.com/viewtopic.php?t=7981).
It must live in another configuration file.
We copy this file to `/etc/apache2/conf-available` en enable it with `a2enconf server-ssl`.

> There is no `conf.d` directory like in some older Apache versions.

## Tips

Check a certificate with

```bash
openssl x509 -in <certificate> -text -noout
```

Verify the passphrase of a key with

```bash
openssl rsa -noout -in <key> -passin pass:<passphrase>
```
