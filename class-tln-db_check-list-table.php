<?php
/**
 * Lists.
 */

require dirname( __FILE__ ) . '/class-tln-list-table.php'; // Our (almost-)clone of WP_List_Table.

/**
 * Shared parent functionality for lists.
 */
class TLN_DB_Check_List_Table extends TLN_List_Table {

	static $tlnormalizer = null; // Handy pointer to global $tlnormalizer (main plugin class instance).

	var $all_items = null; // Reference to all the items (as opposed to per-page $items); points to either $db_check_items or $db_check_slugs. Set by children.

	var $standard_types = array(); // Map of standard types to names. Populated in __construct().

	var $suptypes = array(); // Map of "supertypes" to types. Ie. 'post' and 'term' types which can have types 'attachment', 'category' etc.
	var $types = array(); // Map of types to names. Populated with types and custom ones in add_type().

	var $type = ''; // Set if queried for type (via 'tln_type').
	var $tln_type = ''; // The _REQUEST['tln_type'] sanitized - either "$type" or "$type:$subtype".

	var $query_vars = array( 'page' => TLN_DB_CHECK_MENU_SLUG ); // Added to with query vars. Used for printing hidden inputs for table form.

	public function __construct( $args = array() ) {
		parent::__construct( array(
				'ajax' => true,
				'screen' => TLN_DB_CHECK_MENU_SLUG,
			) 
		);
		if ( ! $this->wp_less_than_4_4 ) { // Added to TLN_List_Table for backward-compat.
			$this->screen->set_screen_reader_content();
		}

		global $tlnormalizer;
		self::$tlnormalizer = $tlnormalizer;

		$this->items = array(); // Defined in parent. The slice of all items in a page.

		$this->standard_types = array(
			'post' => __( 'Post, Page', 'normalizer' ),
			'comment' => __( 'Comment' /*Use WP string*/ ),
			'term' => __( 'Category, Tag', 'normalizer' ),
			'user' => __( 'User', 'normalizer' ),
			'options' => __( 'Options', 'normalizer' ),
			'settings' => __( 'Settings', 'normalizer' ),
			'link' => __( 'Link', 'normalizer' ),
		);
	}

	// Overridden methods.

	/**
	 * Prepares the list of items for displaying.
	 */
	function prepare_items() {
		// Don't bother resetting types, subtypes.
		foreach ( $this->all_items as $item ) {
			$this->add_type( $item['type'], $item['subtype'] );
		}

		$this->type = $subtype = $this->tln_type = '';
		if ( ! empty( $_REQUEST['tln_type'] ) && is_string( $_REQUEST['tln_type'] ) ) {
			list( $this->type, $subtype ) = self::$tlnormalizer->parse_type( $_REQUEST['tln_type'] );
			if ( $this->type ) {
				$this->query_vars['tln_type'] = $this->tln_type = "{$this->type}:{$subtype}";
				$this->add_type( $this->type, $subtype );
			}
		}

		$orderby = 'title';
		if ( isset( $_REQUEST['orderby'] ) && is_string( $_REQUEST['orderby'] ) ) {
			$orderby = strtolower( $_REQUEST['orderby'] );
			$sortable_columns = $this->get_sortable_columns();
			if ( ! isset( $sortable_columns[ $orderby ] ) ) {
				$orderby = 'title';
			}
			$this->query_vars['orderby'] = $_GET['orderby'] = $orderby; // TLN_List_Table uses $_GET.
		}
		$order = 'asc';
		if ( isset( $_REQUEST['order'] ) && is_string( $_REQUEST['order'] ) ) {
			$order = 'desc' === strtolower( $_REQUEST['order'] ) ? 'desc' : 'asc';
			$this->query_vars['order'] = $_GET['order'] = $order; // TLN_List_Table uses $_GET.
		}

		$this->sort( $orderby, $order );

		$total_items = count( $this->all_items );

		$per_page = $this->get_items_per_page( TLN_DB_CHECK_PER_PAGE );
		$total_pages = intval( ceil( $total_items / $per_page ) );

		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'total_pages' => $total_pages,
			'per_page' => $per_page
		) );

		$pagenum = $this->get_pagenum();

		$offset = absint( ( $pagenum - 1 ) * $per_page );
		$this->items = array_slice( $this->all_items, $offset, $per_page );

		// Set up REQUEST_URI for use by TLN_List_Table.
		if ( isset( $_REQUEST['_wp_http_referer'] ) && is_string( $_REQUEST['_wp_http_referer'] ) ) {
			$request_uri = stripslashes( $_REQUEST['_wp_http_referer'] );
		} else {
			$request_uri = stripslashes( $_SERVER['REQUEST_URI'] );
		}
		$request_uri = remove_query_arg( array( '_wp_http_referer', '_wpnonce' ), $request_uri );

		$_SERVER['REQUEST_URI'] = add_query_arg( $this->query_vars, $request_uri );
	}

	/**
	 * Get a list of all, hidden and sortable columns, with filter applied
	 */
	protected function get_column_info() {
		$columns = $this->get_columns();
		$hidden = array();
		$sortable = $this->get_sortable_columns();
		$primary = 'title';

		return array( $columns, $hidden, $sortable, $primary );
	}

	/**
	 * Display the pagination.
	 */
	protected function pagination( $which ) {
		// Override to put "&paged=1" on first-page link if any (useful for detecting if just landed on listing or not).
		ob_start();
		parent::pagination( $which );
		$get = ob_get_clean();
		echo preg_replace( '/(<a class=["\']first-page["\'] href=[\'"])([^\'"]+)/', '$1$2&paged=1', $get );
	}

	/**
	 * Print column headers, accounting for hidden and sortable columns.
	 */
	public function print_column_headers( $with_id = true ) {
		// Override to put back "&paged=pagenum" on sort links (if not 1) - seems odd that they're removed.
		// A bit naughty as goes against standard admin behaviour. There's probably a good reason not to do it.
		$paged = $this->get_pagenum();
		if ( $paged < 1 ) {
			parent::print_column_headers( $with_id );
		} else {
			ob_start();
			parent::print_column_headers( $with_id );
			$get = ob_get_clean();
			echo preg_replace( '/(<a href=[\'"])([^\'"]+)/', '$1$2&paged=' . $paged, $get );
		}
	}

	// Our methods.

	/**
	 * Output "Title" column.
	 */
	function column_title( $item ) {
		$aria_label_html = '';
		// Note in some cases outputting edit link without regard to whether current user can edit.
		if ( 'post' === $item['type'] ) {
			if ( 'nav_menu_item' === $item['subtype'] ) {
				$menu_id = $this->get_menu_id( $item['id'] );
				if ( $menu_id ) {
					$url = admin_url( 'nav-menus.php?action=edit&menu=' . $menu_id );
					/* translators: %s: menu item name */
					$aria_label_html = sprintf( ' aria-label="%s"', esc_attr( __( 'Edit the menu containing this menu item', 'normalizer' ) ) );
				}
			} else {
				$url = get_edit_post_link( $item['id'] );
				if ( $url ) {
					/* translators: %s: post title */
					$aria_label_html = sprintf( ' aria-label="%s"', esc_attr( sprintf( __( '&#8220;%s&#8221; (Edit)' /*Use WP string*/ ), $item['title'] ) ) );
				}
			}
		} elseif ( 'comment' === $item['type'] ) {
			$url = admin_url( 'comment.php?action=editcomment&c=' . $item['id'] );
			$aria_label_html = sprintf( ' aria-label="%s"', esc_attr( __( 'Edit this comment' /*Use WP string*/ ) ) );
		} elseif ( 'user' === $item['type'] ) {
			$url = get_edit_user_link( $item['id'] );
			if ( $url ) {
				$aria_label_html = sprintf( ' aria-label="%s"', esc_attr( __( 'Edit this user', 'normalizer' ) ) );
			}
		} elseif ( 'term' === $item['type'] ) {
			if ( 'nav_menu' === $item['subtype'] ) {
				$url = admin_url( 'nav-menus.php?action=edit&menu=' . $item['id'] );
			} else {
				$url = get_edit_term_link( $item['id'], $item['subtype'] );
			}
			if ( $url ) {
				/* translators: %s: taxonomy term name */
				$aria_label_html = sprintf( ' aria-label="%s"', esc_attr( sprintf( __( '&#8220;%s&#8221; (Edit)' /*Use WP string*/ ), $item['title'] ) ) );
			}
		} elseif ( 'options' === $item['type'] ) {
			$url = ''; // TODO: Map standard options to a url.
		} elseif ( 'settings' === $item['type'] ) {
			$url = ''; // TODO: Map standard settings to a url.
		} elseif ( 'link' === $item['type'] ) {
			$url = get_edit_bookmark_link( $item['id'] );
			if ( $url ) {
				/* translators: %s: link name */
				$aria_label_html = sprintf( ' aria-label="%s"', esc_attr( sprintf( __( 'Edit &#8220;%s&#8221;' /*Use WP string*/ ), $item['title'] ) ) );
			}
		} else { // Shouldn't happen.
			$url = '';
		}
		if ( $url ) {
			printf( '<a class="row-title" href="%s"%s>%s</a>', esc_url( $url ), $aria_label_html, htmlspecialchars( $item['title'], ENT_NOQUOTES ) );
		} else {
			echo htmlspecialchars( $item['title'], ENT_NOQUOTES );
		}
	}

	/**
	 * Sort "Title" column.
	 */
	function sort_title() {
		return array_map( 'remove_accents', wp_list_pluck( $this->all_items, 'title' ) );
	}

	/**
	 * Get the menu id for a menu item.
	 */
	function get_menu_id( $menu_item_id ) {
		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare( "SELECT term_taxonomy_id FROM {$wpdb->term_relationships} WHERE object_id = %d", $menu_item_id ) );
	}

	/**
	 * Output "Type" column.
	 */
	function column_type( $item ) {
		echo htmlspecialchars( $this->types[ $item['subtype'] ], ENT_NOQUOTES ); // Note: signed-up member of the Extraordinarily Severe Campaign Against Pointless Encoding.
	}

	/**
	 * Sort "Type" column.
	 */
	function sort_type() {
		return array_map( array( $this, 'sort_type_map_cb' ), wp_list_pluck( $this->all_items, 'subtype' ) );
	}

	/**
	 * Callback for array_map() in sort_type().
	 */
	function sort_type_map_cb( $subtype ) {
		return remove_accents( $this->types[ $subtype ] );
	}

	/**
	 * Sort items.
	 */
	function sort( $orderby, $order ) {
		$sort_method = 'sort_' . $orderby;
		$sort_order = 'desc' === $order ? SORT_DESC : SORT_ASC;
		$sort_flag = SORT_STRING;
		if ( defined( 'SORT_FLAG_CASE' ) ) { // SORT_FLAG_CASE is PHP 5.4.0
			$sort_flag |= SORT_FLAG_CASE;
		}
		if ( 'title' === $orderby ) {
			array_multisort( $this->$sort_method(), $sort_order, $sort_flag, $this->all_items );
		} else {
			// Subsort by title ascending.
			array_multisort( $this->$sort_method(), $sort_order, $sort_flag, $this->sort_title(), SORT_ASC, $sort_flag, $this->all_items );
		}
	}

	/**
	 * Keep track of the types available.
	 */
	function add_type( $type, $subtype ) {
		// Keep track of sub and custom types.
		if ( ! isset( $this->suptypes[ $subtype ] ) ) {
			if ( 'post' === $type ) {
				$type_obj = get_post_type_object( $subtype );
				$this->types[ $subtype ] = $type_obj && isset( $type_obj->labels ) && $type_obj->labels->singular_name ? $type_obj->labels->singular_name : $subtype; 
				$this->suptypes[ $subtype ] = 'post';
			} elseif ( 'term' === $type ) {
				$type_obj = get_taxonomy( $subtype );
				$this->types[ $subtype ] = $type_obj && isset( $type_obj->labels ) && $type_obj->labels->singular_name ? $type_obj->labels->singular_name : $subtype; 
				$this->suptypes[ $subtype ] = 'term';
			}
		}
		if ( ! isset( $this->types[ $subtype ] ) ) {
			$this->types[ $subtype ] = $this->standard_types[ $type ];
		}
	}

	/**
	 * Print query vars as hiddens (for table form).
	 */
	function hiddens() {
		foreach ( $this->query_vars as $name => $value ) {
		?>
			<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>">
		<?php
		}
	}
}

/**
 * Normalizer Items List Table class.
 * List of non-normalized items, up to TLN_DB_CHECK_LIST_LIMIT.
 */
class TLN_DB_Check_Items_List_Table extends TLN_DB_Check_List_Table {
	public function __construct() {
		parent::__construct();
		$this->all_items = &self::$tlnormalizer->db_check_items; // Will be sorted so use reference to avoid copy.
		if ( self::$tlnormalizer->no_normalizer || ! function_exists( 'normalizer_is_normalized' ) ) {
			self::$tlnormalizer->load_tln_normalizer_class();
		}
		if ( ! self::$tlnormalizer->dont_js ) {
			add_action( 'admin_print_footer_scripts', array( $this, 'admin_print_footer_scripts' ) );
		}
	}

	// Overridden methods.

	/**
	 * Get a list of columns. The format is:
	 * 'internal-name' => 'Title'
	 */
	public function get_columns() {
		$columns = array();

		$columns['title'] = __( 'Title' /*Use WP string*/ );
		$columns['type'] = __( 'Type', 'normalizer' );
		$columns['field'] = __( 'Field (1st detected only)', 'normalizer' );

		return $columns;
	}

	/**
	 * Get a list of sortable columns. The format is:
	 * 'internal-name' => 'orderby'
	 * or
	 * 'internal-name' => array( 'orderby', true )
	 *
	 * The second format will make the initial sorting order be descending
	 */
	protected function get_sortable_columns() {
		return array(
			'title' => array( 'title', empty( $_REQUEST['orderby'] ) ),
			'type' => array( 'type', false ),
			'field' => array( 'field', false ),
		);
	}

	// Our methods.

	/**
	 * Output "Field" column.
	 */
	function column_field( $item ) {
		echo htmlspecialchars( $item['field'], ENT_NOQUOTES );
	}

	/**
	 * Sort "Field" column.
	 */
	function sort_field() {
		return wp_list_pluck( $this->all_items, 'field' );
	}

	/**
	 * Print query vars as hiddens (for table form).
	 */
	function hiddens() {
		if ( isset( $_REQUEST['tln_trans'] ) && is_string( $_REQUEST['tln_trans'] ) && 0 === strpos( $_REQUEST['tln_trans'], 'tln_db_check_items' ) ) {
			$this->query_vars['tln_trans'] = $_REQUEST['tln_trans'];
		}
		parent::hiddens();
	}

	/**
	 *  Called on 'admin_print_footer_scripts'.
	 */
	public function admin_print_footer_scripts() {
		?>
		<script type="text/javascript">
			jQuery( function ( $ ) { // On jQuery ready.
				tl_normalize.db_check_list( <?php echo json_encode( TLN_DB_CHECK_ITEMS_LIST_SEL ); ?> );
			} );
		</script>
		<?php
	}
}

/**
 * Normalizer Slugs List Table class.
 * List of non-normalized percent-encoded slugs, up to TLN_DB_CHECK_LIST_LIMIT.
 */
class TLN_DB_Check_Slugs_List_Table extends TLN_DB_Check_List_Table {

	var $idx = 0; // Index into all items array - set in overridden display_rows() method.

	public function __construct() {
		parent::__construct();
		$this->all_items = &self::$tlnormalizer->db_check_slugs; // Will be sorted so use reference to avoid copy.
		if ( ! self::$tlnormalizer->dont_js ) {
			add_action( 'admin_print_footer_scripts', array( $this, 'admin_print_footer_scripts' ) );
		}
	}

	// Overridden methods.

	/**
	 * Get an associative array ( option_name => option_title ) with the list
	 * of bulk actions available on this table.
	 */
	protected function get_bulk_actions() {
		$actions = array(
			'tln_db_check_normalize_slugs' => __( 'Normalize', 'normalizer' ),
		);
		return $actions;
	}

	/**
	 * Get a list of columns. The format is:
	 * 'internal-name' => 'Title'
	 */
	public function get_columns() {
		$columns = array();

		$columns['cb'] = '<input type="checkbox" />';
		$columns['title'] = __( 'Title' /*Use WP string*/ );
		$columns['type'] = __( 'Type', 'normalizer' );
		$columns['slug'] = __( 'Slug', 'normalizer' );
		$columns['decoded'] = __( 'Decoded', 'normalizer' );
		$columns['normalized'] = __( 'If Normalized', 'normalizer' );
		$columns['normalized_decoded'] = __( 'Normalized Decoded', 'normalizer' );

		return $columns;
	}

	/**
	 * Get a list of sortable columns. The format is:
	 * 'internal-name' => 'orderby'
	 * or
	 * 'internal-name' => array( 'orderby', true )
	 *
	 * The second format will make the initial sorting order be descending
	 */
	protected function get_sortable_columns() {
		return array(
			'title' => array( 'title', empty( $_REQUEST['orderby'] ) ),
			'type' => array( 'type', false ),
			'slug' => array( 'slug', false ),
		);
	}

	/**
	 * Handles the checkbox column output.
	 */
	protected function column_cb( $item ) {
		$value = $item['id'] . ':' . $item['type'] . ':' . $item['idx'];
		?>
		<label class="screen-reader-text" for="cb-select-<?php echo $item['id']; ?>"><?php printf( __( 'Select %s' /*Use WP string*/ ), $item['title'] ); ?></label>
		<input class="hide-if-no-js" id="cb-select-<?php echo $item['id']; ?>" type="checkbox" name="item[]" value="<?php echo esc_attr( $value ); ?>" />
		<?php
	}

	// Our methods.

	/**
	 * Output "Slug" column.
	 */
	function column_slug( $item ) {
		echo htmlspecialchars( $item['slug'], ENT_NOQUOTES );
	}

	/**
	 * Sort "Slug" column.
	 */
	function sort_slug() {
		return wp_list_pluck( $this->all_items, 'slug' );
	}

	/**
	 * Output "Decoded" column.
	 */
	function column_decoded( $item ) {
		echo htmlspecialchars( rawurldecode( $item['slug'] ), ENT_NOQUOTES ); // Note using real rawurldecode() not our subset version TLNormalizer::percent_decode().
	}

	/**
	 * Output "If Normalized" column.
	 */
	function column_normalized( $item ) {
		$decoded = TLNormalizer::percent_decode( $item['slug'] );
		if ( ! ( self::$tlnormalizer->no_normalizer ? tl_normalizer_is_normalized( $decoded ) : normalizer_is_normalized( $decoded ) ) ) {
			$normalized = self::$tlnormalizer->no_normalizer ? tl_normalizer_normalize( $decoded ) : normalizer_normalize( $decoded );
			if ( false === $normalized ) {
				_e( 'Not normalizable!', 'normalizer' );
			} else {
				echo htmlspecialchars( TLNormalizer::percent_encode( $normalized ), ENT_NOQUOTES );
			}
		} else {
			_e( 'No difference!', 'normalizer' );
		}
	}

	/**
	 * Output "Normalized Decoded" column.
	 */
	function column_normalized_decoded( $item ) {
		$decoded = TLNormalizer::percent_decode( $item['slug'] );
		if ( ! ( self::$tlnormalizer->no_normalizer ? tl_normalizer_is_normalized( $decoded ) : normalizer_is_normalized( $decoded ) ) ) {
			$normalized = self::$tlnormalizer->no_normalizer ? tl_normalizer_normalize( $decoded ) : normalizer_normalize( $decoded );
			if ( false === $normalized ) {
				_e( 'Not normalizable!', 'normalizer' );
			} else {
				echo htmlspecialchars( rawurldecode( TLNormalizer::percent_encode( $normalized ) ), ENT_NOQUOTES ); // Re-encode and rawurldecode to give accurate representation.
			}
		} else {
			_e( 'No difference!', 'normalizer' );
		}
	}

	/**
	 * Print query vars as hiddens (for table form).
	 */
	function hiddens() {
		if ( isset( $_REQUEST['tln_trans'] ) && is_string( $_REQUEST['tln_trans'] ) && 0 === strpos( $_REQUEST['tln_trans'], 'tln_db_check_slugs' ) ) {
			$this->query_vars['tln_trans'] = $_REQUEST['tln_trans'];
		}
		parent::hiddens();
	}

	/**
	 *  Called on 'admin_print_footer_scripts'.
	 */
	public function admin_print_footer_scripts() {
		?>
		<script type="text/javascript">
			jQuery( function ( $ ) { // On jQuery ready.
				tl_normalize.db_check_list( <?php echo json_encode( TLN_DB_CHECK_SLUGS_LIST_SEL ); ?> );
			} );
		</script>
		<?php
	}
}
