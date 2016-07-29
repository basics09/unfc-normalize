<?php
/**
 * Main plugin functionality.
 */

define( 'UNFC_DB_CHECK_MENU_SLUG', 'unfc-normalize-db_check' ); // Tools menu slug for database check.
define( 'UNFC_DB_CHECK_PER_PAGE', 'unfc_normalize_db_check_per_page' ); // User option - number of items per page for database check listing.
define( 'UNFC_DB_CHECK_ITEM_BATCH_LIMIT', 4096 ); // Database batch size - number of database requests to do in one go when scanning.
define( 'UNFC_DB_CHECK_NORMALIZE_BATCH_LIMIT', 4096 ); // Database batch size - number of database requests to do in one go when normalizing.
define( 'UNFC_DB_CHECK_LIST_LIMIT', 1000 ); // Initial default number of items to display in listing.
define( 'UNFC_DB_CHECK_TITLE_MAX_LEN', 100 ); // Truncate titles if greater than this length (in UTF-8 chars).
define( 'UNFC_DB_CHECK_ITEMS_LIST_SEL', '#unfc_db_check_items_list' ); // Selector for items listing.
define( 'UNFC_DB_CHECK_SLUGS_LIST_SEL', '#unfc_db_check_slugs_list' ); // Selector for percent-encoded slugs listing.

// Error codes used for standard error messages in db_check_error_msg().
define( 'UNFC_DB_CHECK_DB_ERROR', 1 );
define( 'UNFC_DB_CHECK_META_ERROR', 2 );
define( 'UNFC_DB_CHECK_PARAM_ERROR', 3 );
define( 'UNFC_DB_CHECK_TRANS_ERROR', 4 );
define( 'UNFC_DB_CHECK_SYNC_ERROR', 5 );
define( 'UNFC_DB_CHECK_SELECT_ERROR', 6 );

class UNFC_Normalize {

	// Handy in themselves and to have for testing (can switch on/off). Set in __construct().
	static $dirname = null; // dirname( __FILE__ ).
	static $plugin_basename = null; // plugin_basename( __FILE__ ).
	static $doing_ajax = null; // defined( 'DOING_AJAX' ) && DOING_AJAX.
	static $have_set_group_concat_max_len = false; // Whether have set the MySQL group_concat_max_len variable for the session.

	/*
	 * Filters.
	 */

	// Use a high (ie low number) priority to beat other filters.
	var $priority = 6;

	// Trying to choose the earliest filter available, in 'db' context, so other filters can assume normalized input.
	var $post_filters = array(
		'pre_post_content', 'pre_post_title', 'pre_post_excerpt', /*'pre_post_password',*/ 'pre_post_name', 'pre_post_meta_input', 'pre_post_trackback_url',
	);

	var $comment_filters = array(
		'pre_comment_author_name', 'pre_comment_content', 'pre_comment_author_url', 'pre_comment_author_email',
	);

	var $user_filters = array(
		'pre_user_login', 'pre_user_nicename', 'pre_user_url', 'pre_user_email', 'pre_user_nickname',
		'pre_user_first_name', 'pre_user_last_name', 'pre_user_display_name', 'pre_user_description',
	);

	var $term_filters = array(
		'pre_term_name', 'pre_term_description', 'pre_term_slug',
	);

	// Whether to normalize all options.
	var $do_all_options = true;

	// Or just the WP standard texty ones.
	var $options_filters = array(
		// General.
		'pre_update_option_blogname', 'pre_update_option_blogdescription', 'pre_update_option_admin_email', 'pre_update_option_siteurl', 'pre_update_option_home',
		'pre_update_option_date_format', 'pre_update_option_time_format',
		// Writing. (Non-multisite only.)
		'pre_update_option_mailserver_url', 'pre_update_option_mailserver_url', 'pre_update_option_mailserver_login', /*'pre_update_option_mailserver_pass',*/  'pre_update_option_ping_sites',
		// Nothing texty in Reading.
		// Discussion.
		'pre_update_option_moderation_keys', 'pre_update_option_blacklist_keys',
		// Nothing texty in Media.
		// Permalinks.
		'pre_update_option_permalink_structure', 'pre_update_option_category_base', 'pre_update_option_tag_base',
	);

	var $settings_filters = array( // Network settings (multisite only).
		'pre_update_site_option_blogname', 'pre_update_site_option_blogdescription', 'pre_update_site_option_admin_email', 'pre_update_site_option_siteurl', 'pre_update_site_option_home',
		'pre_update_site_option_site_name', 'pre_update_site_option_new_admin_email', 'pre_update_site_option_illegal_names',
		/*'pre_update_site_option_limited_email_domains',*/ /*'pre_update_site_option_banned_email_domains',*/ // Stripped to ASCII.
		'pre_update_site_option_welcome_email', 'pre_update_site_option_welcome_user_email', 'pre_update_site_option_first_post',
		'pre_update_site_option_first_page', 'pre_update_site_option_first_comment', 'pre_update_site_option_first_comment_author', 'pre_update_site_option_first_comment_url',
	);

	var $menus_filters = array(
		'pre_term_name', 'pre_term_description', 'pre_term_slug', // For the menu.
		'pre_post_content', 'pre_post_title', 'pre_post_excerpt', // For menu items.
	);

	var $widget_filters = array(); // Uses 'widget_update_callback' filter.

	var $permalink_filters = array( 'sanitize_title' );

	var $customize_filters = array(); // None for initial 'customize' preview. For 'customize_save' uses options, settings, menus & widget filters.

	var $link_filters = array(
		'pre_link_url', 'pre_link_name', 'pre_link_image', 'pre_link_description', 'pre_link_notes', 'pre_link_rss',
	);

	/*
	 * Database check tool.
	 */

	var $db_check_hook_suffix = null; // Admin tools menu hook for database check.
	var $db_check_loaded = false; // Whether page loaded.
	var $db_check_cap = 'manage_options'; // Capability needed to access database check.

	var $db_fields = array( // Fields to check.
		'post' => array( 'post_title', 'post_excerpt', 'post_content', 'post_name' ),
		'comment' => array( 'comment_author', 'comment_content', 'comment_author_url', 'comment_author_email' ),
		'user' => array( 'user_nicename', 'user_email', 'user_url', 'display_name' ),
		'term' => array( 'name', 'slug', 'description' ),
		'options' => array( 'option_value' ),
		'settings' => array( 'meta_value' ),
		// 'link' will be set to $db_fields_link in check_db_fields() if link manager enabled.
	);
	var $db_fields_link = array( 'link_url', 'link_name', 'link_image', 'link_description', 'link_notes', 'link_rss' );

	var $db_tables = array( // Map of tables ($wpdb names).
		'post' => 'posts',
		'comment' => 'comments',
		'user' => 'users',
		'term' => 'terms',
		'options' => 'options',
		'settings' => 'sitemeta',
		'link' => 'links',
	);

	var $db_id_cols = array( // Map of table id columns.
		'post' => 'ID',
		'comment' => 'comment_ID',
		'user' => 'ID',
		'term' => 'term_id',
		'options' => 'option_id',
		'settings' => 'meta_id',
		'link' => 'link_id',
	);

	var $db_title_cols = array( // Map of table title columns.
		'post' => 'post_title',
		'comment' => 'comment_content',
		'user' => 'user_login',
		'term' => 'name',
		'options' => 'option_name',
		'settings' => 'meta_key',
		'link' =>  'link_name',
	);

	var $db_meta_tables = array( // Map of meta tables ($wpdb names).
		'post' => 'postmeta',
		'comment' => 'commentmeta',
		'user' => 'usermeta',
		'term' => 'termmeta',
	);

	var $db_meta_id_cols = array( // Map of meta id columns.
		'post' => 'meta_id',
		'comment' => 'meta_id',
		'user' => 'umeta_id',
		'term' => 'meta_id',
	);

	var $db_slug_cols = array( // Map of slug columns (percent-encoded (kinda)).
		'post' => 'post_name',
		'user' => 'user_nicename', // A bit pointless as it's put thru sanitize_user() which strips non-ASCII and percent-encodings but leave for the mo.
		'term' => 'slug',
	);

	var $db_check_num_items = false; // Number of non-normalized items detected.
	var $db_check_items = array(); // Set to list of first get_list_limit() items found.
	var $db_check_num_slugs = false; // Number of non-normalized percent-encoded slugs detected.
	var $db_check_slugs = array(); // Set to list of first get_list_limit() percent-encoded slugs found.

	// General.

	// These are set on 'init' action.
	var $base = ''; // Simplified $pagenow.
	var $added_filters = array(); // Array of whether filters added or not per base.

	// For testing/debugging.
	static $not_compat = false;
	var $dont_js = false, $dont_paste = false, $dont_filter = false, $no_normalizer = false;

	/**
	 * Check system compatibility, add some init-like action.
	 */
	function __construct() {

		if ( null === self::$dirname ) {
			self::$dirname = dirname( __FILE__ );
		}
		if ( null === self::$plugin_basename ) {
			self::$plugin_basename = plugin_basename( __FILE__ );
		}
		if ( null === self::$doing_ajax ) {
			self::$doing_ajax = defined( 'DOING_AJAX' ) && DOING_AJAX;
		}

		add_action( 'admin_init', array( $this, 'admin_init' ) );

		// Don't run anything else in the plugin, if we're on an incompatible system.
		if ( ! self::compatible_version() || ! $this->is_blog_utf8() ) {
			return;
		}

		if ( ! self::$doing_ajax ) {
			add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		}

		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * The primary sanity check, automatically disable the plugin on activation if it doesn't
	 * meet minimum requirements.
	 */
	static function activation_check() {
		if ( ! self::compatible_version() ) {
			deactivate_plugins( self::$plugin_basename );
			wp_die( __( 'The plugin "UNFC Normalize" is not compatible with your system and can\'t be activated.', 'unfc-normalize' ) );
		} else {
			if ( ! self::tested_wp_version() ) {
				$admin_notices_filter = is_network_admin() ? 'network_admin_notices' : ( is_user_admin() ? 'user_admin_notices' : 'admin_notices' );
				add_action( $admin_notices_filter, array( __CLASS__, 'untested_notice' ) );
			}
		}
	}

	/**
	 * Helper to test if using UTF-8.
	 */
	function is_blog_utf8() {
		return in_array( get_option( 'blog_charset' ), array( 'UTF-8', 'utf-8', 'utf8', 'UTF8' ) );
	}

	/**
	 * Called on 'admin_init' action.
	 */
	function admin_init() {
		if ( $this->check_version() && $this->is_blog_utf8() ) {
			if ( self::$doing_ajax ) {
				add_action( 'wp_ajax_unfc_db_check_list_bulk', array( $this, 'wp_ajax_unfc_db_check_list_bulk' ) );
				add_action( 'wp_ajax_unfc_db_check_list_page', array( $this, 'wp_ajax_unfc_db_check_list_page' ) );
				add_action( 'wp_ajax_unfc_db_check_list_sort', array( $this, 'wp_ajax_unfc_db_check_list_sort' ) );
				add_action( 'wp_ajax_unfc_db_check_list_screen_options', array( $this, 'wp_ajax_unfc_db_check_list_screen_options' ) );
			}
		}
	}

	/**
	 * The backup sanity check, in case the plugin is activated in a weird way,
	 * or the versions change after activation.
	 */
	function check_version() {
		if ( ! self::compatible_version() ) {
			if ( is_plugin_active( self::$plugin_basename ) ) {
				deactivate_plugins( self::$plugin_basename );
				$admin_notices_filter = is_network_admin() ? 'network_admin_notices' : ( is_user_admin() ? 'user_admin_notices' : 'admin_notices' );
				add_action( $admin_notices_filter, array( $this, 'disabled_notice' ) );
				if ( isset( $_GET['activate'] ) ) {
					unset( $_GET['activate'] );
				}
			}
			return false;
		}
		return true;
	}

	/**
	 * Called on 'network_admin_notices', 'user_admin_notices' or 'admin_notices' action.
	 */
	function disabled_notice() {
		$error_message  = '<div id="message" class="updated is-dismissible">';
		$error_message .= '<p><strong>' . __( 'Plugin deactivated!', 'unfc-normalize' ) . '</strong> ';
		$error_message .= esc_html__( 'The plugin "UNFC Normalize" is not compatible with your system and has been deactivated.', 'unfc-normalize' );
		$error_message .= '</p></div>';
		echo $error_message;
	}

	/**
	 * Whether compatible with this system.
	 */
	static function compatible_version() {

		// Totally compat! (Famous last words.)
		return ! self::$not_compat; // For testing.
	}

	/**
	 * Called on 'network_admin_notices', 'user_admin_notices' or 'admin_notices' action.
	 */
	static function untested_notice() {
		global $wp_version;
		$untested_wp = ! self::tested_wp_version();
		?>
		<div class="notice notice-warning is-dismissible">
			<p>
				<strong><?php _e( 'Warning: untested!', 'unfc-normalize' ); ?></strong>
			</p>
			<?php if ( $untested_wp ) { ?>
				<p>
					<?php printf(
						/* translators: %1$s: lowest WordPress version tested; %2$s: highest WordPress version tested; %3$s: user's current WordPress version. */
						__( 'The plugin "UNFC Normalize" has only been tested on WordPress Versions %1$s to %2$s. You have WordPress Version %3$s.', 'unfc-normalize' ),
						UNFC_WP_AT_LEAST_VERSION, UNFC_WP_UP_TO_VERSION, $wp_version
					); ?>
				</p>
			<?php } ?>
		</div>
		<?php
	}

	/**
	 * Whether tested with this version of WP
	 */
	static function tested_wp_version() {
		global $wp_version;
		return version_compare( $wp_version, UNFC_WP_AT_LEAST_VERSION, '>=' ) && version_compare( $wp_version, UNFC_WP_UP_TO_VERSION, '<=' );
	}

	/**
	 * Called on 'init' action.
	 */
	function init() {
		unfc_debug_log( "dont_js=", $this->dont_js, ", dont_paste=", $this->dont_paste, ", dont_filter=", $this->dont_filter, ", no_normalizer=", $this->no_normalizer );

		$this->base = '';
		// TODO: Reset $this->added_filters ??

		// Only add filters on admin.
		if ( is_admin() ) {

			$this->base = $this->get_base();

			if ( ! $this->dont_filter ) { // For testing/debugging.
				$this->added_filters = array();

				// Posts.
				if ( 'post' === $this->base ) {
					$this->added_filters['post'] = true;

					foreach( $this->post_filters as $filter ) {
						add_filter( $filter, array( $this, 'normalize' ), $this->priority );
					}

					// Meta data needs its own filter as has its own meta_id => key/value array format.

					// Called on sanitize_post() in wp_insert_post().
					add_filter( 'pre_post_meta', array( $this, 'pre_post_meta' ), $this->priority ); // Seems to be no-op but leave it in for the mo.

					// However, the result of the above is not actually used to update the meta it seems,
					// so add individual filters based on the 'meta' field of $_POST, which is used when updating existing meta data.
					if ( ! empty( $_POST['meta'] ) && is_array( $_POST['meta'] ) ) {
						$this->add_sanitize_metas( $_POST['meta'] );
					}

					// New meta data (add new custom field metabox) uses 'metakeyselect'/'metakeyinput' and 'metavalue' fields of $_POST.
					if ( isset( $_POST['metavalue'] ) && '' !== $_POST['metavalue'] ) {
						$metakey = ! empty( $_POST['metakeyselect'] ) && '#NONE#' !== $_POST['metakeyselect'] ? $_POST['metakeyselect']
										: ( ! empty( $_POST['metakeyinput'] ) ? $_POST['metakeyinput'] : '' );
						if ( '' !== $metakey ) {
							// Put into (no id) => key/value array format.
							$this->add_sanitize_metas( array( array( 'key' => $metakey, 'value' => $_POST['metavalue'] ) ) );
						}
					}

					// For tags (post metabox). Has its own id/term array format.
					add_filter( 'pre_post_tax_input', array( $this, 'pre_post_tax_input' ), $this->priority );

					// For special image alt meta.
					add_filter( 'sanitize_post_meta__wp_attachment_image_alt', array( $this, 'sanitize_meta' ), $this->priority, 3 );
					// For attachment metadata.
					add_filter( 'wp_update_attachment_metadata', array( $this, 'wp_update_attachment_metadata' ), $this->priority, 2 );
				}

				// Comments.
				if ( 'comment' === $this->base || 'post' === $this->base ) {
					$this->added_filters['comment'] = true;

					foreach( $this->comment_filters as $filter ) {
						add_filter( $filter, array( $this, 'normalize' ), $this->priority );
					}

					// No filter for 'comment_meta' on wp_insert/update_comment(), but comment meta seems to be just internal '_wp_XXX' data anyway.
					// add_filter( 'preprocess_comment', array( $this, 'preprocess_comment' ), $this->priority ); // Only used by wp_new_comment().
				}

				// Users.
				if ( 'user' === $this->base ) {
					$this->added_filters['user'] = true;

					foreach( $this->user_filters as $filter ) {
						add_filter( $filter, array( $this, 'normalize' ), $this->priority );
					}

					global $wp_version;
					if ( version_compare( $wp_version, '4.4', '>=' ) ) { // 'insert_user_meta' only available for WP >= 4.4
						// Normalize the user meta. Some are done already by the $user_filters - 'pre_user_nickname' etc.
						// Also, we can (mis-)use the 'insert_user_meta' filter to add sanitize filters for contact methods (using the passed-in $user).
						add_filter( 'insert_user_meta', array( $this, 'insert_user_meta' ), $this->priority, 3 );
					} else {
						// TODO: Anything possible??
					}
				}

				// Categories and tags.
				if ( 'term' === $this->base ) {
					$this->added_filters['term'] = true;

					foreach( $this->term_filters as $filter ) {
						add_filter( $filter, array( $this, 'normalize' ), $this->priority );
					}

					// Term meta data seems to be programmatic only currently.
				}

				// Options.
				if ( 'options' === $this->base || 'customize_save' === $this->base ) {
					$this->added_filters['options'] = true;

					if ( $this->do_all_options ) {
						add_filter( 'pre_update_option', array( $this, 'pre_update_option' ), $this->priority, 3 );
					} else {
						foreach( $this->options_filters as $filter ) {
							add_filter( $filter, array( $this, 'pre_update_option_option' ), $this->priority, 3 );
						}
					}
				}

				// Ajax preview of date/time options.
				if ( 'date_format' === $this->base ) {
					$this->added_filters['date_format'] = true;

					add_filter( 'sanitize_option_date_format', array( $this, 'sanitize_option_option' ), $this->priority, 3 );
				}
				if ( 'time_format' === $this->base ) {
					$this->added_filters['$time_format'] = true;

					add_filter( 'sanitize_option_time_format', array( $this, 'sanitize_option_option' ), $this->priority, 3 );
				}

				// Network settings. (Multisite only.)
				if ( 'settings' === $this->base || 'customize_save' === $this->base ) {
					$this->added_filters['settings'] = true;

					foreach( $this->settings_filters as $filter ) {
						add_filter( $filter, array( $this, 'normalize' ), $this->priority );
					}
				}

				// Menus.
				if ( 'menus' === $this->base || 'customize_save' === $this->base ) {
					$this->added_filters['menus'] = true;

					foreach( $this->menus_filters as $filter ) {
						add_filter( $filter, array( $this, 'normalize' ), $this->priority );
					}

					// sanitize_html_class() strips down to ASCII so not needed for classes (and xfn).
					// add_filter( 'sanitize_html_class', array( $this, 'sanitize_meta' ), $this->priority, 3 );
					// But need post meta filter for menu_item_url.
					add_filter( 'sanitize_post_meta__menu_item_url', array( $this, 'sanitize_meta' ), $this->priority, 3 );
				}

				// Widgets.
				if ( 'widget' === $this->base || 'customize_save' === $this->base ) {
					$this->added_filters['widget'] = true;

					foreach( $this->widget_filters as $filter ) { // No-op.
						add_filter( $filter, array( $this, 'normalize' ), $this->priority ); // @codeCoverageIgnore
					}
					add_filter( 'widget_update_callback', array( $this, 'widget_update_callback' ), $this->priority, 4 );
				}

				// Permalink (ajax).
				if ( 'permalink' === $this->base || 'customize_save' === $this->base ) {
					$this->added_filters['permalink'] = true;

					foreach( $this->permalink_filters as $filter ) {
						add_filter( $filter, array( $this, 'normalize' ), $this->priority );
					}
				}

				// Customizer.
				if ( 'customize' === $this->base ) { // Note this is for the db read-only preview stage. Base will be 'customize_save' on db write.
					// $this->added_filters['customize'] = true; // Nothing at the mo.

					foreach( $this->customize_filters as $filter ) { // No-op.
						add_filter( $filter, array( $this, 'normalize' ), $this->priority ); // @codeCoverageIgnore
					}
				}

				// Links.
				if ( 'link' === $this->base ) {
					 $this->added_filters['link'] = true;

					foreach( $this->link_filters as $filter ) {
						add_filter( $filter, array( $this, 'normalize' ), $this->priority );
					}
				}

				// TODO: other filters??

				// Allow easy add of extra filters. (If directly added then Normalizer polyfill may not load.)
				$extra_filters = array();
				$extra_filters = apply_filters( 'unfc_extra_filters', $extra_filters );
				if ( $extra_filters ) {
					$this->added_filters['extra_filters'] = true;

					foreach( $extra_filters as $filter ) {
						add_filter( $filter, array( $this, 'normalize' ), $this->priority );
					}
				}

				if ( $this->added_filters ) {
					if ( $this->no_normalizer || ! function_exists( 'normalizer_is_normalized' ) ) {

						$this->load_unfc_normalizer_class();
					}
				}
			}
		}

		if ( ! $this->dont_js ) { // For testing/debugging.
			if ( is_admin() ) {
				if ( ! self::$doing_ajax ) {
					add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
				}
			} else {
				add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
			}
		}

		unfc_debug_log( "base=", $this->base, ", added_filters=", $this->added_filters );
	}

	/**
	 * De filter.
	 */
	function normalize( $content ) {

		if ( ! empty( $content ) ) {

			if ( is_string( $content ) ) {

				if ( $this->no_normalizer ) { // For testing when have PHP Normalizer installed.
					if ( ! unfc_normalizer_is_normalized( $content ) ) {
						$normalized = unfc_normalizer_normalize( $content );

						unfc_debug_log( $normalized === $content ? "no_normalizer same"
																: ( "no_normalizer differ\n   content=" . unfc_print_r_hex( $content ) . "\nnormalized=" . unfc_print_r_hex( $normalized ) ) );

						if ( false !== $normalized ) { // Not validating so don't set on error.
							$content = $normalized;
						}
					} else {
						unfc_debug_log( "no_normalizer is_normalized content=" . unfc_print_r_hex( $content ) );
					}
				} else {
					if ( ! normalizer_is_normalized( $content ) ) {
						$normalized = normalizer_normalize( $content );

						unfc_debug_log( $normalized === $content ? "normalizer same"
																: ( "normalizer differ\n   content=" . unfc_print_r_hex( $content ) . "\nnormalized=" . unfc_print_r_hex( $normalized ) ) );

						if ( false !== $normalized ) { // Not validating so don't set on error.
							$content = $normalized;
						}
					} else {
						unfc_debug_log( "normalizer is_normalized content=" . unfc_print_r_hex( $content ) );
					}
				}

			} elseif ( is_array( $content ) ) { // Allow for arrays.
				foreach ( $content as $key => $value ) {
					if ( ! empty( $value ) && ( is_string( $value ) || is_array( $value ) ) ) {
						$content[ $key ] = $this->normalize( $value ); // Recurse.
					}
				}
			}
		}

		return $content;
	}

	/**
	 * Load the UNFC_Normalizer class.
	 */
	function load_unfc_normalizer_class() {

		if ( ! class_exists( 'UNFC_Normalizer' ) ) {
			// Load the (modified version of the) Symfony polyfill https://github.com/symfony/polyfill/tree/master/src/Intl/Normalizer
			require self::$dirname . '/Symfony/Normalizer.php';
		}

		if ( ! function_exists( 'normalizer_is_normalized' ) ) {

			function normalizer_is_normalized( $s, $form = UNFC_Normalizer::NFC ) {
				return UNFC_Normalizer::isNormalized( $s, $form );
			}

			function normalizer_normalize( $s, $form = UNFC_Normalizer::NFC ) {
				return UNFC_Normalizer::normalize( $s, $form );
			}
		}

		if ( $this->no_normalizer ) { // For testing when have PHP Normalizer installed.

			if ( ! function_exists( 'unfc_normalizer_is_normalized' ) ) {

				function unfc_normalizer_is_normalized( $s, $form = UNFC_Normalizer::NFC ) {
					return UNFC_Normalizer::isNormalized( $s, $form );
				}

				function unfc_normalizer_normalize( $s, $form = UNFC_Normalizer::NFC ) {
					return UNFC_Normalizer::normalize( $s, $form );
				}
			}
		}
	}

	/**
	 * Called on 'pre_post_meta' filter.
	 * Filter for post meta data. Which although called seems isn't actually used to update the post meta. Fallback is add_sanitize_meta() below.
	 */
	function pre_post_meta( $arr ) {
		if ( is_array( $arr ) ) {

			// Allow exclusion of keys.
			$exclude_keys = array_flip( apply_filters( 'unfc_exclude_post_meta_keys', array(), $arr, 'pre_post_meta' /*context*/ ) );

			foreach ( $arr as $meta_id => $entry ) {
				if ( isset( $entry['key'] ) && is_string( $entry['key'] ) && ! empty( $entry['value'] ) ) {
					$key = wp_unslash( $entry['key'] ); // NOTE: meta keys WON'T be normalized (not sanitized by WP).
					$value = $entry['value']; // Will be slashed (single/double quote, backslash & nul) but doesn't affect normalization so don't bother unslashing/reslashing.

					if ( '' !== $key && '_' !== $key[0] && ! isset( $exclude_keys[ $key ] ) ) {
						$arr[ $meta_id ] = array( 'key' => $key, 'value' => $this->normalize( $value ) );
					}
				}
			}
		}

		return $arr;
	}

	/**
	 * Add individual filters for metas. Also fallback for above, seeing as it doesn't seem to do anything.
	 * Note passed in raw $_POST array, same meta_id (if available) => key/value format as above.
	 */
	function add_sanitize_metas( $arr ) {

		// Allow exclusion of keys.
		$exclude_keys = array_flip( apply_filters( 'unfc_exclude_post_meta_keys', array(), $arr, 'add_sanitize_metas' /*context*/ ) );

		foreach ( $arr as $entry ) {
			if ( isset( $entry['key'] ) && is_string( $entry['key'] ) && ! empty( $entry['value'] ) ) {
				$key = wp_unslash( $entry['key'] ); // NOTE: meta keys WON'T be normalized (not sanitized by WP).

				if ( '' !== $key && '_' !== $key[0] && ! isset( $exclude_keys[ $key ] ) ) {
					add_filter( "sanitize_post_meta_$key", array( $this, 'sanitize_meta' ), $this->priority, 3 );
				}
			}
		}

		return $arr;
	}

	/**
	 * Called on 'wp_update_attachment_metadata' filter.
	 */
	function wp_update_attachment_metadata( $data, $post_id ) {

		// Allow exclusion of keys.
		$exclude_keys = array_flip( apply_filters( 'unfc_exclude_attachment_meta_keys', array(), $data, $post_id ) );

		foreach ( $data as $key => $value ) {
			if ( ! empty( $value ) && '' !== $key && '_' !== $key[0] && ! isset( $exclude_keys[ $key ] ) ) {
				$data[ $key ] = $this->normalize( $value );
			}
		}

		return $data;
	}

	/**
	 * Called on 'pre_post_tax_input' filter.
	 */
	function pre_post_tax_input( $arr ) {
		if ( is_array( $arr ) ) {
			foreach ( $arr as $taxonomy => $terms ) {
				if ( is_array( $terms ) ) {
					foreach ( $terms as $idx => $term ) {
						if ( ! empty( $term ) && is_string( $term ) && ! ctype_digit( $term ) ) { // Exclude ids.
							$arr[ $taxonomy ][ $idx ] = $this->normalize( $term );
						}
					}
				} else { // For WP < 4.2.
					if ( ! empty( $terms ) && is_string( $terms ) && ! ctype_digit( $terms ) ) { // Exclude ids.
						$arr[ $taxonomy ] = $this->normalize( $terms );
					}
				}
			}
		}

		return $arr;
	}

	/**
	 * Called on 'insert_user_meta' filter.
	 */
	function insert_user_meta( $meta, $user, $update ) {

		// Allow exclusion of keys.
		$exclude_keys = array( 'nickname', 'first_name', 'last_name', 'description' ); // These are already covered by the 'pre_user_XXX' filters.
		$exclude_keys = array_flip( apply_filters( 'unfc_exclude_user_meta_keys', $exclude_keys, $meta, $user, $update ) );

		foreach ( $meta as $key => $value ) {
			if ( ! empty( $value ) && '' !== $key && '_' !== $key[0] && ! isset( $exclude_keys[ $key ] ) ) {
				if ( ( is_string( $value ) && 'false' !== $value && 'true' !== $value ) || is_array( $value ) ) { // Exclude boolean strings.
					$meta[ $key ] = $this->normalize( $value );
				}
			}
		}

		// Use the passed-in $user to get the contact methods and add sanitize filters.
		foreach ( wp_get_user_contact_methods( $user ) as $key => $label /*Have no interest in the $label*/ ) {
			// We don't have access to the newly updated $userdata so can't normalize directly even if we wanted to.
			if ( '' !== $key && '_' !== $key[0] && ! isset( $exclude_keys[ $key ] ) ) {
				add_filter( "sanitize_user_meta_$key", array( $this, 'sanitize_meta' ), $this->priority, 3 );
			}
		}

		return $meta;
	}

	/**
	 * Called on 'pre_update_option' filter.
	 * Called on all options.
	 */
	function pre_update_option( $value, $option, $old_value ) {
		if ( ! empty( $value ) ) {
			// Allow exclusion of options.
			$exclude_options = array_flip( apply_filters( 'unfc_exclude_options', array(), $value, $option, $old_value ) );

			if ( ! isset( $exclude_options[ $option ] ) ) {
				$value = $this->normalize( $value );
			}
		}

		return $value;
	}

	/**
	 * Called on 'pre_update_option_$option' filter.
	 * Called on individual options. Just passthru to pre_update_option().
	 */
	function pre_update_option_option( $value, $old_value, $option = null /*For WP < 4.3 compat*/ ) {
		return $this->pre_update_option( $value, $option, $old_value ); // Note re-ordering of args.
	}

	/**
	 * Called on 'sanitize_option_$option' filter.
	 * For date/time format ajax preview. Just passthru to normalize().
	 */
	function sanitize_option_option( $value, $option, $original_value = null /*For WP < 4.3 compat*/ ) {
		return $this->normalize( $value );
	}

	/**
	 * Called on 'widget_update_callback' filter.
	 */
	function widget_update_callback( $instance, $new_instance, $old_instance, $this_widget ) {

		// Allow exclusion of keys.
		$exclude_keys = array_flip( apply_filters( 'unfc_exclude_widget_instance_keys', array(), $instance, $new_instance, $old_instance, $this_widget ) );

		foreach ( $instance as $key => $value ) {
			if ( ! empty( $value ) && ! isset( $exclude_keys[ $key ] ) ) {
				$instance[ $key ] = $this->normalize( $value );
			}
		}

		return $instance;
	}

	/**
	 * Called on 'sanitize_{$meta_type}_meta_{$meta_key}' filter.
	 * Just a passthru to normalize() for the mo.
	 */
	function sanitize_meta( $meta_value, $meta_key, $meta_type ) {
		return $this->normalize( $meta_value );
	}

	/**
	 * Called on 'admin_enqueue_scripts' and 'wp_enqueue_scripts' actions.
	 */
	function enqueue_scripts() {
		$suffix = defined( "SCRIPT_DEBUG" ) && SCRIPT_DEBUG ? '' : '.min';
		$rangyinputs_suffix = defined( "SCRIPT_DEBUG" ) && SCRIPT_DEBUG ? '-src' : '';

		// Load IE8 Array.prototype.reduceRight polyfill for unorm.
		wp_enqueue_script( 'unfc-ie8', plugins_url( "js/ie8{$suffix}.js", __FILE__ ), array(), UNFC_VERSION );

		global $wp_scripts; // For < 4.2 compat, don't use wp_script_add_data().
		$wp_scripts->add_data( 'unfc-ie8', 'conditional', 'lt IE 9' );

		// Load the javascript normalize polyfill https://github.com/walling/unorm
		wp_enqueue_script( 'unfc-unorm', plugins_url( "unorm/lib/unorm.js", __FILE__ ), array( 'unfc-ie8' ), '1.4.1' ); // Note unorm doesn't come with minified so don't use.

		// Load the getSelection/setSelection jquery plugin https://github.com/timdown/rangyinputs
		wp_enqueue_script( 'unfc-rangyinputs', plugins_url( "rangyinputs/rangyinputs-jquery{$rangyinputs_suffix}.js", __FILE__ ), array( 'jquery' ), '1.2.0' );

		// Our script. Normalizes on paste in tinymce and in admin input/textareas and in some media stuff and in front-end input/textareas.
		wp_enqueue_script( 'unfc-normalize', plugins_url( "js/unfc-normalize{$suffix}.js", __FILE__ ), array( 'jquery', 'unfc-rangyinputs', 'unfc-unorm' ), UNFC_VERSION );

		// Our parameters.
		$params = array(
			'please_wait_msg' => '<div class="notice notice-warning inline"><p>' . __( 'Please wait...', 'unfc-normalize' )
									. '<span class="spinner is-active" style="float:none;margin-top:0;"></span></p></div>',
			'no_items_selected_msg' => '<div class="notice notice-warning is-dismissible inline"><p>' . $this->db_check_error_msg( UNFC_DB_CHECK_SELECT_ERROR ) . '</p></div>',
			'is' => array( // Gets around stringification of direct localize elements.
				'script_debug' => defined( "SCRIPT_DEBUG" ) && SCRIPT_DEBUG,
				'dont_paste' => $this->dont_paste,
				'db_check_loaded' => $this->db_check_loaded,
			),
		);
		$params = apply_filters( 'unfc_params', $params );
		wp_localize_script( 'unfc-normalize', 'unfc_params', $params );

		// Glue.
		add_action( is_admin() ? 'admin_print_footer_scripts' : 'wp_print_footer_scripts', array( $this, 'print_footer_scripts' ) );
	}

	/**
	 * Called on 'admin_print_footer_scripts' and 'wp_print_footer-scripts' actions.
	 */
	function print_footer_scripts() {
		$is_admin = is_admin();
		?>
<script type="text/javascript">
/*jslint ass: true, nomen: true, plusplus: true, regexp: true, vars: true, white: true, indent: 4 */
/*global jQuery, unfc_normalize */

( function ( $ ) {
	'use strict';

	// TinyMCE editor init.
	unfc_normalize.tinymce_editor_init();

	// jQuery ready.
	$( function () {

<?php if ( $is_admin ) : ?>

		unfc_normalize.admin_ready();

<?php else : /*Front end*/ ?>

		unfc_normalize.front_end_ready();

<?php endif; ?>

	} );

<?php if ( $is_admin ) : ?>

	// Customizer - do outside jQuery ready otherwise will miss 'ready' event.
	unfc_normalize.customizer_ready();

<?php endif; ?>

} )( jQuery );
</script>
		<?php
	}

	/**
	 * Standardize what page we're on.
	 */
	function get_base() {
		global $pagenow;
		unfc_debug_log( "pagenow=", $pagenow, ", action=", isset( $_REQUEST['action'] ) ? $_REQUEST['action'] : "(none)" );

		$base = $pagenow;
		$action = isset( $_REQUEST['action'] ) && is_string( $_REQUEST['action'] ) ? stripslashes( $_REQUEST['action'] ) : '';

		if ( '.php' === substr( $base, -4 ) ) {
			$base = substr( $base, 0, -4 );
		}

		if ( 'admin-ajax' === $base && '' !== $action ) {
			$base = $action;
			if ( 'inline-' === substr( $base, 0, 7 ) || 'sample-' === substr( $base, 0, 7 ) || 'update-' === substr( $base, 0, 7 ) ) {
				$base = substr( $base, 7 );
			}
			if ( 'replyto-' === substr( $base, 0, 8 ) ) {
				$base = substr( $base, 8 );
			}
		}

		if ( 'async-upload' === $base && '' !== $action ) {
			$base = $action;
			if ( 'upload-' === substr( $base, 0, 7 ) ) {
				$base = substr( $base, 7 );
			}
		}

		if ( '-add' === substr( $base, -4 ) || '-new' === substr( $base, -4 ) ) {
			$base = substr( $base, 0, -4 );
		}

		if ( '-edit' === substr( $base, -5 ) ) {
			$base = substr( $base, 0, -5 );
		}

		if ( 'add-' === substr( $base, 0, 4 ) || 'nav-' === substr( $base, 0, 4 ) || 'new-' === substr( $base, 0, 4 ) ) {
			$base = substr( $base, 4 );
		}

		if ( 'edit-' === substr( $base, 0, 5 ) || 'save-' === substr( $base, 0, 5 ) ) {
			$base = substr( $base, 5 );
		}

		if ( 'async-upload' === $base || 'attachment' === $base || 'media' === $base || 'meta' === $base || 'save' === $base ) {
			$base = 'post';
		}

		if ( 'profile' === $base ) {
			$base = 'user';
		}

		if ( 'category' === $base || 'tag' == $base || 'tags' === $base || 'tax' === $base ) {
			$base = 'term';
		}

		if ( 'widgets' === $base ) {
			$base = 'widget';
		}

		return $base;
	}

	/**
	 * Called on 'admin_menu' action.
	 */
	function admin_menu() {
		// Add the database check to the tools menu.
		$this->db_check_hook_suffix = add_management_page(
			__( "UNFC No\xcc\x88rmalize Database Check", /*Teehee*/ 'unfc-normalize' ), __( "UNFC No\xcc\x88rm Db Check", 'unfc-normalize' ), $this->db_check_cap, UNFC_DB_CHECK_MENU_SLUG,
			array( $this, 'db_check' )
		);
		if ( $this->db_check_hook_suffix ) {
			add_action( 'load-' . $this->db_check_hook_suffix, array( $this, 'load_db_check' ) );
		}
	}

	/**
	 * Called on 'load-tools_page_UNFC_DB_CHECK_MENU_SLUG' action.
	 */
	function load_db_check() {
		unfc_debug_log( "REQUEST=", $_REQUEST );
		if ( ! current_user_can( $this->db_check_cap ) ) {
			wp_die( __( 'Sorry, you are not allowed to access this page.', 'unfc-normalize' ) );
		}

		$this->db_check_loaded = true;

		$this->db_check_num_items = $this->db_check_num_slugs = false;
		$this->db_check_items = $this->db_check_slugs = array();

		$button = $this->db_check_button(); // Form button or list action.

		if ( 'unfc_db_check_items' === $button ) {
			// Scan for non-normalized data.

			check_admin_referer( UNFC_DB_CHECK_MENU_SLUG . '-items', '_wpnonce_items' );

			$redirect = $this->db_check_base_redirect();
			$admin_notices = array();

			$ret = $this->db_check_items( $admin_notices );

			if ( $ret['num_items'] > 0 ) {
				$transient_key = $button . $_REQUEST['_wpnonce_items'];
				set_transient( $transient_key, $ret, intval( wp_nonce_tick() ) );
				$redirect = add_query_arg( array( 'unfc_trans' => $transient_key ), $redirect );
			}

			$this->db_check_add_admin_notices( $admin_notices );

			wp_redirect( esc_url_raw( $redirect ) );
			if ( defined( 'UNFC_TESTING' ) && UNFC_TESTING ) { // Allow for testing.
				wp_die( $redirect, 'wp_redirect', array_merge( $ret, $admin_notices ) );
			}
			exit;

		} elseif ( 'unfc_db_check_normalize_all' === $button ) {
			// Normalize non-normalized data.

			check_admin_referer( UNFC_DB_CHECK_MENU_SLUG . '-normalize_all', '_wpnonce_normalize_all' );

			$redirect = $this->db_check_base_redirect();
			$admin_notices = array();

			// Delete scan transient if any.
			$transient_key = $this->db_check_transient( 'unfc_db_check_items', false /*dont_get*/, true /*dont_set*/ );
			if ( $transient_key ) {
				delete_transient( $transient_key );
			}

			$this->db_check_normalize_all( $admin_notices );

			$this->db_check_add_admin_notices( $admin_notices );

			wp_redirect( esc_url_raw( $redirect ) );
			if ( defined( 'UNFC_TESTING' ) && UNFC_TESTING ) { // Allow for testing.
				wp_die( $redirect, 'wp_redirect', $admin_notices );
			}
			exit;

		} elseif ( 'unfc_db_check_slugs' === $button ) {
			// Scan for percent-encoded slugs.

			check_admin_referer( UNFC_DB_CHECK_MENU_SLUG . '-slugs', '_wpnonce_slugs' );

			$redirect = $this->db_check_base_redirect();
			$admin_notices = array();

			$ret = $this->db_check_slugs( $admin_notices );

			if ( $ret['num_slugs'] > 0 ) {
				$transient_key = $button . $_REQUEST['_wpnonce_slugs'];
				set_transient( $transient_key, $ret, intval( wp_nonce_tick() ) );
				$redirect = add_query_arg( array( 'unfc_trans' => $transient_key ), $redirect );
			}

			$this->db_check_add_admin_notices( $admin_notices );

			wp_redirect( esc_url_raw( $redirect ) );
			if ( defined( 'UNFC_TESTING' ) && UNFC_TESTING ) { // Allow for testing.
				wp_die( $redirect, 'wp_redirect', array_merge( $ret, $admin_notices ) );
			}
			exit;

		} elseif ( 'unfc_db_check_normalize_slugs' === $button ) {
			// Bulk normalize percent-encoded slugs.

			check_admin_referer( 'bulk-' . UNFC_DB_CHECK_MENU_SLUG, '_wpnonce' );

			$redirect = $this->db_check_base_redirect();
			$ret = $admin_notices = array();

			// Should have percent-encoded transient.
			// If the transient has expired, don't reconstruct as this could take ages but ask for a re-scan.
			$transient_key = $this->db_check_transient( 'unfc_db_check_slugs' );
			if ( ! $transient_key ) {
				$admin_notices[] = array( 'error', $this->db_check_error_msg( UNFC_DB_CHECK_TRANS_ERROR ) );
			} else {
				$redirect = add_query_arg( array( 'unfc_trans' => $transient_key ), $redirect );

				if ( ! isset( $_REQUEST['item'] ) || ! is_array( $_REQUEST['item'] ) ) {
					$admin_notices[] = array( 'warning', $this->db_check_error_msg( UNFC_DB_CHECK_SELECT_ERROR ) ); // Treat no items selected as warning.
				} else  {
					$checkeds = array_map( 'stripslashes', $_REQUEST['item'] );

					$this->db_check_normalize_slugs( $checkeds, $admin_notices );

					$ret['num_slugs'] = $this->db_check_num_slugs;
					$ret['slugs'] = $this->db_check_slugs;

					set_transient( $transient_key, $ret, intval( wp_nonce_tick() ) );
				}
			}

			$this->db_check_add_admin_notices( $admin_notices );

			wp_redirect( esc_url_raw( $redirect ) );
			if ( defined( 'UNFC_TESTING' ) && UNFC_TESTING ) { // Allow for testing.
				wp_die( $redirect, 'wp_redirect', array_merge( $ret, $admin_notices ) );
			}
			exit;

		} elseif ( UNFC_DB_CHECK_PER_PAGE === $button ) {
			// Per-page screen option.

			check_admin_referer( 'screen-options-nonce', 'screenoptionnonce' );

			$redirect = $this->db_check_base_redirect();
			$admin_notices = array();

			// Should have some sort of transient set. (See above re transient transience.)
			$transient_key = $this->db_check_transient( false /*start_with*/, false /*dont_get*/, true /*dont_set*/ );
			if ( ! $transient_key ) {
				$admin_notices[] = array( 'error', $this->db_check_error_msg( UNFC_DB_CHECK_TRANS_ERROR ) );
			} else {
				$redirect = add_query_arg( array( 'unfc_trans' => $transient_key ), $redirect );

				if ( isset( $_REQUEST['wp_screen_options']['value'] ) && is_string( $_REQUEST['wp_screen_options']['value'] ) && ctype_digit( $_REQUEST['wp_screen_options']['value'] ) ) {
					$per_page = intval( $_REQUEST['wp_screen_options']['value'] );
					if ( $per_page > 0 && $per_page < 999 ) {
						$user = wp_get_current_user();
						if ( ! empty( $user->ID ) ) {
							update_user_meta( $user->ID, UNFC_DB_CHECK_PER_PAGE, $per_page );
						}
					}
				}
			}

			$this->db_check_add_admin_notices( $admin_notices );

			wp_redirect( esc_url_raw( $redirect ) );
			if ( defined( 'UNFC_TESTING' ) && UNFC_TESTING ) { // Allow for testing.
				wp_die( $redirect, 'wp_redirect', $admin_notices );
			}
			exit;

		} elseif ( '_wp_http_referer' === $button ) {
			// List form sent with no button or action selected. Redirect to clean url.

			check_admin_referer( 'bulk-' . UNFC_DB_CHECK_MENU_SLUG, '_wpnonce' );

			$redirect = $this->db_check_base_redirect();
			$admin_notices = array();

			// Should have some sort of transient set. (See above re transient transience.)
			$transient_key = $this->db_check_transient( false /*start_with*/, false /*dont_get*/, true /*dont_set*/ );
			if ( ! $transient_key ) {
				$admin_notices[] = array( 'error', $this->db_check_error_msg( UNFC_DB_CHECK_TRANS_ERROR ) );
			} else {
				$redirect = add_query_arg( array( 'unfc_trans' => $transient_key ), $redirect );
			}

			$this->db_check_add_admin_notices( $admin_notices );

			wp_redirect( esc_url_raw( $redirect ) );
			if ( defined( 'UNFC_TESTING' ) && UNFC_TESTING ) { // Allow for testing.
				wp_die( $redirect, 'wp_redirect', $admin_notices );
			}
			exit;

		} else {
			// No button or action or form sent - should be first-time or paging or sorting or filtering.

			// If have valid transient.
			if ( $this->db_check_transient() ) {
				if ( $this->db_check_num_items || $this->db_check_num_slugs ) { // If have listing.
					add_screen_option( 'per_page', array( 'default' => 20, 'option' => UNFC_DB_CHECK_PER_PAGE ) ); // This needs to happen before admin-header is loaded.
				}
			} else {
				// If have invalid transient...
				if ( $this->db_check_transient( false /*start_with*/, false /*dont_get*/, true /*dont_set*/ ) ) {
					$this->db_check_add_admin_notices( array( array( 'error', $this->db_check_error_msg( UNFC_DB_CHECK_TRANS_ERROR ) ) ) );
				}
			}

			$admin_notices_filter = is_network_admin() ? 'network_admin_notices' : ( is_user_admin() ? 'user_admin_notices' : 'admin_notices' );
			add_action( $admin_notices_filter, array( $this, 'admin_notices' ) );
		}
	}

	/**
	 * Callback for database check (Tools menu).
	 */
	function db_check() {
		if ( ! current_user_can( $this->db_check_cap ) ) {
			wp_die( __( 'Sorry, you are not allowed to access this page.', 'unfc-normalize' ) );
		}

		?>

			<div id="unfc_db_check" class="wrap">

				<h1><?php _e( "UNFC No\xcc\x88rmalize Database Check", /*Lol*/ 'unfc-normalize' ); ?></h1>

				<?php $this->db_check_print_page(); ?>

			</div>

		<?php
	}

	/**
	 * Output the page.
	 */
	function db_check_print_page() {
		unfc_debug_log( "this->db_check_num_items={$this->db_check_num_items}, this->db_check_num_slugs={$this->db_check_num_slugs}" );

		if ( false !== $this->db_check_num_items ) {
			// After scanning the database.
			if ( $this->db_check_num_items ) {
				$this->db_check_print_normalize_form();
				$this->db_check_print_items_list();
				?>
					<hr class="unfc_db_check_form_hide">
				<?php
			}
			$this->db_check_print_item_form( true /*rescan*/ );
			?>
				<hr class="unfc_db_check_form_hide">
			<?php
			$this->db_check_print_slug_form();

		} elseif ( false !== $this->db_check_num_slugs ) {
			// After scanning for slugs, or after bulk-normalizing some slugs.
			if ( $this->db_check_num_slugs ) {
				$this->db_check_print_slugs_list();
				?>
					<hr class="unfc_db_check_form_hide">
				<?php
			}
			$this->db_check_print_slug_form( true /*rescan*/ );
			?>
				<hr class="unfc_db_check_form_hide">
			<?php
			$this->db_check_print_item_form();

		} else {
			// Front page.
			$this->db_check_print_item_form();
			?>
				<hr class="unfc_db_check_form_hide">
			<?php
			$this->db_check_print_slug_form();
		}
	}

	/**
	 * Output the scan (or rescan) form.
	 */
	function db_check_print_item_form( $rescan = false ) {
		if ( $rescan ) {
			$value = __( 'Rescan the Database', 'unfc-normalize' );
		} else {
			$value = __( 'Scan the Database', 'unfc-normalize' );
		}
		?>
			<form class="unfc_db_check_form" method="GET">
				<input type="hidden" name="page" value="<?php echo UNFC_DB_CHECK_MENU_SLUG; ?>">
				<?php wp_nonce_field( UNFC_DB_CHECK_MENU_SLUG . '-items', '_wpnonce_items' ) ?>
				<?php if ( ! $rescan ) { ?>
					<p class="unfc_db_check_form_hide">
						<?php _e( 'You can scan the database for non-normalized data (nothing will be updated):', 'unfc-normalize' ); ?>
					</p>
				<?php } ?>
				<input id="unfc_db_check_items" class="button" name="unfc_db_check_items" value="<?php echo esc_attr( $value ); ?>" type="submit">
				<p>
					<?php _e( 'Scanning the database can take a long time depending on the amount and type of data you have.', 'unfc-normalize' ); ?>
				</p>
			</form>
		<?php
	}

	/**
	 * Output the normalize form.
	 */
	function db_check_print_normalize_form() {
		$transient_key = $this->db_check_transient( 'unfc_db_check_items', true /*dont_get*/, true /*dont_set*/ );
		?>
			<form class="unfc_db_check_form" method="GET">
				<input type="hidden" name="page" value="<?php echo UNFC_DB_CHECK_MENU_SLUG; ?>">
				<input type="hidden" name="unfc_trans" value="<?php echo esc_attr( $transient_key ); ?>">
				<?php wp_nonce_field( UNFC_DB_CHECK_MENU_SLUG . '-normalize_all', '_wpnonce_normalize_all' ) ?>
				<p class="unfc_db_check_form_hide">
					<?php _e( 'You can normalize the non-normalized data found in the database. The database <strong>will be updated</strong>.', 'unfc-normalize' ); ?>
				</p>
				<p class="unfc_db_check_form_hide">
					<?php _e( '<strong>Important:</strong> before updating, please <a href="https://codex.wordpress.org/WordPress_Backups">back up your database</a>.', 'unfc-normalize' ); ?>
				</p>
				<input id="unfc_db_check_normalize_all" class="button" name="unfc_db_check_normalize_all" value="<?php echo esc_attr( __( 'Normalize All', 'unfc-normalize' ) ); ?>" type="submit">
				<p>
					<?php _e( 'Normalizing can take a long time depending on the amount and type of data you have.', 'unfc-normalize' ); ?>
				</p>
			</form>
		<?php
	}

	/**
	 * Output the list of non-normalized items found.
	 */
	function db_check_print_items_list() {
		if ( ! class_exists( 'UNFC_DB_Check_Items_List_Table' ) ) {
			require self::$dirname . '/class-unfc-db_check-list-table.php';
		}
		if ( $this->db_check_num_items > count( $this->db_check_items ) ) {
			$h2 = sprintf(
				/* translators: %1$s: formatted maximum number of non-normalized items listed; %2$s: formatted total number of non-normalized items found. */
				__( 'First %1$s Non-Normalized Items of %2$s Found', 'unfc-normalize' ),
				number_format_i18n( $this->get_list_limit( UNFC_DB_CHECK_ITEMS_LIST_SEL ) ), number_format_i18n( $this->db_check_num_items )
			);
		} else {
			$h2 = __( 'Non-Normalized Items', 'unfc-normalize' );
		}
		?>
			<div id="<?php echo substr( UNFC_DB_CHECK_ITEMS_LIST_SEL, 1 ); ?>" class="unfc_db_check_form_hide">
				<hr>
				<h2><?php echo $h2; ?></h2>
		<?php
		$list_table = new UNFC_DB_Check_Items_List_Table();
		$list_table->prepare_items();
		$list_table->views();
		?>
				<form class="unfc_db_check_list_form" method="GET">
		<?php
		$list_table->hiddens();
		$list_table->display();
		?>
				</form>
			</div>
		<?php
	}

	/**
	 * Output the slugs form.
	 */
	function db_check_print_slug_form( $rescan = false ) {
		if ( $rescan ) {
			$value = __( 'Rescan Slugs', 'unfc-normalize' );
		} else {
			$value = __( 'Scan Slugs', 'unfc-normalize' );
		}
		?>
			<form class="unfc_db_check_form" method="GET">
				<input type="hidden" name="page" value="<?php echo UNFC_DB_CHECK_MENU_SLUG; ?>">
				<?php wp_nonce_field( UNFC_DB_CHECK_MENU_SLUG . '-slugs', '_wpnonce_slugs' ) ?>
				<?php if ( ! $rescan ) { ?>
					<p class="unfc_db_check_form_hide">
						<?php _e( 'You can scan the database for slugs that could be percent-encoded from non-normalized data (nothing will be updated):', 'unfc-normalize' ); ?>
					</p>
					<p class="unfc_db_check_form_hide">
						<?php _e( 'A list of posts and/or terms with suspect slugs will be displayed.', 'unfc-normalize' ); ?>
					</p>
				<?php } ?>
				<input id="unfc_db_check_slugs" class="button" name="unfc_db_check_slugs" value="<?php echo esc_attr( $value ); ?>" type="submit">
				<p>
					<?php _e( 'Scanning the slugs can take a long time depending on the amount and type of data you have.', 'unfc-normalize' ); ?>
				</p>
			</form>
		<?php
	}

	/**
	 * Output the list of non-normalized slugs found.
	 */
	function db_check_print_slugs_list() {
		if ( ! class_exists( 'UNFC_DB_Check_Slugs_List_Table' ) ) {
			require self::$dirname . '/class-unfc-db_check-list-table.php';
		}
		if ( $this->db_check_num_slugs > count( $this->db_check_slugs ) ) {
			$h2 = sprintf(
				/* translators: %1$s: formatted maximum number of non-normalized slugs listed; %2$s: formatted total number of non-normalized slugs found. */
				__( 'First %1$s Non-Normalized Slugs of %2$s Found', 'unfc-normalize' ),
				number_format_i18n( $this->get_list_limit( UNFC_DB_CHECK_SLUGS_LIST_SEL ) ), number_format_i18n( $this->db_check_num_slugs )
			);
		} else {
			$h2 = __( 'Non-Normalized Slugs', 'unfc-normalize' );
		}
		?>
			<div id="<?php echo substr( UNFC_DB_CHECK_SLUGS_LIST_SEL, 1 ); ?>" class="unfc_db_check_form_hide">
				<hr>
				<h2><?php echo $h2; ?></h2>
		<?php
		$list_table = new UNFC_DB_Check_Slugs_List_Table();
		$list_table->prepare_items();
		$list_table->views();
		// Note: using POST method rather than GET to allow for large selection for bulk action.
		?>
				<form class="unfc_db_check_list_form" method="POST">
		<?php
		$list_table->hiddens();
		$list_table->display();
		?>
				</form>
			</div>
		<?php
	}

	/**
	 * Which button was pressed.
	 */
	function db_check_button() {
		if ( isset( $_REQUEST['unfc_db_check_items'] ) ) {
			return 'unfc_db_check_items';
		}
		if ( isset( $_REQUEST['unfc_db_check_normalize_all'] ) ) {
			return 'unfc_db_check_normalize_all';
		}
		if ( isset( $_REQUEST['unfc_db_check_slugs'] ) ) {
			return 'unfc_db_check_slugs';
		}
		if ( isset( $_REQUEST['action'] ) && 'unfc_db_check_normalize_slugs' === $_REQUEST['action'] ) {
			return 'unfc_db_check_normalize_slugs';
		}
		if ( isset( $_REQUEST['action2'] ) && 'unfc_db_check_normalize_slugs' === $_REQUEST['action2'] ) {
			return 'unfc_db_check_normalize_slugs';
		}
		if ( isset( $_REQUEST['screen-options-apply'] ) && isset( $_REQUEST['wp_screen_options'] ) && is_array( $_REQUEST['wp_screen_options'] ) ) {
			$options = $_REQUEST['wp_screen_options'];
			if ( isset( $options['option'] ) && is_string( $options['option'] ) && UNFC_DB_CHECK_PER_PAGE === $options['option'] ) {
				return UNFC_DB_CHECK_PER_PAGE;
			}
		}
		if ( isset( $_REQUEST['_wp_http_referer'] ) && is_string( $_REQUEST['_wp_http_referer'] ) && '' !== $_REQUEST['_wp_http_referer'] ) {
			return '_wp_http_referer';
		}
		return false;
	}

	/**
	 * Base url for button redirects, with basic checks on the standard query vars (will be fully checked before use).
	 */
	function db_check_base_redirect() {
		$redirect = admin_url( 'tools.php?page=' . UNFC_DB_CHECK_MENU_SLUG );
		$args = array();

		if ( isset( $_REQUEST['unfc_type'] ) && is_string( $_REQUEST['unfc_type'] ) && $this->sanitize_type( $_REQUEST['unfc_type'] ) ) {
			$args['unfc_type'] = $_REQUEST['unfc_type'];
		}
		if ( isset( $_REQUEST['orderby'] ) && is_string( $_REQUEST['orderby'] ) && preg_match( '/^[a-z_]+$/i', $_REQUEST['orderby'] ) ) {
			$redirect = add_query_arg( array( 'orderby' => $_REQUEST['orderby'] ), $redirect );
		}
		if ( isset( $_REQUEST['order'] ) && is_string( $_REQUEST['order'] ) && preg_match( '/^asc|desc$/i', $_REQUEST['order'] ) ) {
			$redirect = add_query_arg( array( 'order' => $_REQUEST['order'] ), $redirect );
		}
		if ( isset( $_REQUEST['paged'] ) && is_string( $_REQUEST['paged'] ) && ctype_digit( $_REQUEST['paged'] ) ) {
			$redirect = add_query_arg( array( 'paged' => $_REQUEST['paged'] ), $redirect );
		}

		if ( $args ) {
			$redirect = add_query_arg( $args, $redirect );
		}

		return $redirect;
	}

	/**
	 * Get the transient holding the item listing info, and set the items or slugs arrays.
	 */
	function db_check_transient( $starts_with = false, $dont_get = false, $dont_set = false ) {
		if ( isset( $_REQUEST['unfc_trans'] ) && is_string( $_REQUEST['unfc_trans'] ) ) {
			$transient_key = $_REQUEST['unfc_trans'];
			if ( $starts_with && 0 !== strpos( $transient_key, $starts_with ) ) {
				return false;
			}
			$is_items = 0 === strpos( $transient_key, 'unfc_db_check_items' );
			$is_slugs = 0 === strpos( $transient_key, 'unfc_db_check_slugs' );
			if ( $dont_get ) {
				return $is_items || $is_slugs ? $transient_key : false;
			}
			$ret = get_transient( $transient_key );
			if ( $ret ) {
				if ( $is_items ) {
					if ( isset( $ret['num_items'] ) && isset( $ret['items'] ) ) {
						if ( ! $dont_set ) {
							$this->db_check_num_items = $ret['num_items'];
							$this->db_check_items = $ret['items'];
						}
						return $transient_key;
					}
					return false;
				} elseif ( $is_slugs ) {
					if ( isset( $ret['num_slugs'] ) && isset( $ret['slugs'] ) ) {
						if ( ! $dont_set ) {
							$this->db_check_num_slugs = $ret['num_slugs'];
							$this->db_check_slugs = $ret['slugs'];
						}
						return $transient_key;
					}
					return false;
				}
			}
		}
		return false;
	}

	/**
	 * Called on 'network_admin_notices', 'user_admin_notices' or 'admin_notices' action.
	 * Output any messages.
	 */
	function admin_notices() {

		$admin_notices = get_transient( 'unfc_admin_notices' );
		if ( false !== $admin_notices ) {
			delete_transient( 'unfc_admin_notices' );
		}
		if ( $admin_notices ) {

			foreach ( $admin_notices as $admin_notice ) {
				list( $type, $notice ) = $admin_notice;
				if ( 'error' === $type ) {
					?>
						<div class="notice error is-dismissible">
							<p><?php echo $notice; ?></p>
						</div>
					<?php
				} elseif ( 'updated' === $type ) {
					?>
						<div class="notice updated is-dismissible">
							<p><?php echo $notice; ?></p>
						</div>
					<?php
				} else {
					?>
						<div class="notice notice-<?php echo $type; ?> is-dismissible">
							<p><?php echo $notice; ?></p>
						</div>
					<?php
				}
			}
		}
	}

	/**
	 * Add any admin notices as transient.
	 */
	function db_check_add_admin_notices( $admin_notices ) {
		if ( $admin_notices ) {
			set_transient( 'unfc_admin_notices', $admin_notices, 5 * MINUTE_IN_SECONDS );
		}
	}

	/**
	 * Return error message.
	 */
	function db_check_error_msg( $error ) {
		$error_msgs = array(
			UNFC_DB_CHECK_DB_ERROR => __( 'Database error.', 'unfc-normalize' ),
			UNFC_DB_CHECK_META_ERROR => __( 'Meta data error.', 'unfc-normalize' ),
			UNFC_DB_CHECK_PARAM_ERROR => __( 'Parameters error.', 'unfc-normalize' ),
			UNFC_DB_CHECK_TRANS_ERROR => __( 'Transient has expired. Please re-scan.', 'unfc-normalize' ),
			UNFC_DB_CHECK_SYNC_ERROR => __( 'Data out of sync!', 'unfc-normalize' ),
			UNFC_DB_CHECK_SELECT_ERROR => __( 'No items selected!', 'unfc-normalize' ),
		);

		return $error_msgs[ $error ];
	}

	/**
	 * Scan the database for non-normalized data.
	 */
	function db_check_items( &$admin_notices ) {
		$ret = array( 'num_items' => 0, 'items' => array() );

		global $wpdb;

		$this->check_db_fields();

		if ( $this->no_normalizer || ! function_exists( 'normalizer_is_normalized' ) ) {
			$this->load_unfc_normalizer_class();
		}

		$time_query = $time_loop = $total_results = $total_gets = 0; // Some stats.

		// Batch query to lessen memory consumption.
		$batch_limit = $this->get_batch_limit( __FUNCTION__ );

		// In-memory list limit.
		$list_limit = $this->get_list_limit( UNFC_DB_CHECK_ITEMS_LIST_SEL );

		$type = $subtype = '';
		if ( ! empty( $_REQUEST['unfc_type'] ) ) {
			list( $type, $subtype ) = $this->parse_type( $_REQUEST['unfc_type'] );
		}
		if ( $type ) {
			$types = array( $type );
		} else {
			$types = array_keys( $this->db_fields );
		}

		foreach ( $types as $type ) {
			$sql = $this->db_check_sql( false /*normalize*/, $type, $subtype );
			if ( ! $sql ) {
				continue;
			}

			$time_query += -microtime( true );
			$results = $wpdb->get_results( $sql . ' LIMIT 0, ' . $batch_limit );
			$time_query += +microtime( true );
			$wpdb->flush(); // Try to keep memory consumption to a min.

			for ( $num_gets = 1; $results; $num_gets++ ) {
				$time_loop += -microtime( true );
				$num_results = count( $results );
				$total_results += $num_results;

				// Check whether each row and its meta data needs normalizing.
				foreach ( $results as $obj ) {
					$have_field = false;

					foreach ( $this->db_fields[ $type ] as $field ) {
						if ( ! empty( $obj->$field ) && ! ( $this->no_normalizer ? unfc_normalizer_is_normalized( $obj->$field ) : normalizer_is_normalized( $obj->$field ) ) ) {
							$have_field = $field;
							break;
						}
					}

					// Meta data retrieved as group concatenated fields.
					if ( ! $have_field && ! empty( $obj->meta_values )
							&& ! ( $this->no_normalizer ? unfc_normalizer_is_normalized( $obj->meta_values ) : normalizer_is_normalized( $obj->meta_values ) ) ) {
						$have_field = 'meta';
					}

					if ( $have_field ) {
						if ( $ret['num_items'] < $list_limit ) {
							$title = $obj->{$this->db_title_cols[ $type ]};
							if ( mb_strlen( $title ) > UNFC_DB_CHECK_TITLE_MAX_LEN ) {
								$title = mb_substr( $title, 0, UNFC_DB_CHECK_TITLE_MAX_LEN, 'UTF-8' ) . __( '...', 'unfc-normalize' );
							}
							$ret['items'][] = array(
								'id' => (int) $obj->id, 'type' => $type, 'idx' => $ret['num_items'],
								'title' => $title, 'subtype' => isset( $obj->subtype ) ? $obj->subtype : $type, 'field' => $have_field,
							);
						}
						$ret['num_items']++;
					}
				}
				$time_loop += +microtime( true );

				if ( $num_results < $batch_limit ) {
					break;
				}
				unset( $results );
				$time_query += -microtime( true );
				$results = $wpdb->get_results( $sql . ' LIMIT ' . ( $num_gets * $batch_limit ) . ', ' . $batch_limit );
				$time_query += +microtime( true );
				$wpdb->flush();
			}
			$total_gets += $num_gets;

			if ( null === $results ) {
				$admin_notices[] = array( 'error', $this->db_check_error_msg( UNFC_DB_CHECK_DB_ERROR ) );
				return $ret;
			}
			unset( $results );
		}

		unfc_debug_log(
			"num_items={$ret['num_items']}, total_results=$total_results, total_gets=$total_gets, time_query=", sprintf( '%.10f', $time_query ), ", time_loop=", sprintf( '%.10f', $time_loop )
		);

		if ( $ret['num_items'] ) {
			$admin_notices[] = array( 'info', sprintf(
				/* translators: %s: formatted number of items detected. */
				_n( '%s non-normalized item detected.', '%s non-normalized items detected.', $ret['num_items'], 'unfc-normalize' ), number_format_i18n( $ret['num_items'] )
			) );
		} else {
			$admin_notices[] = array( 'success', __( '<strong>No</strong> non-normalized data detected!', 'unfc-normalize' ) );
		}

		return $ret;
	}

	/**
	 * Do database normalize on all items.
	 */
	function db_check_normalize_all( &$admin_notices ) {

		$num_updates = $num_locked = $num_fails = 0;

		global $wpdb;

		$this->check_db_fields();

		if ( $this->no_normalizer || ! function_exists( 'normalizer_is_normalized' ) ) {
			$this->load_unfc_normalizer_class();
		}

		$time_query = $time_loop = $total_results = $total_gets = 0; // Some stats.

		// Batch query to lessen memory consumption.
		$batch_limit = $this->get_batch_limit( __FUNCTION__ );

		$type = $subtype = '';
		if ( ! empty( $_REQUEST['unfc_type'] ) ) {
			list( $type, $subtype ) = $this->parse_type( $_REQUEST['unfc_type'] );
		}
		if ( $type ) {
			$types = array( $type );
		} else {
			$types = array_keys( $this->db_fields );
		}

		$current_user_id = get_current_user_id();

		foreach ( $types as $type ) {
			$sql = $this->db_check_sql( true /*normalize*/, $type, $subtype );
			if ( ! $sql ) {
				continue;
			}
			$per_type_updates = 0;

			$time_query += -microtime( true );
			$results = $wpdb->get_results( $sql . ' LIMIT 0, ' . $batch_limit );
			$time_query += +microtime( true );
			$wpdb->flush(); // Try to keep memory consumption to a min.

			for ( $num_gets = 1; $results; $num_gets++ ) {
				$time_loop += -microtime( true );
				$num_results = count( $results );
				$total_results += $num_results;

				// Check whether each row and its meta data needs normalizing.
				foreach ( $results as $obj ) {
					$id = $obj->id;
					$updated = $failed = false;

					// Check for post being locked by another user.
					$locked = 'post' === $type && self::wp_check_post_lock( $id, $current_user_id );
					if ( ! $locked ) {
						$data = array();
						foreach ( $this->db_fields[ $type ] as $field ) {
							if ( ! empty( $obj->$field ) && ! ( $this->no_normalizer ? unfc_normalizer_is_normalized( $obj->$field ) : normalizer_is_normalized( $obj->$field ) ) ) {
								$normalized = $this->recursive_unserialize_normalize( $obj->$field );
								if ( false === $normalized ) { // As taking values from database which should be valid UTF-8, this shouldn't happen.
									$failed = true;
									break;
								} else {
									$data[ $field ] = $normalized;
								}
								unset( $obj->$field, $normalized );
							}
						}

						if ( ! $failed ) {
							// Terms span two tables so need to treat specially.
							if ( 'term' === $type && isset( $data['description'] ) ) {
								$update = $wpdb->update( $wpdb->term_taxonomy, array( 'description' => $data['description'] ), array( 'term_id' => $id, 'taxonomy' => $obj->subtype ) );
								if ( false === $update ) {
									$admin_notices[] = array( 'error', $this->db_check_error_msg( UNFC_DB_CHECK_DB_ERROR ) );
									return false;
								}
								unset( $data['description'] );
								$updated = true;
							}

							if ( $data ) {
								$db_table = $wpdb->{$this->db_tables[ $type ]};
								$db_id_col = $this->db_id_cols[ $type ];
								$update = $wpdb->update( $db_table, $data, array( $db_id_col => $id ) );
								if ( false === $update ) {
									$admin_notices[] = array( 'error', $this->db_check_error_msg( UNFC_DB_CHECK_DB_ERROR ) );
									return false;
								}
								$updated = true;
								unset( $data );
							}

							if ( ! empty( $obj->meta_values ) ) {
								// Meta data retrieved as group concatenated fields.
								$meta_ids = explode( ',', $obj->meta_ids );
								$cnt = count( $meta_ids );
								$meta_values = $this->db_check_meta_split( $obj->meta_values, $obj->meta_value_lens );
								if ( $cnt !== count( $meta_values ) ) {
									$admin_notices[] = array( 'error', $this->db_check_error_msg( UNFC_DB_CHECK_META_ERROR ) );
									return false;
								}
								$db_meta_table = $wpdb->{$this->db_meta_tables[ $type ]};
								$db_meta_id_col = $this->db_meta_id_cols[ $type ];
								foreach ( $meta_values as $idx => $meta_value ) {
									$data = array();
									if ( ! ( $this->no_normalizer ? unfc_normalizer_is_normalized( $meta_value ) : normalizer_is_normalized( $meta_value ) ) ) {
										$normalized = $this->recursive_unserialize_normalize( $meta_value );
										if ( false === $normalized ) {
											$failed = true;
											break;
										} else {
											$data['meta_value'] = $normalized;
										}
										unset( $meta_value, $normalized );
									}
									if ( $data ) {
										$update = $wpdb->update( $db_meta_table, $data, array( $db_meta_id_col => $meta_ids[ $idx ] ) );
										if ( false === $update ) {
											$admin_notices[] = array( 'error', $this->db_check_error_msg( UNFC_DB_CHECK_DB_ERROR ) );
											return false;
										}
										$updated = true;
									}
									unset( $data );
								}
								unset( $obj->meta_values, $meta_values );
							}
						}
					}

					if ( $updated ) {
						$num_updates++;
						$per_type_updates++;
					} elseif ( $locked ) {
						$num_locked++;
					} elseif ( $failed ) {
						$num_fails++;
					}
				}
				$time_loop += +microtime( true );

				if ( $num_results < $batch_limit ) {
					break;
				}
				unset( $results );
				$time_query += -microtime( true );
				$results = $wpdb->get_results( $sql . ' LIMIT ' . ( $num_gets * $batch_limit - $per_type_updates ) . ', ' . $batch_limit );
				$time_query += +microtime( true );
				$wpdb->flush();
			}
			$total_gets += $num_gets;

			if ( null === $results ) {
				$admin_notices[] = array( 'error', $this->db_check_error_msg( UNFC_DB_CHECK_DB_ERROR ) );
				return false;
			}
			unset( $results );
		}

		unfc_debug_log(
			"num_updates=$num_updates, total_results=$total_results, total_gets=$total_gets, time_query=", sprintf( '%.10f', $time_query ), ", time_loop=", sprintf( '%.10f', $time_loop )
		);

		if ( $num_updates ) {
			wp_cache_flush(); // Clear all cache items.
		}

		if ( $num_updates ) {
			/* translators: %s: formatted number of items normalized. */
			$admin_notices[] = array( 'updated', sprintf( _n( '%s item normalized.', '%s items normalized.', $num_updates, 'unfc-normalize' ), number_format_i18n( $num_updates ) ) );
		} else {
			$admin_notices[] = array( 'updated', __( 'Nothing updated!', 'unfc-normalize' ) );
		}
		if ( $num_locked ) {
			/* translators: %s: formatted number of items locked. */
			$admin_notices[] = array( 'warning', sprintf(
				_n( '%s item not normalized, somebody is editing it.', '%s items not normalized, somebody is editing them.', $num_locked, 'unfc-normalize' ), number_format_i18n( $num_locked )
			) );
		}
		if ( $num_fails ) {
			/* translators: %s: formatted number of items that failed to normalize. */
			$admin_notices[] = array( 'warning', sprintf(
				_n( '%s item not normalized, failed to normalize.', '%s items not normalized, failed to normalize.', $num_fails, 'unfc-normalize' ), number_format_i18n( $num_fails )
			) );
		}

		return $num_updates;
	}

	/**
	 * Helper for db_check_normalize_all() to split the joined meta data using their lengths field.
	 */
	function db_check_meta_split( $strs, $lens ) {
		mbstring_binary_safe_encoding();
		$ret = array();
		$lens = array_map( 'intval', explode( ',', $lens ) );
		$offset = 0;
		foreach ( $lens as $len ) {
			$ret[] = substr( $strs, $offset, $len );
			$offset += $len;
		}
		reset_mbstring_encoding();
		return $ret;
	}

	/**
	 * Helper for db_check_items() and db_check_normalize_all() to return SQL to get possible non-normalized data.
	 */
	function db_check_sql( $normalize, $type, $subtype = '' ) {
		global $wpdb;

		if ( ! self::$have_set_group_concat_max_len ) {
			// Set the MySQL GROUP_CONCAT max_len. See http://dev.mysql.com/doc/refman/5.7/en/server-system-variables.html#sysvar_group_concat_max_len
			$wpdb->query( 'SET SESSION group_concat_max_len = CAST(-1 AS UNSIGNED)' );
			self::$have_set_group_concat_max_len = true;
		}

		$sql = '';

		$have_meta = isset( $this->db_meta_id_cols[ $type ] ) && isset( $wpdb->{$this->db_meta_tables[ $type ]} );

		if ( 'post' === $type ) {
			$subtype_sql = $subtype ? $wpdb->prepare( ' AND post_type = %s', $subtype ) : '';
			$sql = "SELECT " . $this->db_check_fields_sql( $normalize, $type, $have_meta ) . " FROM {$wpdb->posts} t"
				. " LEFT JOIN {$wpdb->postmeta} m ON (m.post_id = t.ID AND SUBSTRING(m.meta_key, 1, 1) <> '_' AND m.meta_value <> '')"
				. " WHERE post_status NOT IN ('trash'){$subtype_sql} AND (" . $this->db_check_regexp_sql( $type, $have_meta ) . ") GROUP BY t.ID";

		} elseif ( 'comment' === $type ) {
			$sql = "SELECT " . $this->db_check_fields_sql( $normalize, $type, $have_meta ) . " FROM {$wpdb->comments} t"
				. " LEFT JOIN {$wpdb->commentmeta} m ON (m.comment_id = t.comment_ID AND SUBSTRING(m.meta_key, 1, 1) <> '_' AND m.meta_value <> '')"
				. " WHERE " . $this->db_check_regexp_sql( $type, $have_meta ) . " GROUP BY t.comment_ID";

		} elseif ( 'user' === $type ) {
			$sql = "SELECT " . $this->db_check_fields_sql( $normalize, $type, $have_meta ) . " FROM {$wpdb->users} t"
				. " LEFT JOIN {$wpdb->usermeta} m ON (m.user_id = t.ID AND SUBSTRING(m.meta_key, 1, 1) <> '_' AND m.meta_value <> '')"
				. " WHERE " . $this->db_check_regexp_sql( $type, $have_meta ) . " GROUP BY t.ID";

		} elseif ( 'term' === $type ) {
			$subtype_sql = $subtype ? $wpdb->prepare( ' AND tt.taxonomy = %s', $subtype ) : '';
			$sql = "SELECT " . $this->db_check_fields_sql( $normalize, $type, $have_meta ) . " FROM {$wpdb->terms} t"
				. " JOIN {$wpdb->term_taxonomy} tt ON (tt.term_id = t.term_id{$subtype_sql})";
			if ( $have_meta ) {
				$sql .= " LEFT JOIN {$wpdb->termmeta} m ON (m.term_id = t.term_id AND SUBSTRING(m.meta_key, 1, 1) <> '_' AND m.meta_value <> '')"
					. " WHERE " . $this->db_check_regexp_sql( $type, $have_meta ) . " GROUP BY t.term_id, tt.taxonomy";
			} else {
				$sql .= " WHERE " . $this->db_check_regexp_sql( $type, $have_meta );
			}

		} elseif ( 'options' === $type ) {
			$sql = "SELECT " . $this->db_check_fields_sql( $normalize, $type, $have_meta ) . " FROM {$wpdb->options} t"
				. " WHERE SUBSTRING(t.option_name, 1, 1) <> '_' AND (" . $this->db_check_regexp_sql( $type ) . ")";

		} elseif ( 'link' === $type ) {
			$sql = "SELECT " . $this->db_check_fields_sql( $normalize, $type ) . " FROM {$wpdb->links} t"
				. " WHERE " . $this->db_check_regexp_sql( $type );

		} elseif ( is_multisite() ) {
			if ( 'settings' === $type ) {
				$sql = "SELECT " . $this->db_check_fields_sql( $normalize, $type ) . " FROM {$wpdb->sitemeta} t"
					. " WHERE t.site_id = {$wpdb->siteid} AND SUBSTRING(t.meta_key, 1, 1) <> '_' AND t.meta_value <> '' AND (" . $this->db_check_regexp_sql( $type ) . ")";
			}
		}

		return $sql;
	}

	/**
	 * Helper for db_check_sql() to return the type fields.
	 */
	function db_check_fields_sql( $normalize, $type, $have_meta = false ) {
		$ret = array();

		$ret[] = 't.' . $this->db_id_cols[ $type ] . ' AS id';
		if ( $normalize ) {
			if ( 'term' === $type ) {
				$ret[] = 'tt.taxonomy AS subtype';
			}
		} else {
			if ( 'post' === $type ) {
				$ret[] = 't.post_type AS subtype';
			} elseif ( 'user' === $type ) {
				$ret[] = 'user_login'; // Used as title in listing and not checked in fields.
			} elseif ( 'term' === $type ) {
				$ret[] = 'tt.taxonomy AS subtype';
			} elseif ( 'options' === $type ) {
				$ret[] = 'option_name'; // Used as title in listing and not checked in fields.
			} elseif ( 'settings' === $type ) {
				$ret[] = 'meta_key'; // Used as title in listing and not checked in fields.
			}
		}

		$fields = $this->db_fields[ $type ];
		for ( $i = 0, $cnt = count( $fields ); $i < $cnt; $i++ ) {
			$ret[] = $fields[ $i ];
		}
		if ( $have_meta ) {
			if ( $normalize ) {
				$ret[] = "GROUP_CONCAT(m.{$this->db_meta_id_cols[ $type ]}) AS meta_ids"; // Meta ids separated by commas.
			}
			$ret[] = "GROUP_CONCAT(m.meta_value SEPARATOR '') AS meta_values";
			if ( $normalize ) {
				// Based on http://stackoverflow.com/a/452621/664741 - adds lengths field.
				$ret[] = "GROUP_CONCAT(LENGTH(m.meta_value)) AS meta_value_lens";
			}
		}

		return implode( ', ', $ret );
	}

	/**
	 * Helper for db_check_sql() to return the regular expression sql.
	 */
	function db_check_regexp_sql( $type, $have_meta = false ) {

		// MySQL's regex doesn't understand hex (or octal or sexagesimal) escape sequences and naked chr codes seem to get molested on their way to the server,
		// so need to use the hex (or dec as more compact) codes as CHAR()s. Also it's slow unless expressions are simple.
		// The following is '[\xcc-\xf4]', which takes advantage of knowing we have valid UTF-8 in the database to search for characters >= U+0300.
		static $regexp = "CONCAT('[',CHAR(204),'-',CHAR(244),']')";

		$ret = array();
		foreach ( $this->db_fields[ $type ] as $field ) {
			$ret[] = "$field RLIKE $regexp";
		}
		if ( $have_meta ) {
			$ret[] = "m.meta_value RLIKE $regexp";
		}

		return implode( ' OR ', $ret );
	}

	/**
	 * Scan the database for non-normalized percent-encoded slugs.
	 */
	function db_check_slugs( &$admin_notices ) {
		$ret = array( 'num_slugs' => 0, 'slugs' => array() );

		global $wpdb;

		$this->check_db_fields();

		if ( $this->no_normalizer || ! function_exists( 'normalizer_is_normalized' ) ) {
			$this->load_unfc_normalizer_class();
		}

		$time_query = $time_loop = $total_results = $total_gets = 0; // Some stats.

		// Batch query to lessen memory consumption.
		$batch_limit = $this->get_batch_limit( __FUNCTION__ );

		// In-memory list limit.
		$list_limit = $this->get_list_limit( UNFC_DB_CHECK_SLUGS_LIST_SEL );

		$type = $subtype = '';
		if ( ! empty( $_REQUEST['unfc_type'] ) ) {
			list( $type, $subtype ) = $this->parse_type( $_REQUEST['unfc_type'] );
		}
		if ( $type ) {
			$types = isset( $this->db_slug_cols[ $type ] ) ? array( $type ) : array();
		} else {
			$types = array_keys( $this->db_slug_cols );
		}

		foreach ( $types as $type ) {
			$sql = $this->db_check_slug_sql( $type, $subtype );
			if ( ! $sql ) {
				continue;
			}

			$time_query += -microtime( true );
			$results = $wpdb->get_results( $sql . ' LIMIT 0, ' . $batch_limit );
			$time_query += +microtime( true );
			$wpdb->flush(); // Try to keep memory consumption to a min.

			for ( $num_gets = 1; $results; $num_gets++ ) {
				$time_loop += -microtime( true );
				$num_results = count( $results );
				$total_results += $num_results;

				// Check whether each slug needs normalizing.
				foreach ( $results as $obj ) {

					if ( ! empty( $obj->slug ) ) {
						$decoded = self::percent_decode( $obj->slug ); // Note slugs aren't properly percent-encoded by sanitize_title_with_dashes() so not using rawurldecode().
						if ( ! ( $this->no_normalizer ? unfc_normalizer_is_normalized( $decoded ) : normalizer_is_normalized( $decoded ) ) ) {
							if ( $ret['num_slugs'] < $list_limit ) {
								$title = $obj->title;
								if ( mb_strlen( $title ) > UNFC_DB_CHECK_TITLE_MAX_LEN ) {
									$title = mb_substr( $title, 0, UNFC_DB_CHECK_TITLE_MAX_LEN, 'UTF-8' ) . __( '...', 'unfc-normalize' );
								}
								$ret['slugs'][] = array(
									'id' => (int) $obj->id, 'type' => $type, 'idx' => $ret['num_slugs'],
									'title' => $title, 'subtype' => $obj->subtype, 'slug' => $obj->slug,
								);
							}
							$ret['num_slugs']++;
						}
					}
				}
				$time_loop += +microtime( true );

				if ( $num_results < $batch_limit ) {
					break;
				}
				unset( $results );
				$time_query += -microtime( true );
				$results = $wpdb->get_results( $sql . ' LIMIT ' . ( $num_gets * $batch_limit ) . ', ' . $batch_limit );
				$time_query += +microtime( true );
				$wpdb->flush();
			}
			$total_gets += $num_gets;

			if ( null === $results ) {
				$admin_notices[] = array( 'error', $this->db_check_error_msg( UNFC_DB_CHECK_DB_ERROR ) );
				return $ret;
			}
			unset( $results );
		}

		if ( $ret['num_slugs'] ) {
			$admin_notices[] = array( 'info', sprintf(
				/* translators: %s: formatted number of percent-encoded items detected. */
				_n( '%s non-normalized percent-encoded slug detected.', '%s non-normalized percent-encoded slugs detected.', $ret['num_slugs'], 'unfc-normalize' ),
				number_format_i18n( $ret['num_slugs'] )
			) );
		} else {
			$admin_notices[] = array( 'success', __( '<strong>No</strong> non-normalized percent-encoded slugs detected!', 'unfc-normalize' ) );
		}

		unfc_debug_log(
			"num_slugs={$ret['num_slugs']}, total_results=$total_results, total_gets=$total_gets, time_query=", sprintf( '%.10f', $time_query ), ", time_loop=", sprintf( '%.10f', $time_loop )
		);

		return $ret;
	}

	/**
	 * Helper for db_check_slugs() to return SQL to get possible non-normalized percent-encoded slugs.
	 */
	function db_check_slug_sql( $type, $subtype = '' ) {
		global $wpdb;

		$sql = '';

		if ( 'post' === $type ) {
			$subtype_sql = $subtype ? $wpdb->prepare( ' AND post_type = %s', $subtype ) : '';
			$sql = "SELECT " . $this->db_check_slug_fields_sql( $type ) . " FROM {$wpdb->posts} t"
				. " WHERE post_status NOT IN ('trash'){$subtype_sql} AND (" . $this->db_check_slug_regexp_sql( $type ) . ")";

		} elseif ( 'user' === $type ) {
			$sql = "SELECT " . $this->db_check_slug_fields_sql( $type ) . " FROM {$wpdb->users} t"
				. " WHERE " . $this->db_check_slug_regexp_sql( $type );

		} elseif ( 'term' === $type ) {
			$subtype_sql = $subtype ? $wpdb->prepare( ' AND tt.taxonomy = %s', $subtype ) : '';
			$sql = "SELECT " . $this->db_check_slug_fields_sql( $type ) . " FROM {$wpdb->terms} t"
				. " JOIN {$wpdb->term_taxonomy} tt ON (tt.term_id = t.term_id{$subtype_sql})"
				. " WHERE " . $this->db_check_slug_regexp_sql( $type );
		}

		return $sql;
	}

	/**
	 * Helper for db_check_slug_sql() to return the type fields.
	 */
	function db_check_slug_fields_sql( $type ) {
		$ret = array();

		if ( isset( $this->db_slug_cols[ $type ] ) ) {
			$ret[] = 't.' . $this->db_id_cols[ $type ] . ' AS id';
			$ret[] = 't.' . $this->db_slug_cols[ $type ] . ' AS slug';
			$ret[] = "'$type' AS type";
			if ( 'post' === $type ) {
				$ret[] = 't.post_title AS title';
				$ret[] = 't.post_type AS subtype';
			} elseif ( 'user' === $type ) {
				$ret[] = 't.user_login AS title';
				$ret[] = "'$type' AS subtype";
			} elseif ( 'term' === $type ) {
				$ret[] = 't.name AS title';
				$ret[] = 'tt.taxonomy AS subtype';
			}
		}

		return implode( ', ', $ret );
	}

	/**
	 * Helper for db_check_slug_sql() to return the percent-encoded regular expression sql.
	 */
	function db_check_slug_regexp_sql( $type ) {

		// Search for percent-encoded (urlencoded) characters in slugs that are >= U+0300. Equivalent to '[\xcc-\xff]'.
		// Note this is only used to flag possible non-normalized chars that have been percent-encoded, not to fix as slugs aren't reliably urlencoded.
		static $slug_regexp = "'%c[c-f]|%[def][0-9a-f]'"; // Don't bother restricting \xf? to <= \xf4 (keep it as simple as possible).

		$ret = '';

		if ( isset( $this->db_slug_cols[ $type ] ) ) {
			$ret = $this->db_slug_cols[ $type ] . " RLIKE $slug_regexp";
		}

		return $ret;
	}

	/**
	 * Do database normalize (bulk action) on a number of percent-encoded slugs.
	 */
	function db_check_normalize_slugs( $checkeds, &$admin_notices ) {

		$num_updates = $num_locked = $num_fails = $num_missing = $num_skipped = 0;

		global $wpdb;

		$this->check_db_fields();

		if ( $this->no_normalizer || ! function_exists( 'normalizer_is_normalized' ) ) {
			$this->load_unfc_normalizer_class();
		}

		$current_user_id = get_current_user_id();

		foreach ( $checkeds as $checked ) {

			// Parse id:type:db_check_slugs_idx.
			if ( false == preg_match( '/^([1-9]\d*):(' . implode( '|', array_keys( $this->db_slug_cols ) ) . '):(\d+)$/', $checked, $matches ) ) {
				$admin_notices[] = array( 'error', $this->db_check_error_msg( UNFC_DB_CHECK_PARAM_ERROR ) );
				return false; // Should never happen so treat as fatal error.
			}
			$id = intval( $matches[1] );
			$type = $matches[2];
			$idx = intval( $matches[3] );

			if ( ! isset( $this->db_check_slugs[ $idx ] ) || $this->db_check_slugs[ $idx ]['id'] !== $id || $this->db_check_slugs[ $idx ]['type'] !== $type ) {
				$admin_notices[] = array( 'warning', $this->db_check_error_msg( UNFC_DB_CHECK_SYNC_ERROR ) ); // Treat mismatched slug indexes as warning.
				break; // Stop but report anything done so far.
			}

			$db_table = $wpdb->{$this->db_tables[ $type ]};
			$db_id_col = $this->db_id_cols[ $type ];
			$field = $this->db_slug_cols[ $type ];

			// Check that row still there.
			$obj = $wpdb->get_row( $wpdb->prepare( "SELECT $field FROM $db_table WHERE $db_id_col = %d", $id ) );
			if ( ! $obj ) {
				if ( $wpdb->last_error ) { // get_row() returns null on no result or on error so need to check.
					$admin_notices[] = array( 'error', $this->db_check_error_msg( UNFC_DB_CHECK_DB_ERROR ) );
					return false;
				}
				$num_missing++;
				unset( $this->db_check_slugs[ $idx ] );
				$this->db_check_num_slugs--;
			} else {

				// Check for post being locked by another user.
				$locked = 'post' === $type && self::wp_check_post_lock( $id, $current_user_id );
				if ( $locked ) {
					$num_locked++;
				} else {
					$data = array();
					if ( ! empty( $obj->$field ) ) {
						$decoded = self::percent_decode( $obj->$field ); // Note slugs aren't properly percent-encoded by sanitize_title_with_dashes() so not using rawurldecode().
						if ( ! ( $this->no_normalizer ? unfc_normalizer_is_normalized( $decoded ) : normalizer_is_normalized( $decoded ) ) ) {
							$normalized = $this->no_normalizer ? unfc_normalizer_normalize( $decoded ) : normalizer_normalize( $decoded );
							if ( false === $normalized ) { // Note this should only happen if the slug originally had raw (not percent-encoded) invalid UTF-8 sequences. Which shouldn't happen.
								$num_fails++;
							} else {
								$data[ $field ] = self::percent_encode( $normalized );
							}
						} else {
							$num_skipped++;
							unset( $this->db_check_slugs[ $idx ] );
							$this->db_check_num_slugs--;
						}
					} else {
						$num_skipped++;
						unset( $this->db_check_slugs[ $idx ] );
						$this->db_check_num_slugs--;
					}
					if ( $data ) {
						$update = $wpdb->update( $db_table, $data, array( $db_id_col => $id ) );
						if ( false === $update ) {
							$admin_notices[] = array( 'error', $this->db_check_error_msg( UNFC_DB_CHECK_DB_ERROR ) );
							return false;
						}
						$num_updates++;
						unset( $this->db_check_slugs[ $idx ] );
						$this->db_check_num_slugs--;
					}
				}
			}
		}

		if ( $num_updates ) {
			wp_cache_flush(); // Clear all cache items.
		}

		if ( $num_updates ) {
			/* translators: %s: formatted number of slugs normalized. */
			$admin_notices[] = array( 'updated', sprintf( _n( '%s slug normalized.', '%s slugs normalized.', $num_updates, 'unfc-normalize' ), number_format_i18n( $num_updates ) ) );
		} else {
			$admin_notices[] = array( 'updated', __( 'Nothing updated!', 'unfc-normalize' ) );
		}
		if ( $num_locked ) {
			/* translators: %s: formatted number of items locked. */
			$admin_notices[] = array( 'warning', sprintf(
				_n( '%s slug not normalized, somebody is editing it.', '%s slugs not normalized, somebody is editing them.', $num_locked, 'unfc-normalize' ), number_format_i18n( $num_locked )
			) );
		}
		if ( $num_missing ) {
			/* translators: %s: formatted number of slugs not found. */
			$admin_notices[] = array( 'warning', sprintf(
				_n( '%s slug not normalized, no longer exists.', '%s slugs not normalized, no longer exist.', $num_missing, 'unfc-normalize' ), number_format_i18n( $num_missing )
			) );
		}
		if ( $num_skipped ) {
			/* translators: %s: formatted number of slugs not non-normalized. */
			$admin_notices[] = array( 'warning', sprintf(
				_n( '%s slug not normalized, no longer non-normalized.', '%s slugs not normalized, no longer non-normalized.', $num_skipped, 'unfc-normalize' ), number_format_i18n( $num_skipped )
			) );
		}
		if ( $num_fails ) {
			/* translators: %s: formatted number of slugs that failed to normalize. */
			$admin_notices[] = array( 'warning', sprintf(
				_n( '%s slug not normalized, failed to normalize.', '%s slugs not normalized, failed to normalize.', $num_fails, 'unfc-normalize' ), number_format_i18n( $num_fails )
			) );
		}

		return $num_updates;
	}

	/**
	 * Check if using links and update db_fields map.
	 */
	function check_db_fields() {
		if ( get_option( 'link_manager_enabled' ) ) {
			if ( ! isset( $this->db_fields['link'] ) ) {
				$this->db_fields['link'] = $this->db_fields_link;
			}
		} else {
			if ( isset( $this->db_fields['link'] ) ) {
				unset( $this->db_fields['link'] );
			}
		}
	}

	/**
	 * Normalizes raw database data that may be serialized.
	 * Based on recursive_unserialize_replace() from "searchreplacedb2.php".
	 * See https://interconnectit.com/products/search-and-replace-for-wordpress-databases/
	 */
	function recursive_unserialize_normalize( $data = '', $serialised = false ) {
		// Some unserialised data cannot be re-serialised eg. SimpleXMLElements
		try {
			if ( is_string( $data ) && false !== ( $unserialized = @unserialize( $data ) ) ) {
				$data = $this->recursive_unserialize_normalize( $unserialized, true );

			} elseif ( is_array( $data ) ) {
				$_tmp = array();
				foreach ( $data as $key => $value ) {
					$_tmp[ $key ] = $this->recursive_unserialize_normalize( $value, false );
				}

				$data = $_tmp;
				unset( $_tmp );

			} else {
				if ( is_string( $data ) ) {
					$data = $this->no_normalizer ? unfc_normalizer_normalize( $data ) : normalizer_normalize( $data );
				}
			}

			if ( $serialised ) {
				return serialize( $data );
			}

		} catch( Exception $error ) {
		}

		return $data;
	}

	/**
	 * Get the amount to batch database queries by.
	 */
	function get_batch_limit( $from ) {
		// TODO: perhaps some groovy calculation based on database size and memory limit.

		$batch_limit = 'db_check_normalize_all' === $from ? UNFC_DB_CHECK_NORMALIZE_BATCH_LIMIT : UNFC_DB_CHECK_ITEM_BATCH_LIMIT;

		return apply_filters( 'unfc_batch_limit', $batch_limit, $from );
	}

	/**
	 * Get the amount to limit in-memory lists by.
	 */
	function get_list_limit( $sel ) {

		$list_limit = UNFC_DB_CHECK_LIST_LIMIT;

		$post_max_size = ini_get( 'post_max_size' );
		$memory_limit = ini_get( 'memory_limit' );
		if ( false !== $post_max_size && false !== $memory_limit ) {
			$post_max_size = wp_convert_hr_to_bytes( $post_max_size );
			$memory_limit = wp_convert_hr_to_bytes( $memory_limit );

			$limit = $memory_limit > 0 ? min( $post_max_size, $memory_limit / 4 ) : $post_max_size;

			// Allow v generous 1K per item overhead rounded down to a multiple of 10.
			$list_limit = max( 20, min( 10000, intval( $limit / 10240 ) * 10 ) ); // Limit to maximum 10000, minimum 20.
		}

		return apply_filters( 'unfc_list_limit', $list_limit, $sel );
	}

	/**
	 * Parse type "type:subtype" where optional delimited subtype has same chars as sanitize_key().
	 */
	function parse_type( $type ) {
		$this->check_db_fields();

		if ( false == preg_match( '/^(' . implode( '|', array_keys( $this->db_fields ) ) . ')(?::([a-z0-9_\-]+))?$/', strtolower( $type ), $matches ) ) {
			return array( '', '' );
		}
		return array( $matches[1], empty( $matches[2] ) ? '' : $matches[2] );
	}

	/**
	 * Make sure type legit.
	 */
	function sanitize_type( $type ) {
		list( $parse_type, $parse_subtype ) = $this->parse_type( $type );
		return $parse_type ? $type : '';
	}

	/**
	 * Convert percent-encoded valid non-ASCII UTF-8 chars to string. Not using rawurldecode() as slugs aren't properly urlencoded,
	 * so only converting valid UTF-8 sequences should guard against normalizations failing.
	 */
	static function percent_decode( $str ) {
		return preg_replace_callback(
			'/%(?:
			  (?:c[2-9a-f]|d[0-9a-f])
			| e(?:
				  0%[ab]
				| [1-9abcef]%[89ab]
				| d%[89]
				)[0-9a-f]
			| f(?:
				  0%[9ab]
				| [123]%[89ab]
				| 4%8
				)[0-9a-f]%[89ab][0-9a-f]
			)%[89ab][0-9a-f]/x',
			array( __CLASS__, 'percent_decode_cb' ), $str
		);
	}

	/**
	 * Callback for preg_replace in percent_decode().
	 */
	static function percent_decode_cb( $matches ) {
		$starter = hexdec( substr( $matches[0], 1, 2 ) );
		if ( $starter < 0xe0 ) {
			// %cc%80 - %df%bf
			return chr( $starter ) . chr( hexdec( substr( $matches[0], 4, 2 ) ) );
		}
		if ( $starter < 0xf0 ) {
			// %e0%80%80 - %ef%bf%bf
			return chr( $starter ) . chr( hexdec( substr( $matches[0], 4, 2 ) ) ) . chr( hexdec( substr( $matches[0], 7, 2 ) ) );
		}
		// %f0%80%80%80 - %f4%8f%bf%bf
		return chr( $starter ) . chr( hexdec( substr( $matches[0], 4, 2 ) ) ) . chr( hexdec( substr( $matches[0], 7, 2 ) ) ) . chr( hexdec( substr( $matches[0], 10, 2 ) ) );
	}

	/**
	 * Convert valid UTF-8 chars >= U+0080 to percent-encodings. Note not using rawurlencode() as need to ensure round-trip with percent_decode().
	 */
	static function percent_encode( $str ) {
		return preg_replace_callback(
			'/[\xc2-\xdf][\x80-\xbf]                          # non-overlong 2-byte
			| \xe0[\xa0-\xbf][\x80-\xbf]                      # excluding overlongs
			| [\xe1-\xec\xee\xef][\x80-\xbf][\x80-\xbf]       # straight 3-byte
			| \xed[\x80-\x9f][\x80-\xbf]                      # excluding surrogates
			| \xf0[\x90-\xbf][\x80-\xbf][\x80-\xbf]           # planes 1-3
			| [\xf1-\xf3][\x80-\xbf][\x80-\xbf][\x80-\xbf]    # planes 4-15
			| \xf4[\x80-\x8f][\x80-\xbf][\x80-\xbf]           # plane 16
			/x',
			array( __CLASS__,  'percent_encode_cb' ), $str
		);
	}

	/**
	 * Callback for preg_replace in percent_encode().
	 */
	static function percent_encode_cb( $matches ) {
		$starter = ord( $matches[0][0] );
		if ( $starter < 0xe0 ) {
			return sprintf( '%%%02x%%%02x', $starter, ord( $matches[0][1] ) );
		}
		if ( $starter < 0xf0 ) {
			return sprintf( '%%%02x%%%02x%%%02x', $starter, ord( $matches[0][1] ), ord( $matches[0][2] ) );
		}
		return sprintf( '%%%02x%%%02x%%%02x%%%02x', $starter, ord( $matches[0][1] ), ord( $matches[0][2] ), ord( $matches[0][3] ) );
	}

	/**
	 * This logic is copied from "wp-admin/post.php" wp_check_post_lock().
	 * Need this to avoid its get_post(), which consumes large amounts of memory.
	 */
	static function wp_check_post_lock( $post_id, $current_user_id ) {
		if ( !$lock = self::get_post_meta( $post_id, '_edit_lock', true ) )
			return false;

		$lock = explode( ':', $lock );
		$time = $lock[0];
		$user = isset( $lock[1] ) ? $lock[1] : self::get_post_meta( $post_id, '_edit_last', true );

		/** This filter is documented in wp-admin/includes/ajax-actions.php */
		$time_window = apply_filters( 'wp_check_post_lock_window', 150 );

		if ( $time && $time > time() - $time_window && $user != $current_user_id )
			return $user;
		return false;
	}

	/**
	 * Use this instead of WP get_post_meta() to avoid loading cache for memory reasons.
	 */
	static function get_post_meta( $post_id, $key = '', $single = false /*ignore*/ ) {
		global $wpdb;
		$ret = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s", $post_id, $key ) );
		return $ret;
	}
}

// Debug functions - no-ops unless WP_DEBUG is set.
if ( ! function_exists( 'unfc_debug_log' ) ) {
	require dirname( __FILE__ ) . '/debug.php';
}
