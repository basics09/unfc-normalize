=== Normalizer ===
Contributors: zodiac1978
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=LCH9UVV7RKDFY
Tags: Unicode, Normalization, Form C, Unicode Normalization Form C, Normalize, Normalizer
Requires at least: 1.5.2
Tested up to: 4.3
Stable tag: 1.1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Normalizes content, excerpt, title and comment content to Normalization Form C.

== Description ==

For everyone getting this warning from W3C validator: "Text run is not in Unicode Normalization Form C."

See: http://www.w3.org/International/docs/charmod-norm/#choice-of-normalization-form

**Requires PHP 5.3+**

Be sure to have the PHP-Normalizer-extension (intl and icu) installed.

See: http://php.net/manual/en/normalizer.normalize.php

See also: https://core.trac.wordpress.org/ticket/30130

== Installation ==

1. Upload the zip file from this plugin on your plugins page or search for `Normalizer` and install it directly from the repository
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Done!

== Frequently Asked Questions ==

= I don't see any changes. =

The plugin just adds the normalization if there are problematic characters. Furthermore it does do the normalization before saving, so you don't see anything. It just works if it is needed.

= Will this slow down my site? =

Sorry, but I don't have a clue. Maybe just a little bit. 

== Screenshots ==

1. Correct transliteration if you enter the word directly
2. Missing transliteration for copy/pasted word from PDF
3. Error message from W3C

== Changelog ==

= 1.0.0 =
* Initial release