#!/usr/bin/env bash
# https://github.com/vudaltsov/polyfill/blob/intl/.travis.yml

if [ $# -lt 2 ]; then
	echo "usage: $0 <icu-version> <php-version>"
	exit 1
fi

ICU_VERSION=$1
ICU_PHP_VERSION=$2

ICU_DIR=$HOME/.build/icu-$ICU_VERSION
ICU_PHP_DIR=$HOME/.build/php-$ICU_PHP_VERSION-icu-$ICU_VERSION
#if [ ! -f $ICU_PHP_DIR/bin/php ]; then
	wget -O icu-src.tgz http://download.icu-project.org/files/icu4c/$ICU_VERSION/icu4c-$(echo $ICU_VERSION | tr '.' '_')-src.tgz
	mkdir icu-src && tar xzf icu-src.tgz -C icu-src --strip-components=1
	pushd icu-src/source
		./configure --prefix=$ICU_DIR
		make && make install
	popd
	wget -O php-src.tgz http://us1.php.net/get/php-$ICU_PHP_VERSION.tar.gz/from/this/mirror
	mkdir php-src && tar xzf php-src.tgz -C php-src --strip-components=1
	pushd php-src
		./configure --prefix=$ICU_PHP_DIR --enable-intl --with-icu-dir=$ICU_DIR --enable-mbstring --with-mysqli=/usr/bin/mysql_config --with-zlib --with-zlib-dir=/usr
		make && make install
	popd
#fi
