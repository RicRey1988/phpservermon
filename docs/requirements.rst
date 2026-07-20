.. _requirements:

Requirements
============

* Web server
* MySQL or MariaDB database
* PHP 8.5 or later
* PHP extensions: ctype, cURL, filter, hash, JSON, libxml, mbstring,
  OpenSSL, PDO, PDO MySQL and XML
* Composer 2 for installations made directly from the Git repository

The installer blocks setup when the PHP version or a required extension is
missing. The same platform check is also applied to scheduled status updates.
