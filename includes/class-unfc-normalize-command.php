<?php

/**
 * Normalizes a database to Normalization Form C.
 *
 * ## EXAMPLES
 *
 *     # Scan the database, listing non-normalized items.
 *     $ wp unfc-normalize scan-db
 *
 *     # Normalize the database.
 *     $ wp unfc-normalize db
 *
 *     # Scan for slugs percent-encoded from non-normalized data, listing non-normalized items.
 *     $ wp unfc-normalize scan-slugs
 *
 *     # Normalize the slugs.
 *     $ wp unfc-normalize slugs
 *
 */
class UNFC_Normalize_Command extends WP_CLI_Command {

	/**
	 * Scans a database for data not in Normalization Form C.
	 *
	 * Scans the database for non-normalized data and lists those not in Normalization Form C.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - count
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Scan the database, listing non-normalized items.
	 *     $ wp unfc-normalize scan-db
	 *     +--------+-----------------------------+---------+----------+---------------------------+
	 *     | ID     | Title                       | Type    | Subtype  | Field (1st detected only) |
	 *     +--------+-----------------------------+---------+----------+---------------------------+
	 *     | 87225  | Post with non-NFC Content   | post    | post     | post_content              |
	 *     | 114963 | Nëw Post with non-NFC Title | post    | post     | post_title                |
	 *     | 114980 | Post with non-NFC Content   | post    | revision | post_content              |
	 *     | 114981 | Nëw Post with non-NFC Title | post    | revision | post_title                |
	 *     | 39011  | Non-NFC cömment             | comment | comment  | comment_content           |
	 *     | 959    | johnscanner                 | user    | user     | meta                      |
	 *     | 5342   | Non-NFC Cätegory            | term    | category | name                      |
	 *     +--------+-----------------------------+---------+----------+---------------------------+
	 *     Success: 7 non-normalized items detected.
	 *
	 *     # Can use "--quiet" option with "--format=csv" to get just the CSV in the output.
	 *     $ wp unfc-normalize scan-db --format=csv --quiet > scan-db.csv
	 *
	 *     # Can use "--format=count" in a script to get nothing outputted except the bare count.
	 *     $ if [[ $(wp unfc-normalize scan-db --format=count) -ne 0 ]]; then echo 'Database needs backing up and normalizing!'; fi;
	 *    Database needs backing up and normalizing!
	 *
	 * @subcommand scan-db
	 */
	function scan_db( $args, $assoc_args ) {
		global $unfc_normalize;
		self::_check();

		$admin_notices = array();

		$format = \WP_CLI\Utils\get_flag_value( $assoc_args, 'format' );
		$verbose = ( 'count' !== $format );

		add_filter( 'unfc_list_limit', array( __CLASS__, '_unfc_list_limit' ), 10, 2 );

		$ret = $unfc_normalize->db_check_items( $admin_notices );

		remove_filter( 'unfc_list_limit', array( __CLASS__, '_unfc_list_limit' ), 10 );

		self::_format( $format, $ret['items'], array( 'id' => 'ID', 'title' => 'Title', 'type' => 'Type', 'subtype' => 'Subtype', 'field' => 'Field (1st detected only)' ) );

		if ( $verbose ) {
			$message = implode( "\n", self::_admin_notices( $admin_notices ) );

			WP_CLI::success( $message );
		}
	}

	/**
	 * Normalizes a database to Normalization Form C.
	 *
	 * Scans the database for non-normalized data and normalizes to Normalization Form C.
	 *
	 * ## EXAMPLES
	 *
	 *     # Normalize the database.
	 *     $ wp unfc-normalize db
	 *     Important: before updating, please back up your database (https://codex.wordpress.org/WordPress_Backups).
	 *     Are you sure you want to normalize your database? [y/n] y
	 *     Success: 7 items normalized.
	 *
	 *     # Note that if someone is editing a post (or page) that has a non-normalized slug, the post won't be updated.
	 *     $wp unfc-normalize db --yes
	 *     Success: 6 items normalized.
	 *     Warning: 1 item not normalized, somebody is editing it.
	 */
	function db( $args, $assoc_args ) {
		global $unfc_normalize;
		self::_check();

		$admin_notices = array();

		$yes = \WP_CLI\Utils\get_flag_value( $assoc_args, 'yes', false );

		if ( ! $yes ) {
			WP_CLI::log( 'Important: before updating, please back up your database (https://codex.wordpress.org/WordPress_Backups).' );
			WP_CLI::confirm( 'Are you sure you want to normalize your database?', $assoc_args );
		}

		$unfc_normalize->db_check_normalize_all( $admin_notices );

		$message = implode( "\n", self::_admin_notices( $admin_notices ) );

		WP_CLI::success( $message );
	}

	/**
	 * Scans for slugs not in Normalization Form C.
	 *
	 * Scans the database for slugs that could be percent-encoded from non-normalized data and lists them.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - count
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Scan for slugs, listing slugs that could be percent-encoded from non-normalized data.
	 *     $ wp unfc-normalize scan-slugs
	 *     +--------+------------------+----------+------------------+------------------+------------------+----------------------+
	 *     | ID     | Title            | Type     | Slug             | Decoded          | If Normalized    | Normalized Decoded   |
	 *     +--------+------------------+----------+------------------+------------------+------------------+----------------------+
	 *     | 114963 | Nëw Post with no | post     | ne%cc%88w-post-w | nëw-post-with-no | n%c3%abw-post-wi | nëw-post-with-non-nf |
	 *     |        | n-NFC Title      |          | ith-non-nfc-titl | n-nfc-title      | th-non-nfc-title | c-title              |
	 *     |        |                  |          | e                |                  |                  |                      |
	 *     | 5342   | Non-NFC Cätegory | category | non-nfc-ca%cc%88 | non-nfc-cätegory | non-nfc-c%c3%a4t | non-nfc-cätegory     |
	 *     |        |                  |          | tegory           |                  | egory            |                      |
	 *     +--------+------------------+----------+------------------+------------------+------------------+----------------------+
	 *     Success: 2 non-normalized percent-encoded slugs detected.
	 *
	 *     # Can use "--quiet" option with "--format=csv" to get just the CSV in the output.
	 *     $ wp unfc-normalize scan-slugs --format=csv --quiet > scan-slugs.csv
	 *
	 *     # Can use "--format=count" in a script to get nothing outputted except the bare count.
	 *     $ if [[ $(wp unfc-normalize scan-slugs --format=count) -ne 0 ]]; then echo 'Database needs backing up and normalizing!'; fi;
	 *    Database needs backing up and normalizing!
	 *
	 * @subcommand scan-slugs
	 */
	function scan_slugs( $args, $assoc_args ) {
		global $unfc_normalize;
		self::_check();

		$admin_notices = array();

		$format = \WP_CLI\Utils\get_flag_value( $assoc_args, 'format' );
		$verbose = ( 'count' !== $format );

		add_filter( 'unfc_list_limit', array( __CLASS__, '_unfc_list_limit' ), 10, 2 );

		$ret = $unfc_normalize->db_check_slugs( $admin_notices );

		remove_filter( 'unfc_list_limit', array( __CLASS__, '_unfc_list_limit' ), 10 );

		if ( $verbose ) { // Don't bother doing following unless outputting detail.
			foreach ( $ret['slugs'] as &$ritem ) {
				$ritem['decoded'] = rawurldecode( $ritem['slug'] );
				if ( ! ( $unfc_normalize->no_normalizer ? unfc_normalizer_is_normalized( $ritem['decoded'] ) : normalizer_is_normalized( $ritem['decoded'] ) ) ) {
					$normalized = $unfc_normalize->no_normalizer ? unfc_normalizer_normalize( $ritem['decoded'] ) : normalizer_normalize( $ritem['decoded'] );
					if ( false === $normalized ) {
						$ritem['normalized_decoded'] = $ritem['normalized'] = 'Not normalizable!';
					} else {
						$ritem['normalized'] = UNFC_Normalize::percent_encode( $normalized );
						$ritem['normalized_decoded'] = rawurldecode( $ritem['normalized'] );
					}
				} else {
					$ritem['normalized_decoded'] = $ritem['normalized'] = 'No difference!';
				}
			}
		}
		self::_format( $format, $ret['slugs'], array(
			'id' => 'ID', 'title' => 'Title', 'subtype' => 'Type', 'slug' => 'Slug', 'decoded' => 'Decoded', 'normalized' => 'If Normalized', 'normalized_decoded' => 'Normalized Decoded'
		) );

		if ( $verbose ) {
			$message = implode( "\n", self::_admin_notices( $admin_notices ) );

			WP_CLI::success( $message );
		}
	}

	/**
	 * Normalizes the slugs to Normalization Form C.
	 *
	 * Scans the database for slugs that could be percent-encoded from non-normalized data and re-encodes them in Normalization Form C.
	 * If the slugs are for posts (or pages) then the old slug will be added to the post's meta "_wp_old_slug" for redirection.
	 *
	 * ## EXAMPLES
	 *
	 *     # Normalize the slugs.
	 *     $ wp unfc-normalize slugs
	 *     Important: before updating, please back up your database (https://codex.wordpress.org/WordPress_Backups).
	 *     Are you sure you want to normalize the slugs? [y/n] y
	 *     Success: 2 slugs normalized.
	 *
	 *     # Note that if someone is editing a post (or page) that has a non-normalized slug, the post won't be updated.
	 *     $wp unfc-normalize slugs --yes
	 *     Success: 1 slug normalized.
	 *     Warning: 1 slug not normalized, somebody is editing it.
	 */
	function slugs( $args, $assoc_args ) {
		global $unfc_normalize;
		self::_check();

		$admin_notices = array();

		$yes = \WP_CLI\Utils\get_flag_value( $assoc_args, 'yes', false );

		if ( ! $yes ) {
			WP_CLI::log( 'Important: before updating, please back up your database (https://codex.wordpress.org/WordPress_Backups).' );
			WP_CLI::confirm( 'Are you sure you want to normalize the slugs?', $assoc_args );
		}

		add_filter( 'unfc_list_limit', array( __CLASS__, '_unfc_list_limit' ), 10, 2 );

		$ret = $unfc_normalize->db_check_slugs( $admin_notices );

		remove_filter( 'unfc_list_limit', array( __CLASS__, '_unfc_list_limit' ), 10 );

		$admin_notices = array();
		// Fake checkeds.
		$checkeds = array();
		foreach ( $ret['slugs'] as $idx => $item ) {
			$checkeds[] = $item['id'] . ':' . $item['type'] . ':' . $idx;
		}
		$unfc_normalize->db_check_num_slugs = $ret['num_slugs'];
		$unfc_normalize->db_check_slugs = $ret['slugs'];
		$unfc_normalize->db_check_normalize_slugs( $checkeds, $admin_notices );

		$message = implode( "\n", self::_admin_notices( $admin_notices ) );

		WP_CLI::success( $message );
	}

	/**
	 * Check plugin is available and compatible with system.
	 */
	static function _check() {
		global $unfc_normalize;
		if ( ! $unfc_normalize ) {
			WP_CLI::error( 'The plugin "UNFC Nörmalize" is not available.' );
		}
		if ( ! UNFC_Normalize::compatible_version() ) {
			WP_CLI::error( 'The plugin "UNFC Nörmalize" is not compatible with your system.' );
		}
		if ( ! UNFC_Normalize::is_blog_utf8() ) {
			WP_CLI::error( 'The plugin "UNFC Nörmalize" can only run on a blog with charset "UTF-8".' );
		}
	}

	/**
	 * Format table, csv and count output. Not using \WP_CLI\Formatter so can set headers.
	 */
	static function _format( $format, $items, $headers_fields ) {
		if ( 'count' === $format ) {
			echo count( $items ); // No newline.
			return;
		}
		if ( ! $items ) {
			return;
		}

		$headers = array_values( $headers_fields );
		$fields = array_keys( $headers_fields );

		if ( 'csv' === $format ) {
			fputcsv( STDOUT, $headers );
			foreach ( $items as $item ) {
				fputcsv( STDOUT, array_values( \WP_CLI\Utils\pick_fields( $item, $fields ) ) );
			}
			return;
		}

		$table = new \cli\Table;
		$table->setHeaders( $headers );
		$table->setAsciiPreColorized( true );
		foreach ( $items as $item ) {
			$table->addRow( array_values( \WP_CLI\Utils\pick_fields( $item, $fields ) ) );
		}
		foreach ( $table->getDisplayLines() as $line ) {
			WP_CLI::line( $line );
		}
	}

	/**
	 * Return array of command line friendly notices.
	 */
	static function _admin_notices( $admin_notices ) {
		$ret = array();
		if ( $admin_notices ) {
			foreach ( $admin_notices as $admin_notice ) {
				list( $type, $notice ) = $admin_notice;
				$notice = strip_tags( $notice );
				if ( 'error' === $type ) {
					$ret[] = 'ERROR: ' . $notice;
				} elseif ( 'warning' === $type ) {
					$ret[] = 'Warning: ' . $notice;
				} else {
					$ret[] = $notice;
				}
			}
		}
		return $ret;
	}

	/**
	 * Unlimited reporting.
	 */
	static function _unfc_list_limit( $list_limit, $sel ) {
		return PHP_INT_MAX;
	}
}

WP_CLI::add_command( 'unfc-normalize', 'UNFC_Normalize_Command' );
