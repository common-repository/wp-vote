<?php

namespace WP_Vote;

//use WP_Vote_Orig\Import;

if ( ! defined( 'WPINC' ) ) {
	die;
}


/**
 * Class Dual_Rep_Organization_Voter
 * @package WP_Vote
 */
class Dual_Rep_Organization_Voter extends Abstract_Voter_Object {

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
		'member_id'   => array( 'required' => true ),
		'rep_1_name'  => array( 'required' => true ),
		'rep_1_email' => array( 'required' => true, 'email' => true ),
		'rep_2_name'  => array( 'required' => true ),
		'rep_2_email' => array( 'required' => true, 'email' => true ),
	);

	/**
	 *
	 */
	public static function init() {
		self::$slug  = 'dual-rep-organization-voter';
		self::$label = __( 'Dual Rep Organization', 'wp-vote' );

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
			'name'       => __( 'Rep 1: Name', 'wp-vote' ),
			'desc'       => '',
			'id'         => self::get_prefix( 'rep_1_name' ),
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
		) );

		$cmb->add_field( array(
			'name'       => __( 'Rep 2: Name', 'wp-vote' ),
			'desc'       => '',
			'id'         => self::get_prefix( 'rep_2_name' ),
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
	 * Override standard import for this Voter Type
	 *
	 * @param $header
	 * @param $data
	 *
	 * @return bool
	 */
//	public static function import_voter( $header, $data ) {
//
//		$postarr = array(
//			//'ID'           => $data[ array_search( 'ID', $header ) ],
//			'post_title'   => $data[ array_search( static::get_prefix( 'member_name' ), $header ) ],
//			'post_content' => '',
//			'post_type'    => Voter::get_post_type(),
//			'post_status'  => 'publish',
//		);
//
//		$voter_meta = self::filter_import_data( $header, $data );
//
//		$valid_fields = self::validate_required_fields( $voter_meta );
//
//		$valid_rep_1_email = is_email( $voter_meta[ self::get_prefix( 'rep_1_email' ) ] );
//		$valid_rep_2_email = is_email( $voter_meta[ self::get_prefix( 'rep_2_email' ) ] );
//
//		if ( ! empty( $voter_meta ) ) {
//			$postarr['meta_input'] = $voter_meta;
//			unset( $postarr[ static::get_prefix( 'member_name' ) ] );
//		}
//
//		$voter_args = array(
//			'post_type'              => Voter::get_post_type(),
//			'meta_key'               => self::get_prefix( 'member_id' ),
//			'meta_value'             => $data[ array_search( self::get_prefix( 'member_id' ), $header ) ],
//			// Performance Options
//			'posts_per_page'         => '1', // set to a reasonable limit
//			'no_found_rows'          => true, // useful when pagination is not needed
//			'update_post_term_cache' => false, // useful when taxonomy terms will not be utilized
//			'fields'                 => 'ids', // useful when only the post IDs are needed
//		);
//
//		$voter_query = new \WP_Query( $voter_args );
//
//		if ( $voter_query->have_posts() ) {
//			$postarr['ID'] = $voter_query->posts[0];
//			$post_id       = wp_update_post( $postarr );
//		} else {
//			unset( $postarr['ID'] );
//			$post_id = wp_insert_post( $postarr );
//		}
//		unset( $postarr );
//		wp_reset_query();
//
//		$permalink = get_edit_post_link( $post_id );
//		if ( $valid_fields && $valid_rep_1_email && $valid_rep_2_email ) {
//			Import_Logger::notice( "{$voter_meta[self::get_prefix('member_id')]}: {$voter_meta[self::get_prefix('member_id')]} - Import Successful" );
//		} else {
//			if ( ! $valid_fields ) {
//				Import_Logger::warn( "<a href='$permalink' target='_blank'>{$voter_meta[self::get_prefix('member_id')]}: {$voter_meta[self::get_prefix('member_name')]} - Missing Required Fields</a>" );
//			}
//			if ( ! $valid_rep_1_email ) {
//				Import_Logger::warn( "<a href='$permalink' target='_blank'>{$voter_meta[self::get_prefix('member_id')]}: {$voter_meta[self::get_prefix('member_name')]} - Rep 1 Email Invalid</a>" );
//			}
//			if ( ! $valid_rep_2_email ) {
//				Import_Logger::warn( "<a href='$permalink' target='_blank'>{$voter_meta[self::get_prefix('member_id')]}: {$voter_meta[self::get_prefix('member_name')]} - Rep 2 Email Invalid</a>" );
//			}
//		}
//
//		return $post_id;
//
//	}

//	public static function filter_import_data( $header, $data ) {
//
//		$voter_types = Voter::get_voter_types();
//		if ( 1 === count( $voter_types ) ) {
//			$voter_type_values = array_values( $voter_types );
//			$voter_type        = array_shift( $voter_type_values );
//			$voter_type_keys   = array_keys( $voter_types );
//			$voter_type_key    = array_shift( $voter_type_keys );
//			$voter_class       = $voter_type['class'];
//		} else {
//			$voter_type_key = $data[ array_search( 'voter_type', $header ) ];
//			$voter_class    = $voter_types[ $voter_type_key ]['class'];
//		}
//
//
//		$filtered_data = array(
//			Voter::get_prefix( 'voter_type' ) => $voter_type_key,
//		);
//
//		foreach ( $header as $col_name ) {
//			if ( stristr( $col_name, static::get_prefix() ) ) {
//				$filtered_data[ $col_name ] = $data[ array_search( $col_name, $header ) ];
//			}
//		}
//
//		return $filtered_data;
//
//	}






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
						'email' => get_post_meta( $this->post->ID, self::get_prefix( 'rep_1_email' ), true ),
						'name'  => get_post_meta( $this->post->ID, self::get_prefix( 'rep_1_name' ), true ),
					),
					array(
						'email' => get_post_meta( $this->post->ID, self::get_prefix( 'rep_2_email' ), true ),
						'name'  => get_post_meta( $this->post->ID, self::get_prefix( 'rep_2_name' ), true ),
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
			'ID'         => $this->post->ID,
			'title'      => $this->post->post_title,
			'voter_type' => get_post_meta( $this->post->ID, Voter::get_prefix( 'voter_type' ), true ),
			'reps'       => array(
				array(
					'email' => get_post_meta( $this->post->ID, self::get_prefix( 'rep_1_email' ), true ),
					'name'  => get_post_meta( $this->post->ID, self::get_prefix( 'rep_1_name' ), true ),
				),
				array(
					'email' => get_post_meta( $this->post->ID, self::get_prefix( 'rep_2_email' ), true ),
					'name'  => get_post_meta( $this->post->ID, self::get_prefix( 'rep_2_name' ), true ),
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

		return $this->reps[ $rep_id ]['name'];
	}

}
