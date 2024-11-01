<?php

namespace WP_Vote;

//use WP_Vote_Orig\Import;

if ( ! defined( 'WPINC' ) ) {
	die;
}


/**
 * Class Dual_Split_Name_Rep_Organization_Voter
 * @package WP_Vote
 */
class Dual_Split_Name_Rep_Organization_Voter extends Abstract_Voter_Object {

	/**
	 * Class
	 */
	protected static $slug;
	/**
	 * @var
	 */
	protected static $label;
	/**
	 * @var array
	 */
	protected static $fields = array(
//		'member_name' => array( 'required' => true ),
		'member_id'        => array( 'required' => true ),
		'rep_1_first_name' => array( 'required' => true ),
		'rep_1_last_name'  => array( 'required' => true ),
		'rep_1_email'      => array( 'required' => true, 'email' => true ),
		'rep_2_first_name' => array( 'required' => true ),
		'rep_2_last_name'  => array( 'required' => true ),
		'rep_2_email'      => array( 'required' => true, 'email' => true ),
	);

	/**
	 *
	 */
	public static function init() {
		self::$slug  = 'dual-split-name-rep-organization-voter';
		self::$label = __( 'Dual Rep Organization with split names', 'wp-vote' );

		parent::init();
	}

	/**
	 *
	 */
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
			'name'       => __( 'Org/Member ID', 'wp-vote' ),
			'desc'       => '',
			'id'         => self::get_prefix( 'member_id' ),
			'type'       => 'text',
			'show_names' => true, // Show field names on the left
			'attributes' => array(
				'required' => 'required',
			),
		) );

		$cmb->add_field( array(
			'name'       => __( 'Rep 1: First Name', 'wp-vote' ),
			'desc'       => '',
			'id'         => self::get_prefix( 'rep_1_first_name' ),
			'type'       => 'text',
			'show_names' => true, // Show field names on the left
			'attributes' => array(
				'required' => 'required',
			),
		) );

		$cmb->add_field( array(
			'name'       => __( 'Rep 1: Last Name', 'wp-vote' ),
			'desc'       => '',
			'id'         => self::get_prefix( 'rep_1_last_name' ),
			'type'       => 'text',
			'show_names' => true, // Show field names on the left
			'attributes' => array(
				'required' => 'required',
			),
		) );

		$cmb->add_field( array(
			'name'       => __( 'Rep 1: Email', 'wp-vote' ),
			'desc'       => '',
			'id'         => self::get_prefix( 'rep_1_email' ),
			'type'       => 'text_email',
			'show_names' => true, // Show field names on the left
			'attributes' => array(
				'required' => 'required',
			),
			'sanitization_cb' =>  array( __CLASS__, 'cmb2_sanitize_text_email_callback' ),
		) );

		$cmb->add_field( array(
			'name'       => __( 'Rep 2: First Name', 'wp-vote' ),
			'desc'       => '',
			'id'         => self::get_prefix( 'rep_2_first_name' ),
			'type'       => 'text',
			'show_names' => true, // Show field names on the left
			'attributes' => array(
				'required' => 'required',
			),
		) );

		$cmb->add_field( array(
			'name'       => __( 'Rep 2: Last Name', 'wp-vote' ),
			'desc'       => '',
			'id'         => self::get_prefix( 'rep_2_last_name' ),
			'type'       => 'text',
			'show_names' => true, // Show field names on the left
			'attributes' => array(
				'required' => 'required',
			),
		) );

		$cmb->add_field( array(
			'name'       => __( 'Rep 2: Email', 'wp-vote' ),
			'desc'       => '',
			'id'         => self::get_prefix( 'rep_2_email' ),
			'type'       => 'text_email',
			'show_names' => true, // Show field names on the left
			'attributes' => array(
				'required' => 'required',
			),
			'sanitization_cb' =>  array( __CLASS__, 'cmb2_sanitize_text_email_callback' ),
		) );

	}

	/**
	 * @param $text
	 *
	 * @return string
	 */
	public static function enter_title_here_hook( $text ) {
		$screen = get_current_screen();
		if ( in_array( $screen->id, array( Voter::get_post_type() ) ) && Voter::get_voter_type() == self::$slug ) {
			$text = __( 'Organization Name', 'wp-vote' );
		}

		return $text;

	}


	/**
	 * Instance
	 */
	public function __construct( $args ) {

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
						'email'          => get_post_meta( $this->post->ID, self::get_prefix( 'rep_1_email' ), true ),
						'rep_first_name' => get_post_meta( $this->post->ID, self::get_prefix( 'rep_1_first_name' ), true ),
						'rep_last_name'  => get_post_meta( $this->post->ID, self::get_prefix( 'rep_1_last_name' ), true ),
					),
					array(
						'email'          => get_post_meta( $this->post->ID, self::get_prefix( 'rep_2_email' ), true ),
						'rep_first_name' => get_post_meta( $this->post->ID, self::get_prefix( 'rep_2_first_name' ), true ),
						'rep_last_name'  => get_post_meta( $this->post->ID, self::get_prefix( 'rep_2_last_name' ), true ),
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


	/**
	 * @return array
	 */
	public function get_meta_for_ballot() {
		$ballot_meta = array(
			'ID'            => $this->post->ID,
			'title'         => $this->post->post_title,
			'voter_type'    => get_post_meta( $this->post->ID, Voter::get_prefix( 'voter_type' ), true ),
			'reps'          => array(
				array(
					'email'          => get_post_meta( $this->post->ID, self::get_prefix( 'rep_1_email' ), true ),
					'rep_first_name' => get_post_meta( $this->post->ID, self::get_prefix( 'rep_1_first_name' ), true ),
					'rep_last_name'  => get_post_meta( $this->post->ID, self::get_prefix( 'rep_1_last_name' ), true ),
				),
				array(
					'email'          => get_post_meta( $this->post->ID, self::get_prefix( 'rep_2_email' ), true ),
					'rep_first_name' => get_post_meta( $this->post->ID, self::get_prefix( 'rep_2_first_name' ), true ),
					'rep_last_name'  => get_post_meta( $this->post->ID, self::get_prefix( 'rep_2_last_name' ), true ),
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
		if ( false === $rep_id ) {
			return false;
		}

		return trim( $this->reps[ $rep_id ]['rep_first_name'] . ' ' . $this->reps[ $rep_id ]['rep_last_name'] );
	}

	private static $used_email = array();
	public function cmb2_sanitize_text_email_callback( $value, $object = null ) {

		$args = array(
			'post_type'  => Voter::get_post_type(),
			'meta_query' => array(
				'relation' => 'OR',
				array(
					'key'     => self::get_prefix( 'rep_1_email' ),
					'value'   => $value,
				),
				array(
					'key'     => self::get_prefix( 'rep_2_email' ),
					'value'   => $value,
				),
			),
		);
		$query = new \WP_Query( $args );
		$posts = $query->posts;

		if( 1 >= count( $posts ) && ! in_array( $value, self::$used_email, true ) ) {
			if( empty( $posts ) || $posts[0]->ID === get_the_ID() ){

				self::$used_email[] = $value;
				return $value;
			}
		}

		Admin_Notices::display_error( __( 'Duplicate Email Address used', 'wp-vote' ) );

		return '';
	}
}
