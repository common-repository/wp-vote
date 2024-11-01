<?php

namespace WP_Vote;

if ( ! defined( 'WPINC' ) ) {
	die;
}


class Basic_Individual_Voter extends Abstract_Voter_Object {

	/**
	 * Class
	 */
	protected static $slug;
	protected static $label;
	protected static $fields;

	public static function init() {
		self::$slug            = 'basic-individual-voter';
		self::$label           = __( 'Basic Individual', 'wp-vote' );
		self::$fields = array(
			'email_address' => array(
				'required' => true,
				'email' => true,
			),
		);
		parent::init();
	}

	public static function render_meta_fields() {

		$cmb = new_cmb2_box( array(
			'id'           => self::get_prefix( 'voter_details_metabox' ),
			'title'        => __( 'Voter Details', 'wp_vote' ),
			'object_types' => array( Voter::get_post_type() ), // Post type
			'context'      => 'normal',
			'priority'     => 'high',
			'show_names'   => true, // Show field names on the left
		) );

		$cmb->add_field( array(
			'name'       => __( 'Email Address', 'wp-vote' ),
			'desc'       => '',
			'id'         => self::get_prefix( 'email_address' ),
			'type'       => 'text_email',
			'show_names' => true, // Show field names on the left
			'attributes' => array(
				'required' => 'required',
			),
			'sanitization_cb' =>  array( __CLASS__, 'cmb2_sanitize_text_email_callback' ),
		) );
		$cmb->add_field( array(
			'name'       => __( 'First Name', 'wp-vote' ),
			'desc'       => '',
			'id'         => self::get_prefix( 'rep_first_name' ),
			'type'       => 'text',
			'show_names' => true, // Show field names on the left
			'attributes' => array(
				'required' => 'required',
			),
		) );
		$cmb->add_field( array(
			'name'       => __( 'Last Name', 'wp-vote' ),
			'desc'       => '',
			'id'         => self::get_prefix( 'rep_last_name' ),
			'type'       => 'text',
			'show_names' => true, // Show field names on the left
			'attributes' => array(
				'required' => 'required',
			),
		) );
	}

	public static function enter_title_here_hook( $text ) {
		$screen = get_current_screen();
		if ( in_array( $screen->id, array( Voter::get_post_type() ) ) && Voter::get_voter_type() == self::$slug ) {
			$text = __( 'Voter Name', 'wp-vote' );
		}

		return $text;
	}


	/**
	 * Instance
	 */
	public function __construct( $args ) {

		$this->data = $args;

		// Create Voter from existing post ID
		if ( ! is_array( $args ) ) {
			$voter_post = get_post( $args );
			if ( $voter_post ) {
				$this->ID    = $args;
				$this->post  = $voter_post;
				$this->meta  = get_post_meta( $args );
				$this->title = $this->post->post_title;
				$this->reps  = array(
					array(
						'email' => get_post_meta( $this->post->ID, self::get_prefix( 'email_address' ), true ),
					),
				);

			}
		} else {
			// Build the voter object from voter meta stored in ballot ($ID, $title and $reps)
			$this->ID    = $args['ID'];
			$this->title = $args['title'];
			$this->reps  = $args['reps'];
		}

	}

	public function get_meta_for_ballot() {
		$ballot_meta = array(
			'ID'         => $this->post->ID,
			'title'      => $this->post->post_title,
			'voter_type' => self::get_slug(),
			'reps'       => array(
				array(
					'name' => $this->post->post_title,
					'email' => get_post_meta( $this->post->ID, self::get_prefix( 'email_address' ), true ),
				),
			),
			'export_fields' => array(
				'member_id' => get_post_meta( $this->post->ID, self::get_prefix( 'member_id' ), true ),
			),
		);

		return $ballot_meta;
	}

	/**
	 * Control how we identify a representative (name, email, ...?)
	 */
	public function get_rep_name( $rep_id = false ) {
		return $this->reps[0]['name'];
	}


}
