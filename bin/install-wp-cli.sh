#!/usr/bin/env bash

wget https://raw.github.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
chmod +x wp-cli.phar
sudo mv -f wp-cli.phar /usr/local/bin/wp
which wp
wp --info
