[![Build Status](https://travis-ci.org/gitlost/unfc-normalize.png?branch=master)](https://travis-ci.org/gitlost/unfc-normalize)
# UNFC Nörmalize #
**Contributors:** [gitlost](https://profiles.wordpress.org/gitlost), [zodiac1978](https://profiles.wordpress.org/zodiac1978)  
**Tags:** Unicode, Normalization, Form C, Unicode Normalization Form C, Normalize, Normalizer, UTF-8, NFC  
**Requires at least:** 3.9.13  
**Tested up to:** 4.5.3  
**Stable tag:** 0.9.1  
**License:** GPLv2 or later  
**License URI:** http://www.gnu.org/licenses/gpl-2.0.html  

Normalizes UTF-8 input to Normalization Form C.

## Description ##

This is a souped-up version of the [Normalizer plugin](https://wordpress.org/plugins/normalizer/ "Normalizer - WordPress Plugins") by
[Torsten Landsiedel](https://profiles.wordpress.org/zodiac1978/).

It adds WP filters to normalize UTF-8 data coming into the system to the
[de facto web standard Normalization Form C](https://www.w3.org/International/docs/charmod-norm/#choice-of-normalization-form "Choice of Normalization Form").
The Unicode Consortium report is at [Unicode Normalization Forms](http://www.unicode.org/reports/tr15/).

For best performance [install](http://php.net/manual/en/intl.installation.php) (if possible)
the [PHP Internationalization extension `Intl`](http://php.net/manual/en/intro.intl.php),
which includes the PHP class `Normalizer`.

However the plugin works without the PHP Internationalization extension being installed, as it uses (a modified version of)
the [Symfony `Normalizer` polyfill](https://github.com/symfony/polyfill/tree/master/src/Intl/Normalizer).

Text pasted into inputs is normalized immediately using the javascript [`normalize()` method](https://developer.mozilla.org/en/docs/Web/JavaScript/Reference/Global_Objects/String/normalize).
For browsers without normalization support, the [unorm polyfill](https://github.com/walling/unorm) is used.

For further info, see the [PHP:Normalizer::normalize manual page](http://php.net/manual/en/normalizer.normalize.php)
and the WP Trac ticket [#30130 Normalize characters with combining marks to precomposed characters](https://core.trac.wordpress.org/ticket/30130).

For existing data, the plugin includes an administration tool to scan and normalize the database.
**Important:** before using this tool to normalize, please [backup your database](https://codex.wordpress.org/WordPress_Backups).
This is especially important if your database contains non-normalized serialized data, as this plugin uses the same suck-and-see technique as interconnect/it's
[Database Search and Replace Script in PHP](https://interconnectit.com/products/search-and-replace-for-wordpress-databases/) to deal with serialized
data, and is fallible.

A google-cheating schoolboy French translation is supplied.

The plugin should run on PHP 5.2.17 to 7.0.9, and on WP 3.9.13 to 4.5.3.

The project is on [github](https://github.com/gitlost/unfc-normalize).

## Installation ##

1. Upload the zip file from this plugin on your plugins page or search for "UNFC Nörmalize" and install it directly from the repository
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Done!

## Frequently Asked Questions ##

## Screenshots ##

## Changelog ##

### 0.9.1 ###
* Fix bad serialized data corruption on db check.
* Fix text domain tag.

### 0.9.0 ###
* Initial version after renaming from tl-normalize.
