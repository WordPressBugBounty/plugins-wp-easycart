<?php
class ec_paging {
	public $current_page;
	public $total_pages;

	private $total_products;
	private $num_per_page;
	private $start_item;

	private $store_page;
	private $permalink_divider;

	const MAX_PAGES_SHOWN = 5;

	function __construct( $num_per_page ) {
		$this->total_products = 0;
		$this->num_per_page = $num_per_page;
		$this->current_page = $this->get_current_page( );
		$this->total_pages = 0;
		$this->start_item = ( ( $this->current_page - 1 ) * $this->num_per_page );

		$storepageid = get_option( 'ec_option_storepage' );
		$this->store_page = get_permalink( $storepageid );

		if ( class_exists( "WordPressHTTPS" ) && isset( $_SERVER['HTTPS'] ) ) {
			$https_class = new WordPressHTTPS();
			$this->store_page = $https_class->makeUrlHttps( $this->store_page );
		}

		if ( substr_count( $this->store_page, '?' ) ) {
			$this->permalink_divider = "&";
		} else {
			$this->permalink_divider = "?";
		}
	}

	public function update_product_count( $total_products ) {
		$this->total_products = $total_products;
		$this->total_pages = $this->get_total_pages();
	}

	private function get_current_page() {
		if ( isset( $_GET['pagenum'] ) ) {
			return intval( $_GET['pagenum'] );
		} else {
			return 1;
		}
	}

	private function get_total_pages() {
		if ( $this->num_per_page > 0 ) {
			return ceil( $this->total_products / $this->num_per_page );
		} else {
			return 0;
		}
	}

	private function start_page() {
		if ( $this->current_page <= ceil( self::MAX_PAGES_SHOWN / 2 ) ) {
			return 1;
		} else if ( ($this->current_page + ceil( self::MAX_PAGES_SHOWN / 2 ) ) > $this->total_pages ) {
			return ( $this->total_pages - self::MAX_PAGES_SHOWN + 1 );
		} else {
			return ( $this->current_page - ceil(self::MAX_PAGES_SHOWN / 2 ) + 1 );
		}
	}

	private function end_page() {
		if ( $this->total_pages < self::MAX_PAGES_SHOWN ) {
			return $this->total_pages;
		} else if ( $this->current_page <= ceil( self::MAX_PAGES_SHOWN / 2 ) ) {
			return self::MAX_PAGES_SHOWN;
		} else if ( ( $this->current_page + ceil( self::MAX_PAGES_SHOWN / 2 ) ) > $this->total_pages ) {
			return $this->total_pages;
		} else {
			return ( $this->current_page + ceil( self::MAX_PAGES_SHOWN / 2 ) - 1 );
		}
	}

	public function display_paging_links( $divider, $link_string ) {
		$ret_string = '';
		if ( 1 != $this->current_page ) {
			$ret_string .= '<a href="' . $link_string . '&amp;pagenum=' . ( $this->current_page - 1 ) . '" class="ec_prev_link">< Prev</a>' . $divider;
		}

		for ( $i = $this->start_page(); $i <= $this->end_page(); $i++ ) {
			if ( $i == $this->current_page ) {
				$ret_string .= $this->get_selected_link( $i );
			} else {
				$ret_string .= $this->get_link( $i, $link_string );
			}
			if ( $i != $this->total_pages ) {
				$ret_string .= $divider;
			}
		}

		if ( $this->current_page != $this->total_pages ) {
			$ret_string .= $divider . '<a href="' . $link_string . '&amp;pagenum=' . ( $this->current_page + 1 ) . '" class="ec_next_link">Next ></a>';
		}

		return $ret_string;
	}

	private function get_selected_link( $i ) {
		return "<span class=\"ec_selected_page\">" . ($i) . "</span>";
	}

	private function get_link( $i, $link_string ) {
		return "<a href=\"" . $link_string . "&amp;pagenum=" .  $i . "\" class=\"ec_page_link\">" . $i . "</a>";
	}

	public function get_limit_query( $atts = array() ) {
		$use_paging = ( isset( $atts['paging'] ) ) ? $atts['paging'] : get_option( 'ec_option_enable_product_paging' );
		if ( $use_paging && is_numeric( $this->start_item ) && is_numeric( $this->num_per_page ) && $this->num_per_page > 0 ) {
			return sprintf( " LIMIT %d, %d", $this->start_item, $this->num_per_page );
		} else {
			return '';
		}
	}

	public function get_prev_page_link() {
		return $this->get_current_url( $this->current_page - 1 );
	}

	public function get_page_link( $i ) {
		return $this->get_current_url( $i );
	}

	public function get_next_page_link() {
		return $this->get_current_url( $this->current_page + 1 );
	}

	private function get_current_url( $pagenum ) {
		$url = 'http';
		if( isset( $_SERVER["HTTPS"] ) && $_SERVER["HTTPS"] == "on" ){
			$url .= "s";
		}
		$url .= "://";

		if ( (int) $_SERVER["SERVER_PORT"] != 80 && (int) $_SERVER["SERVER_PORT"] != 443 ) {
			$url .= sanitize_url( $_SERVER["HTTP_HOST"] ) . ":" . (int) $_SERVER["SERVER_PORT"];
		} else {
			$url .= sanitize_url( $_SERVER["HTTP_HOST"] );
		}

		if ( substr_count( $url, '?' ) ) {
			$url .= "&";
		} else {
			$url = "?";
		}
		$url .= 'pagenum=' . (int) $pagenum;

		if( isset( $_GET['manufacturer'] ) ){
			$url .= "&manufacturer=" . htmlentities( sanitize_text_field( $_GET['manufacturer'] ), ENT_QUOTES );
		}

		if( isset( $_GET['pricepoint'] ) ){
			$url .= "&pricepoint=" . htmlentities( (int) $_GET['pricepoint'], ENT_QUOTES );
		}

		if( isset( $_GET['ec_search'] ) ){
			$url .= "&ec_search=" . htmlentities( sanitize_text_field( $_GET['ec_search'] ), ENT_QUOTES );
		}

		if( isset( $_GET['group_id'] ) ){
			$url .= "&group_id=" . htmlentities( (int) $_GET['group_id'], ENT_QUOTES );
		}

		if( isset( $_GET['menuid'] ) ){
			$url .= "&menuid=" . htmlentities( (int) $_GET['menuid'], ENT_QUOTES );
		}

		if( isset( $_GET['submenuid'] ) ){
			$url .= "&submenuid=" . htmlentities( (int) $_GET['submenuid'], ENT_QUOTES );
		}

		if( isset( $_GET['subsubmenuid'] ) ){
			$url .= "&subsubmenuid=" . htmlentities( (int) $_GET['subsubmenuid'], ENT_QUOTES );
		}

		if( isset( $_GET['filternum'] ) ){
			$url .= "&filternum=" . htmlentities( (int) $_GET['filternum'], ENT_QUOTES );
		}

		if( isset( $_GET['perpage'] ) ){
			$url .= "&perpage=" . htmlentities( (int) $_GET['perpage'], ENT_QUOTES );
		}

		if ( isset( $_GET['filter_option'] ) ) {
			$filter_data_raw = explode( ',', sanitize_text_field( $_GET['filter_option'] ) );
			$filters = array( );
			for ( $filter_i=0; $filter_i<count( $filter_data_raw ); $filter_i++ ) {
				$filters[] = (int) $filter_data_raw[$filter_i];
			}
			$url .= "&filter_option=" . htmlentities( implode( ',', $filters ), ENT_QUOTES );
		}

		return $url;
	}
}
