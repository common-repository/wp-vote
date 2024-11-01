<?php

namespace WP_Vote;

if ( ! defined( 'WPINC' ) ) {
	die;
}

interface Voter_Object_Interface {

	public static function init();

	// Admin UI
	public static function render_meta_fields();

	// Actions
	public function send_email_notification( $ballot_id, $ballot_status );

}

abstract class Abstract_Voter_Object implements Voter_Object_Interface {

	/**
	 * Class variables and static functions
	 */

	/**
	 * @var string Unique identifier for this voter, lowercase letters and hyphens only
	 */
	protected static $slug;

	/**
	 * @var string Human-readable label for use in dropdown fields and labels, supports translation
	 */
	protected static $label;

	/**
	 * @var string Set automatically via get_called_class() when voter type is registered
	 */
	protected static $class;

	/**
	 * @var array Define which fields are part of this voter. Used during import.
	 */
	protected static $fields;
	/**
	 * @var array Define which fields are required. Used during import.
	 */
//	protected static $required_fields;

	/**
	 * Returns the slug for the voter type
	 * @return string
	 */
	public static function get_slug() {
		return static::$slug;
	}

	/**
	 * Returns the slug for the voter type
	 * @return array
	 */
	public static function get_fields() {
		return static::$fields;
	}

	/**
	 * Create compound slugs by combining the voter type slug with a provided string
	 *
	 * @param string $append Provided string to append to voter type slug (run through sanitize_title)
	 *
	 * @return string
	 */
	public static function get_prefix( $append = '' ) {
		return static::get_slug() . '_' . sanitize_title( $append );
	}

	/**
	 * Same as get_prefix( $append ) with the addition of a leading '_'
	 * Used for creating hidden post meta that won't show up in the Custom Fields meta box
	 *
	 * @param string $append
	 *
	 * @return string
	 */
	public static function _get_prefix( $append = '' ) {
		return '_' . get_prefix( $append );
	}

	/**
	 * Initializes the voter type
	 * @return bool
	 */
	public static function init() {
		if ( empty( static::$slug ) || empty( static::$label ) ) {
			trigger_error(
				sprintf( __( 'self::$slug and self::$label must be set in %s::init() before calling parent::init() to initialize WP Vote voter type.', 'wp-vote' ), get_called_class() ),
				E_USER_WARNING
			);

			return false;
		}

		static::$class = get_called_class();

//		if ( get_called_class() instanceof Abstract_Voter_Object ) {
//
//		}
		add_filter( 'wp-vote_register_voter_types', array(
			get_called_class(),
			'register_voter_type_hook'
		) );
		add_filter( 'enter_title_here', array( get_called_class(), 'enter_title_here_hook' ) );

		return true;
	}

	/**
	 * Hook for registering the voter type
	 *
	 * @param array $voter_types
	 *
	 * @return array
	 */
	public static function register_voter_type_hook( $voter_types ) {

		if ( ! empty( static::$slug ) ) {
			$voter_types[ static::$slug ] = array(
				'class' => get_called_class(),
				'label' => static::$label,
			);
		}

		return $voter_types;
	}


	public static function import_voter( $voter_type, $header, $data ) {

		$voter_types = Voter::get_voter_types();

		if ( ! self::validate_header( $voter_type, $header ) ) {
			return false;
		};

		$postarr = array(
			'ID'           => $data[ array_search( 'id', $header ) ],
			'post_title'   => $data[ array_search( 'title', $header ) ],
			'post_content' => '',
			'post_type'    => Voter::get_post_type(),
			'post_status'  => 'publish',
		);

		$voter_meta = self::filter_import_data( $voter_type, $header, $data );

		if ( ! self::validate_required_fields( $voter_meta ) ) {
			return false;
		}

		// Prefix all our meta
		$prefixed_meta = array();
		foreach ( $voter_meta as $meta_key => $meta_value ) {
			$prefixed_meta[ call_user_func( array(
				$voter_types[ $voter_type ]['class'],
				'get_prefix'
			), $meta_key ) ] = $meta_value;
		}

		$prefixed_meta['wp-vote-voter_voter_type'] = $voter_type;

		if ( ! empty( $prefixed_meta ) ) {
			$postarr['meta_input'] = $prefixed_meta;
		}


		$existing_post = get_post( $postarr['ID'] );
		if ( ! empty( $postarr['ID'] ) && $existing_post && $existing_post->post_type == Voter::get_post_type() ) {
			$post_id = wp_update_post( $postarr );
		} else {
			unset( $postarr['ID'] );
			$post_id = wp_insert_post( $postarr );
		}
		unset( $postarr );

		return $post_id;

	}

	public static function validate_header( $voter_type, $header ) {

		$voter_types = Voter::get_voter_types();

		$fields = call_user_func( array( $voter_types[ $voter_type ]['class'], 'get_fields' ) );

		$fields = array_merge(
			array( 'id', 'title' ),
			array_keys( $fields )
		);

		$diff = array_diff( $fields, $header );

		return empty( $diff );

	}

	public static function filter_import_data( $voter_type, $header, $data ) {

		$voter_types = Voter::get_voter_types();

		$non_meta_keys = array(
			'id',
			'title',
		);

		$combined_data = array_combine( $header, $data );

		foreach ( $non_meta_keys as $key_to_remove ) {
			unset( $combined_data[ $key_to_remove ] );
		}

		return $combined_data;

	}

	public static function validate_required_fields( $voter_meta ) {

		if ( is_array( static::$fields ) ) {
			$required_fields = array_filter( static::$fields, function ( $field ) {
				if ( isset( $field['required'] ) ) {
					return $field['required'];
				}

				return false;
			} );
			foreach ( array_keys( $required_fields ) as $required_field ) {
				if ( empty( $voter_meta[ $required_field ] ) ) {
					return false;
				}
			}
		}

		return true;
	}

	public static function get_details_for_ballot() {

		$format = array(
			'ID'    => 'ID',
			'title' => 'post_title',
			'reps'  => array(
				'rep_email' => array(
					'email' => 'email',
					'...'   => '...',
				),
			),
		);
	}


	public static function enter_title_here_hook( $text ) {
		$screen = get_current_screen();
		if ( in_array( $screen->id, array( Voter::get_post_type() ) ) ) {
			return $text;
		}
	}


	/**
	 * Instance variables and methods
	 */
	protected $data;

	protected $ID;

	protected $title;

	protected $reps;

	protected $post;

	protected $meta;

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
				$this->reps  = array();

			}
		} else {
			// Build the voter object from voter meta stored in ballot ($ID, $title and $reps)
			$this->ID    = $args['ID'];
			$this->title = $args['title'];
			$this->reps  = $args['reps'];
		}

	}

	public function get_meta_for_ballot() {
		return null;
	}

	public function send_email_notification( $ballot_id, $ballot_status ) {

		$return = true;

		foreach ( $this->reps as $rep_index => $rep ) {
			$success   = Voter::email_representative( $this->ID, $rep_index, $rep, $ballot_id, $ballot_status );
			if ( ! $success ) {
				$return = false;
			}
		}

		return $return;

	}


	/**
	 * Control how we identify a representative (name, email, ...?)
	 */
	public function get_rep_name( $rep_id = false ) {
		return false;
	}

	/**
	 * Control how we identify a representative (name, email, ...?)
	 */
	public function get_title() {
		return $this->title;
	}


	public function cmb2_sanitize_text_email_callback( $value, $object = null ) {


	$posts = get_posts( array(
			'meta_key'         => $object['id'],
			'meta_value'       => $value,
			'post_type'        => Voter::get_post_type(),
		));

		if( 1 >= count( $posts ) ){
			if( $posts[0]->ID === get_the_ID() || empty( $posts ) ){
				return $value;
			}
		}

		Admin_Notices::display_error( __( 'Duplicate Email Address used', 'wp-vote' ) );

		return '';
	}


}