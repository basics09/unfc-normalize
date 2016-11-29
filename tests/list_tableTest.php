<?php
/**
 * Test list table functionality.
 *
 * @group unfc
 * @group unfc_list_table
 */
class Tests_UNFC_List_Table extends WP_UnitTestCase {

    /**
	 * @ticket unfc_list_table_list_table
     */
	function test_list_table() {
		$args = array( 'screen' => 'dummy', 'ajax' => true );
		$list_table = new _Dummy_UNFC_List_Table( $args );

		// Compat getters and setters.
		$this->assertSame( $args['screen'], $list_table->_args['screen'] );
		$this->assertSame( $args['ajax'], $list_table->_args['ajax'] );

		$this->assertTrue( isset( $list_table->screen ) );

		$screen = $list_table->screen;
		unset( $list_table->screen );
		$this->assertFalse( isset( $list_table->screen ) );

		$list_table->screen = $screen;
		$this->assertSame( $screen, $list_table->screen );

		// Compat call.
		$pagination_args = array(
			'total_items' => 0,
			'total_pages' => 0,
			'per_page' => 3,
		);
		$list_table->set_pagination_args( $pagination_args );

		// Total pages converted to float so use assertEquals not assertSame.
		$pagination_args['total_pages'] = 0;
		$this->assertEquals( $pagination_args, $list_table->_pagination_args );

		$pagination_args['total_items'] = 7;
		$list_table->set_pagination_args( $pagination_args );
		$pagination_args['total_pages'] = 3;
		$this->assertEquals( $pagination_args, $list_table->_pagination_args );
		$this->assertEquals( $pagination_args['per_page'], $list_table->get_pagination_arg( 'per_page' ) );

		$_REQUEST['paged'] = 10;
		$this->assertEquals( 3, $list_table->get_pagenum() );
		$pagination_args['total_items'] = 0;
		$pagination_args['total_pages'] = 0;
		$list_table->set_pagination_args( $pagination_args );
		$this->assertEquals( 1, $list_table->get_pagenum() );

		unset( $list_table->_pagination_args );
		ob_start();
		$list_table->pagination( 'top' );
		$out = ob_get_clean();
		$this->assertEmpty( $out );

		$pagination_args = array(
			'total_items' => 8,
			'total_pages' => 3,
			'per_page' => 3,
		);
		$list_table->set_pagination_args( $pagination_args );

		$_REQUEST['paged'] = 1;
		ob_start();
		$list_table->pagination( 'top' );
		$out = ob_get_clean();
		$this->assertNotEmpty( $out );
		$this->assertTrue( false === stripos( $out, 'first-page' ) );
		$this->assertTrue( false === stripos( $out, 'prev-page' ) );
		$this->assertTrue( false !== stripos( $out, 'next-page' ) );
		$this->assertTrue( false !== stripos( $out, 'last-page' ) );
		$this->assertTrue( false === stripos( $out, 'hide-if-js' ) );

		$pagination_args['infinite_scroll'] = true;
		$list_table->set_pagination_args( $pagination_args );

		$_REQUEST['paged'] = 2;
		ob_start();
		$list_table->pagination( 'top' );
		$out = ob_get_clean();
		$this->assertNotEmpty( $out );
		$this->assertTrue( false === stripos( $out, 'first-page' ) );
		$this->assertTrue( false !== stripos( $out, 'prev-page' ) );
		$this->assertTrue( false !== stripos( $out, 'next-page' ) );
		$this->assertTrue( false === stripos( $out, 'last-page' ) );
		$this->assertTrue( false !== stripos( $out, 'hide-if-js' ) );

		$_REQUEST['paged'] = 3;
		ob_start();
		$list_table->pagination( 'bottom' );
		$out = ob_get_clean();
		$this->assertNotEmpty( $out );
		$this->assertTrue( false !== stripos( $out, 'first-page' ) );
		$this->assertTrue( false !== stripos( $out, 'prev-page' ) );
		$this->assertTrue( false === stripos( $out, 'next-page' ) );
		$this->assertTrue( false === stripos( $out, 'last-page' ) );
		$this->assertTrue( false !== stripos( $out, 'hide-if-js' ) );

		$pagination_args['total_pages'] = $pagination_args['total_items'] = 0;
		$list_table->set_pagination_args( $pagination_args );
		ob_start();
		$list_table->pagination( 'bottom' );
		$out = ob_get_clean();
		error_log( "out=$out" );
		$this->assertNotEmpty( $out );
		$this->assertTrue( false !== stripos( $out, 'no-pages' ) );
		$this->assertTrue( false !== stripos( $out, '0 items' ) );
		$this->assertTrue( false === stripos( $out, 'first-page' ) );
		$this->assertTrue( false === stripos( $out, 'prev-page' ) );
		//$this->assertTrue( false === stripos( $out, 'next-page' ) ); // Why set?
		//$this->assertTrue( false === stripos( $out, 'last-page' ) ); // Why set?
	}

    /**
	 * @ticket unfc_list_table_columns
     */
	function test_columns() {
		$list_table = new _Dummy_UNFC_List_Table( array( 'screen' => 'dummy' ) );

		$this->assertEmpty( $list_table->get_sortable_columns() );
		$this->assertSame( 4, $list_table->get_column_count() );
		$this->assertEmpty( $list_table->extra_tablenav( 'top' ) );
		$this->assertEmpty( $list_table->column_default( 'item', 'column_name' ) );
		$this->assertEmpty( $list_table->column_cb( 'item' ) );
	}

    /**
	 * @ticket unfc_list_table_search_box
     */
	function test_search_box() {
		$list_table = new _Dummy_UNFC_List_Table( array( 'screen' => 'dummy' ) );

		$list_table->prepare_items();
		$this->assertFalse( $list_table->has_items() );

		$_REQUEST = array( 's' => '' );
		ob_start();
		$list_table->search_box( 'Search', 1 );
		$out = ob_get_clean();
		$this->assertEmpty( $out );

		$list_table->num_items = 5;
		$list_table->prepare_items();
		$this->assertTrue( $list_table->has_items() );

		$this->assertSame( 'id', $list_table->get_primary_column() );

		$_REQUEST = array( 's' => 'search item', 'orderby' => 'date', 'order' => 'DESC', 'post_mime_type' => 'image/jpg', 'detached' => 'true' );
		ob_start();
		$list_table->search_box( 'Search', 1 );
		$out = ob_get_clean();
		$this->assertNotEmpty( $out );
		$this->assertTrue( false !== stripos( $out, 'orderby' ) );
		$this->assertTrue( false !== stripos( $out, 'order' ) );
		$this->assertTrue( false !== stripos( $out, 'post_mime_type' ) );
		$this->assertTrue( false !== stripos( $out, 'detached' ) );
	}

    /**
	 * @ticket unfc_list_table_views
     */
	function test_views() {
		$list_table = new _Dummy_UNFC_List_Table( array( 'screen' => 'dummy' ) );

		ob_start();
		$list_table->views();
		$out = ob_get_clean();
		$this->assertEmpty( $out );

		$list_table->num_views = 5;
		ob_start();
		$list_table->views();
		$out = ob_get_clean();
		$this->assertNotEmpty( $out );
		$this->assertTrue( false !== stripos( $out, 'viewid1' ) );
		$this->assertTrue( false !== stripos( $out, 'viewid5' ) );

		ob_start();
		$list_table->view_switcher( 'list' );
		$out = ob_get_clean();
		$this->assertNotEmpty( $out );
		$this->assertTrue( false !== stripos( $out, 'view-list current' ) );

		ob_start();
		$list_table->view_switcher( 'excerpt' );
		$out = ob_get_clean();
		$this->assertNotEmpty( $out );
		$this->assertTrue( false !== stripos( $out, 'view-excerpt current' ) );
	}

    /**
	 * @ticket unfc_list_table_actions
     */
	function test_actions() {
		$list_table = new _Dummy_UNFC_List_Table( array( 'screen' => 'dummy' ) );

		$actions = array();
		$out = $list_table->row_actions( $actions );
		$this->assertEmpty( $out );

		$actions = array( 'action1' => 'link1', 'action2' => 'link2' );
		$out = $list_table->row_actions( $actions );
		$this->assertNotEmpty( $out );
		$this->assertTrue( false !== stripos( $out, 'action1' ) );
		$this->assertTrue( false !== stripos( $out, 'action2' ) );

		$_REQUEST = array();
		$this->assertFalse( $list_table->current_action() );
		$_REQUEST = array( 'action' => 'action1' );
		$this->assertSame( 'action1', $list_table->current_action() );
		$_REQUEST = array( 'action2' => 'action1' );
		$this->assertSame( 'action1', $list_table->current_action() );
		$_REQUEST = array( 'action2' => 'action1', 'filter_action' => 'filter' );
		$this->assertFalse( $list_table->current_action() );
	}

    /**
	 * @ticket unfc_list_table_months_dropdown
     */
	function test_months_dropdown() {
		$list_table = new _Dummy_UNFC_List_Table( array( 'screen' => 'dummy' ) );

		ob_start();
		$list_table->months_dropdown( 'post' );
		$out = ob_get_clean();
		$this->assertEmpty( $out );

		$post1 = $this->factory->post->create_and_get( array( 'post_title' => 'title1', 'post_content' => 'content1', 'post_type' => 'post', 'post_date' => '2016-11-28' ) );
		$this->assertTrue( is_object( $post1 ) );

		ob_start();
		$list_table->months_dropdown( 'post' );
		$out = ob_get_clean();
		$this->assertNotEmpty( $out );
		$this->assertTrue( false !== stripos( $out, '201611' ) );

		$post2 = $this->factory->post->create_and_get( array( 'post_title' => 'title2', 'post_content' => 'content2', 'post_type' => 'post', 'post_date' => '2016-10-28' ) );
		$this->assertTrue( is_object( $post2 ) );

		ob_start();
		$list_table->months_dropdown( 'post' );
		$out = ob_get_clean();
		$this->assertNotEmpty( $out );
		$this->assertTrue( false !== stripos( $out, '201611' ) );
		$this->assertTrue( false !== stripos( $out, '201610' ) );
	}

    /**
	 * @ticket unfc_list_table_comments
     */
	function test_comments() {
		$list_table = new _Dummy_UNFC_List_Table( array( 'screen' => 'dummy' ) );

		ob_start();
		$list_table->comments_bubble( 0, 0 );
		$out = ob_get_clean();
		$this->assertNotEmpty( $out );
		$this->assertTrue( false !== stripos( $out, 'no comments' ) );

		ob_start();
		$list_table->comments_bubble( 0, 2 );
		$out = ob_get_clean();
		$this->assertNotEmpty( $out );
		$this->assertTrue( false !== stripos( $out, 'no approved' ) );
		$this->assertTrue( false !== stripos( $out, '2 pending' ) );

		add_filter( 'get_comments_number', array( $this, 'filter_get_comments_number' ), 10, 2 );

		ob_start();
		$list_table->comments_bubble( 1, 1 );
		$out = ob_get_clean();
		$this->assertNotEmpty( $out );
		$this->assertTrue( false !== stripos( $out, '3 approved' ) );
		$this->assertTrue( false !== stripos( $out, '1 pending' ) );

		remove_filter( 'get_comments_number', array( $this, 'filter_get_comments_number' ), 10 );
	}

	function filter_get_comments_number( $count, $post_id ) {
		return 3;
	}
}
if ( ! class_exists( 'UNFC_List_Table' ) ) {
	require dirname( dirname( __FILE__ ) ) . '/includes/class-unfc-list-table.php';
}
class _Dummy_UNFC_List_Table extends UNFC_List_Table {

	var $num_items = 0;
	var $num_views = 0;

	protected $columns;
	protected $views;

	public function __construct( $args = array() ) {
		parent::__construct( $args );
		$this->columns = array( 'id' => 'ID', 'title' => 'Title', 'val' => 'Val', 'date' => 'Date' );
	}
	public function ajax_user_can() {
		return true;
	}
	public function prepare_items() {
		$this->items = array();
		for ( $i = 1; $i <= $this->num_items; $i++ ) {
			$this->items[] = array( $i, 'title' . $i, 'val' . $i, 'date' . $i );
		}
	}
	protected function get_views() {
		$this->views = array();
		for ( $i = 1; $i <= $this->num_views; $i++ ) {
			$this->views[ 'viewid' . $i ] = admin_url( 'view-url-' . $i );
		}
		return $this->views;
	}
	public function get_columns() {
		return $this->columns;
	}
}
