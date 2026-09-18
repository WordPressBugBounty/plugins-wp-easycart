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
	public $verified = false;      // bought this product ( ec_reviews )
	public $reply_text = '';       // public store reply ( PRO )
	public $reply_date = null;

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
		if ( isset( $review_row->verified ) ) {
			$this->verified = (bool) $review_row->verified;
			$this->reply_text = isset( $review_row->reply_text ) ? (string) $review_row->reply_text : '';
			$this->reply_date = isset( $review_row->reply_date ) ? $review_row->reply_date : null;
		} else if ( class_exists( 'ec_reviews' ) ) {
			$x = ec_reviews::extras( $this->review_id );
			$this->verified = $x['verified'];
			$this->reply_text = $x['reply'];
			$this->reply_date = $x['reply_date'];
		}
	}

	public function has_reply() {
		return '' !== trim( (string) $this->reply_text );
	}

	/* "Verified buyer" chip, or nothing. */
	public function display_verified_badge() {
		if ( ! $this->verified ) {
			return;
		}
		echo '<span class="ec_review_verified" title="' . esc_attr__( 'This reviewer purchased this product', 'wp-easycart' ) . '">&#10003; ' . esc_html( apply_filters( 'wp_easycart_review_verified_label', __( 'Verified buyer', 'wp-easycart' ) ) ) . '</span>';
	}

	/* The store's public reply block ( PRO ), or nothing. */
	public function display_reply( $date_format = 'F j, Y' ) {
		if ( ! $this->has_reply() ) {
			return;
		}
		$who = class_exists( 'ec_reviews' ) ? ec_reviews::reply_signature() : get_bloginfo( 'name' );
		echo '<div class="ec_review_reply">';
		echo '<div class="ec_review_reply_meta"><span class="ec_review_reply_who">' . esc_html( sprintf( apply_filters( 'wp_easycart_review_reply_label', __( 'Response from %s', 'wp-easycart' ) ), $who ) ) . '</span>';
		if ( $this->reply_date ) {
			echo ' <span class="ec_review_reply_date">' . esc_html( date( $date_format, strtotime( $this->reply_date ) ) ) . '</span>';
		}
		echo '</div><div class="ec_review_reply_text">' . wp_kses( nl2br( wp_unslash( $this->reply_text ) ), array( 'br' => array(), 'a' => array( 'href' => array() ), 'b' => array(), 'strong' => array(), 'i' => array(), 'em' => array() ) ) . '</div></div>';
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
