.. _requirements:

Requirements
============

* Web server
* MySQL or MariaDB database
* PHP 8.5 or later
* PHP extensions: ctype, cURL, filter, GD, hash, Intl, JSON, libxml, mbstring,
  OpenSSL, PDO, PDO MySQL, XML and ZIP
* Composer 2 for installations made directly from the Git repository
* HTTPS for PWA installation and Web Push outside localhost

The installer blocks setup when the PHP version or a required extension is
missing. The same platform check is also applied to scheduled status updates.
