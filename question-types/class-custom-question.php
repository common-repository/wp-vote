<?php

namespace WP_Vote;

if ( ! defined( 'WPINC' ) ) {
	die;
}


class Custom_Question extends Abstract_Question_Object {

	/**
	 * Class
	 */
	protected static $slug;
	protected static $label;
	protected static $fields;
	protected static $answers;

	public static function init() {

		self::$slug    = 'custom';
		self::$label   = __( 'Custom', 'wp-vote' );
		self::$fields  = array( 'question_title', 'question_description', 'answers' );
		self::$answers = false;

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
