#!/usr/bin/env bash

if [ $# -lt 1 ]; then
	echo "usage: $0 <php-version>"
	exit 1
fi

WP_CLI_PHP_VERSION=$1

if [[ $WP_CLI_PHP_VERSION = 5.3* ]]; then
	wget -O wp-cli.phar https://github.com/wp-cli/wp-cli/releases/download/v2.0.1/wp-cli-2.0.1.phar
else
	wget https://raw.github.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
fi
chmod +x wp-cli.phar
sudo mv -f wp-cli.phar /usr/local/bin/wp
which wp
wp --info
