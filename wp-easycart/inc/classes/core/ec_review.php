<?php
class ec_review {
	public $review_id;
	public $product_id;
	public $approved;
	public $title;
	public $description;
	public $rating;
	public $review_date;
	public $reviewer_name;

	function __construct( $review_row ) {
		$this->review_id = $review_row->review_id;
		$this->approved = $review_row->approved;
		$this->title = $review_row->title;
		$this->description = $review_row->description;
		$this->rating = $review_row->rating;
		$this->review_date = $review_row->review_date;
		$this->reviewer_name = wp_easycart_language()->get_text( 'customer_review', 'product_details_review_anonymous_reviewer' );
		if ( isset( $review_row->reviewer_name ) && '' != $review_row->reviewer_name ) {
			$this->reviewer_name = $review_row->reviewer_name;
		} else if ( isset( $review_row->first_name ) && isset( $review_row->last_name ) ) {
			$this->reviewer_name = $review_row->first_name . ' ' . $review_row->last_name;
		}
	}

	public function display_review_title() {
		echo esc_attr( htmlspecialchars( $this->title, ENT_QUOTES ) );
	}
	
	public function display_review_stars( $is_elementor = false ) {
		for ( $i = 0; $i < $this->rating; $i++ ) {
			$this->display_star_on( $is_elementor );
		}
		for ( $i = $this->rating; $i < 5; $i++ ) {
			$this->display_star_off( $is_elementor );
		}
	}

	private function display_star_on( $is_elementor = false ){
		if ( $is_elementor ) {
			echo '<div class="ec_product_star_on_ele"></div>';
		} else {
			echo '<div class="ec_product_star_on"></div>';
		}
	}

	private function display_star_off( $is_elementor = false ) {
		if ( $is_elementor ) {
			echo '<div class="ec_product_star_off_ele"></div>';
		} else {
			echo '<div class="ec_product_star_off"></div>';
		}
	}

	public function display_review_date( $date_format ) {
		echo esc_attr( date( $date_format, strtotime( $this->review_date ) ) );
	}

	public function display_review_description(){
		echo esc_attr( nl2br( htmlspecialchars( $this->description, ENT_QUOTES ) ) );
	}
}
