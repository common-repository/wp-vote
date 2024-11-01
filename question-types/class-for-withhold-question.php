<?php

namespace WP_Vote;

if ( ! defined( 'WPINC' ) ) {
	die;
}


class For_Withhold_Question extends Abstract_Question_Object {

	/**
	 * Class
	 */
	protected static $slug;
	protected static $label;
	protected static $fields;
	protected static $answers;

	public static function init() {

		self::$slug    = 'for-withhold';
		self::$label   = __( 'For/Withhold', 'wp-vote' );
		self::$fields  = array( 'question_title', 'question_description' );
		self::$answers = apply_filters( 'wp-vote_answer_options_' . self::$slug, array(
			'for'      => __( 'For', 'wp-vote' ),
			'withhold' => __( 'Withhold', 'wp-vote' ),
		) );

		parent::init();

	}

	public static function render_meta_fields() {


	}


	/**
	 * Instance
	 */
	protected $data;

	public function __construct( $args ) {

	}

}
