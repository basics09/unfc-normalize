=== UNFC Nörmalize ===
Contributors: gitlost, zodiac1978
Tags: Unicode, Normalization, Form C, Unicode Normalization Form C, Normalize, Normalizer, UTF-8, NFC
Requires at least: 3.9.13
Tested up to: 4.7.0
Stable tag: 1.0.5
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Normalizes UTF-8 input to Normalization Form C.

== Description ==

This is a souped-up version of the [Normalizer plugin](https://wordpress.org/plugins/normalizer/ "Normalizer - WordPress Plugins") by
[Torsten Landsiedel](https://profiles.wordpress.org/zodiac1978/).

It adds WP filters to normalize UTF-8 data coming into the system to the
[de facto web standard Normalization Form C](https://www.w3.org/International/docs/charmod-norm/#choice-of-normalization-form "Choice of Normalization Form").
The Unicode Consortium report is at [Unicode Normalization Forms](http://www.unicode.org/reports/tr15/).

= Polyfills =

For best performance [install](http://php.net/manual/en/intl.installation.php) (if possible)
the [PHP Internationalization extension `Intl`](http://php.net/manual/en/intro.intl.php),
which includes the PHP class `Normalizer`.

However the plugin works without the PHP Internationalization extension being installed, as it uses (a modified version of)
the [Symfony `Normalizer` polyfill](https://github.com/symfony/polyfill/tree/master/src/Intl/Normalizer).

Also text pasted into inputs is normalized immediately using the javascript [`normalize()` method](https://developer.mozilla.org/en/docs/Web/JavaScript/Reference/Global_Objects/String/normalize).
For browsers without normalization support, the [unorm polyfill](https://github.com/walling/unorm) is used.

= Background =

For further info, see the WP Trac ticket [#30130 Normalize characters with combining marks to precomposed characters](https://coretrac.wordpress.org/ticket/30130)
and this [Make WP Core comment](https://make.wordpress.org/core/2016/05/17/may-17-feature-projects-chat-and-prompt/#comment-30300).

The plugin also works around the Safari-specific issues raised by WP Trac ticket [#22363 Accents in attachment filenames should be sanitized](https://core.trac.wordpress.org/ticket/22363).

= Scanner =

For existing data, the plugin includes an administration tool to scan and normalize the database.
**Important:** before using this tool to normalize, please [backup your database](https://codex.wordpress.org/WordPress_Backups).
This is especially important if your database contains non-normalized serialized data, as this plugin uses the same suck-and-see technique as interconnect/it's
[Database Search and Replace Script in PHP](https://interconnectit.com/products/search-and-replace-for-wordpress-databases/) to deal with serialized
data, and is fallible.

= Tailends =

A google-cheating schoolboy French translation is supplied.

The plugin should run on PHP 5.2.17 to 7.0.13, and on WP 3.9.13 to 4.7.0.

The project is on [github](https://github.com/gitlost/unfc-normalize).

== Installation ==

Install in the standard way via the 'Plugins' menu in WordPress and then activate. No further action is needed.

== Frequently Asked Questions ==

= How can I normalize extra stuff? =

You can add normalization to anything that passes its content through a filter. The canonical way is to use the `unfc_extra_filters` filter which returns an array of filter names -
for instance, in your theme's `functions.php` file, add:

	function mytheme_unfc_extra_filters( $extra_filters ) {
		$extra_filters[] = 'myfilter';
		return $extra_filters;
	}
	add_filter( 'unfc_extra_filters', 'mytheme_unfc_extra_filters' );

Note that the `unfc_extra_filters` filter is only called in the administration backend. You can also add a filter directly, to be called in the frontend or backend, by referencing the
global PHP variable `unfc_normalize`, but you should ensure that the `Normalizer` polyfill is loaded if you don't have the PHP `Intl` extension installed:

	global $unfc_normalize;
	if ( ! function_exists( 'normalizer_is_normalized' ) ) { // If the "Intl" extension is not installed...
		$unfc_normalize->load_unfc_normalizer_class(); // ...load the polyfill.
	}
	add_filter( 'myfilter', array( $unfc_normalize, 'normalize' ), 6 /* Or whatever priority you choose */ );

== Screenshots ==

1. Before and after: pasting non-normalized text into a post's title and content.
2. Uploading a file on macOS with Firefox and then viewing with Safari without UNFC Nörmalize (broken in Safari).
3. Uploading a file on macOS with Firefox and then viewing with Safari with UNFC Nörmalize enabled (whee it works).
4. The database scan administration tool.

== Changelog ==

= 1.0.5 =
* Do load of normalizer in slugs list constructor not items list. Fixes slugs listing crash when Intl extension not installed.
* Ignore case when checking charset (UNFC_Normalize::is_blog_utf8()).
* WP coding standards (yoda style in unfc_utf8_4range()).
* Add screenshots.
* Some extra unittests.
* WP 4.7.0 compatible.

= 1.0.4 =
* Add _wp_old_slug on normalizing slugs.
* Escape title in screen reader label in db check.

= 1.0.3 =
* For PHP 5 performance do preliminary preg_match on isNormalized. 
* Improve comments in Normalizer.php, tabs -> 4 spaces, UNFC_REGEX_IS_INVALID_UTF8_XXX invert & rename.
* Fix untested admin notice and adjust tests. Fix some test bleed ($wp_scripts).

= 1.0.2 =
* Move all .php files bar main to includes subdir. Remove unused ajax.
* Fix single-byte trie. Move regex alts to tools/functions.php.
* Fix untested admin notice. Adjust tests for untested admin_notice change. Fix some test bleed ($wp_scripts).
* WP 4.6 compatible.

= 1.0.1 =
* First release for wordpress.org repository.
* Add assets.
* Include "class-unfc-list_table.php" in .pot file (for forward/backward compat).
* Remove unused variable in UNFC_DB_Check_Slugs_List_Table.

= 1.0.0 =
* Initial release.

= 0.9.1 =
* Fix bad serialized data corruption on db check.
* Fix text domain tag.

= 0.9.0 =
* Initial version after renaming from tl-normalize.

== Upgrade Notice ==

= 1.0.5 =
Fixes fatal error on check slugs listing in DB check tool when Intl extension not available.

= 1.0.4 =
Now adds _wp_old_slug on normalizing slugs so old links will work.

= 1.0.3 =
Improved PHP 5 performance on isNormalized() check.
