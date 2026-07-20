#!/usr/bin/env bash
# Create database
mysql -e "CREATE DATABASE IF NOT EXISTS psm;"
# Create user
mysql -e "CREATE USER IF NOT EXISTS 'psm'@'localhost' IDENTIFIED BY 'psm-dev-password';"
mysql -e "GRANT ALL PRIVILEGES ON psm.* TO 'psm'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"

