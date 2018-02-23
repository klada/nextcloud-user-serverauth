# Nextcloud auth backend for webserver-provided auth

This app can make use of the `REMOTE_USER` variable provided by the webserver
to authenticate Nextcloud users. This can be used for Single-Sign-On scenarios,
where the webserver uses Kerberos auth for example.

## Example Apache configuration

You need to set up the webserver to protect the Nextcloud login page:

```
<LocationMatch "^(/index\.php|/index\.php/login)$">
   AuthType Basic
   AuthName "Nextcloud Login"
   AuthUserFile "/var/www/html/.htpasswd"
   Require valid-user
</LocationMatch>
```
