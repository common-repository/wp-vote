<?php

namespace WP_Vote;

if ( ! defined( 'WPINC' ) ) {
	die;
}


class Open_Text_Question extends Abstract_Question_Object {

	/**
	 * Class
	 */
	protected static $slug;
	protected static $label;
	protected static $fields;
	protected static $answers;

	public static function init() {

		self::$slug    = 'open_text';
		self::$label   = __( 'Open Text', 'wp-vote' );
		self::$fields  = array( 'question_title', 'question_description' );
		self::$answers = false;

		parent::init();

	}

	public static function render_meta_fields() {

	}

	public static function get_questions() {

		return null;
	}


	/**
	 * Instance
	 */
	protected $data;

	public function __construct( $args ) {

	}

}
