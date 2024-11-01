<?php

namespace WP_Vote;

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Ballot extends Abstract_Post_Type {

	const SLUG = 'ballot';

	const POST_TYPE = 'wp-vote-ballot';

	const STATUS_NEW = 'new';

	const STATUS_DRAFT = 'draft';

	const STATUS_OPEN = 'open';

	const STATUS_CLOSED = 'closed';

	const STATUS_VOTED = 'voted';

	const STATUS_REMINDER = 'remind';

	public static $question_types;

	public static $cmb_questions;

	public static $cmb_test_email;

	public static $cmb_voters;

	public static $cmb_footer;

	public static $ballot_id;

	public static $status;

	public static $date_open;

	public static $date_closed;

	public static $salt;

	public static function plugins_loaded() {

		parent::plugins_loaded();

		add_action( 'init', array( __CLASS__, 'init' ) );
		add_action( 'cmb2_admin_init', array( __CLASS__, 'cmb2_admin_init_hook' ) );

		// Ballot Actions
		add_action( 'post_submitbox_minor_actions', array( __CLASS__, 'draft_message' ) );
		add_action( 'post_submitbox_start', array( __CLASS__, 'post_submitbox_start' ) );

		add_action( 'enter_title_here', array( __CLASS__, 'enter_title_here_hook' ) );

		// Save Post
		add_action( 'save_post', array( __CLASS__, 'save_ballot_maybe_change_status' ), 9999, 3 );

		add_filter( 'get_sample_permalink_html', array( __CLASS__, 'remove_permalink' ) );

		add_filter( 'post_updated_messages', array( __CLASS__, 'post_published_message' ) );

		add_action( 'wp', array( __CLASS__, 'sniff_submits' ) );

		add_filter( self::get_prefix( 'votes_to_be_saved' ), array(
			__CLASS__,
			'save_accepted_conditions'
		) );


		add_filter( 'post_row_actions', array( __CLASS__, 'post_row_actions' ), 10, 2 );

		add_filter( 'post_row_actions',  array( __CLASS__, 'clone_post_link' ), 10, 2 );
		add_action( 'admin_action_wp_vote_clone_post_as_draft',  array( __CLASS__, 'clone_post_as_draft' ) );


	}

	public static function init() {
		self::$question_types = apply_filters( 'wp-vote_register_question_types', self::$question_types );
		add_filter( 'gettext', array( __CLASS__, 'filter_published_on' ), 10000, 2 );
	}

	public static function register_custom_post_type() {
		$labels = array(
			'name'               => _x( 'Ballot', 'post type general name', 'wp_vote' ),
			'singular_name'      => _x( 'Ballot', 'post type singular name', 'wp_vote' ),
			'menu_name'          => _x( 'Ballots', 'admin menu', 'wp_vote' ),
			'name_admin_bar'     => _x( 'Ballot', 'add new on admin bar', 'wp_vote' ),
			'add_new'            => _x( 'Add New', 'Ballot', 'wp_vote' ),
			'add_new_item'       => __( 'Add New Ballot', 'wp_vote' ),
			'new_item'           => __( 'New Ballot', 'wp_vote' ),
			'edit_item'          => __( 'Edit Ballot', 'wp_vote' ),
			'view_item'          => __( 'View Ballot', 'wp_vote' ),
			'all_items'          => __( 'Ballots', 'wp_vote' ),
			'search_items'       => __( 'Search Ballots', 'wp_vote' ),
			'parent_item_colon'  => __( 'Parent Ballots:', 'wp_vote' ),
			'not_found'          => __( 'No ballots found.', 'wp_vote' ),
			'not_found_in_trash' => __( 'No ballots found in Trash.', 'wp_vote' ),
		);

		$current_user    = wp_get_current_user();
		$roles           = $current_user->roles;
		$allowed_roles   = Settings::get_option_value( 'allowed_roles' );
		$allowed_roles[] = 'administrator';

		$authorized = (bool) array_intersect( $roles, $allowed_roles );


		$args = array(
			'labels'             => $labels,
			'description'        => __( 'Description.', 'wp_vote' ),
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => $authorized,
			'show_in_menu'       => ( $authorized ) ? WP_Vote::get_plugin_name() : false,
			'query_var'          => true,
			'rewrite'            => array( 'slug' => self::get_slug() ),
			'capability_type'    => 'post',
			'has_archive'        => true,
			'hierarchical'       => false,
			'menu_position'      => 10,
			'supports'           => array( 'title', 'editor' ),
		);

		register_post_type( self::get_post_type(), $args );
	}


	public static function enter_title_here_hook( $text = null ) {
		return __( 'Enter ballot title here', 'wp-vote' );
	}

	public static function post_row_actions( $actions, $post ) {
		if ( self::get_post_type() === $post->post_type ) {
			if ( false !== strpos( $actions['view'], 'View' ) ) {
				unset( $actions['view'] );
			}
		}

		return $actions;
	}


	/*
     * Add the duplicate link to action list for post_row_actions
     */
	public static function clone_post_link( $actions, $post ) {
		if ( current_user_can('edit_posts') && self::get_post_type() === $post->post_type ) {
			$actions['clone'] = '<a href="' . wp_nonce_url('admin.php?action=wp_vote_clone_post_as_draft&post=' . $post->ID, basename(__FILE__), 'wp_vote_clone_nonce' ) . '" title="Clone this ballot" rel="permalink">Clone</a>';
		}
		return $actions;
	}


	/*
     * Function creates post duplicate as a draft and redirects then to the edit post screen
     */
	public static function clone_post_as_draft(){
		global $wpdb;
		if (! ( isset( $_GET['post']) || isset( $_POST['post'])  || ( isset($_REQUEST['action']) && 'wp_vote_clone_post_as_draft' == $_REQUEST['action'] ) ) ) {

		    wp_die('No post to duplicate has been supplied!');
		}

		/*
		 * Nonce verification
		 */
		if ( !isset( $_GET['wp_vote_clone_nonce'] ) || !wp_verify_nonce( $_GET['wp_vote_clone_nonce'], basename( __FILE__ ) ) ) {

		    return;
        }


		/*
		 * get the original post id
		 */
		$post_id = (isset($_GET['post']) ? absint( $_GET['post'] ) : absint( $_POST['post'] ) );
		/*
		 * and all the original post data then
		 */
		$post = get_post( $post_id );

		/*
		 * if you don't want current user to be the new post author,
		 * then change next couple of lines to this: $new_post_author = $post->post_author;
		 */
		$current_user = wp_get_current_user();
		$new_post_author = $current_user->ID;

		/*
		 * if post data exists, create the post duplicate
		 */
		if (isset( $post ) && $post != null) {

			/*
			 * new post data array
			 */
			$args = array(
				'comment_status' => $post->comment_status,
				'ping_status'    => $post->ping_status,
				'post_author'    => $new_post_author,
				'post_content'   => $post->post_content,
				'post_excerpt'   => $post->post_excerpt,
				'post_name'      => $post->post_name,
				'post_parent'    => $post->post_parent,
				'post_password'  => $post->post_password,
				'post_status'    => 'draft',
				'post_title'     => 'clone -' . $post->post_title,
				'post_type'      => $post->post_type,
				'to_ping'        => $post->to_ping,
				'menu_order'     => $post->menu_order
			);

			/*
			 * insert the post by wp_insert_post() function
			 */
			$new_post_id = wp_insert_post( $args );

			/*
			 * get all current post terms ad set them to the new post draft
			 */
			$taxonomies = get_object_taxonomies($post->post_type); // returns array of taxonomy names for post type, ex array("category", "post_tag");
			foreach ($taxonomies as $taxonomy) {
				$post_terms = wp_get_object_terms($post_id, $taxonomy, array('fields' => 'slugs'));
				wp_set_object_terms($new_post_id, $post_terms, $taxonomy, false);
			}

			/*
			 * duplicate all post meta just in two SQL queries
			 */
			$post_meta_infos = $wpdb->get_results("SELECT meta_key, meta_value FROM $wpdb->postmeta WHERE post_id=$post_id");

			if (count($post_meta_infos)!=0) {
				$sql_query = "INSERT INTO $wpdb->postmeta (post_id, meta_key, meta_value) ";
				foreach ($post_meta_infos as $meta_info) {
				    if ( ! in_array( $meta_info->meta_key , array( 'wp-vote-ballot_voters', 'wp-vote-ballot_date_open', 'wp-vote-ballot_date_closed', 'wp-vote-ballot_status', 'wp-vote-ballot_salt' ), true ) ) {
					    $meta_value = $meta_info->meta_value;
                        if( self::get_prefix( 'questions' ) === $meta_info->meta_key ) {
	                          $meta_value = self::stringify_questions_from_array( $meta_value );
                        }

						$meta_key = $meta_info->meta_key;
						if( $meta_key == '_wp_old_slug' ) continue;
						$meta_value = addslashes( $meta_value );
						$sql_query_sel[]= "SELECT $new_post_id, '$meta_key', '$meta_value'";
                    }
				}
				$sql_query.= implode(" UNION ALL ", $sql_query_sel );

				$wpdb->query($sql_query);
			}


			/*
			 * finally, redirect to the edit post screen for the new draft
			 */
			wp_redirect( admin_url( 'post.php?action=edit&post=' . $new_post_id ) );
			exit;
		} else {
			wp_die('Post creation failed, could not find original post: ' . $post_id);
		}
	}


	private static function stringify_questions_from_array( $questions ){
		$questions = unserialize( $questions );
		foreach ( $questions  as $question_index => $question ) {

			$question_type  = $question[ self::get_prefix( 'question_type' ) ];
			$question_types = self::get_question_types();

            if( is_array( $question[ self::get_prefix( 'question_answers' ) ] ) ) {
	            if ( false === $question_types[ $question_type ]['answers'] ) {
		            $answers = '';
		            foreach ( $question[ self::get_prefix( 'question_answers' ) ] as $answer_option_index => $answer ){

			            if( $answer_option_index === sanitize_title( $answer['label'] ) ) {
				            $answers .= $answer['label'] . PHP_EOL;
			            }else{
				            $answers .= $answer_option_index . ' : ' . $answer['label'] . PHP_EOL;
			            }

		            }

		            $questions[ $question_index ][ self::get_prefix( 'question_answers' ) ] = $answers;
	            } else {

		            unset( $questions[ $question_index ][ self::get_prefix( 'question_answers' ) ] );
	            }
            }

		}

	    return serialize( $questions );
    }

	public static function cmb2_admin_init_hook() {

		switch ( Ballot::get_ballot_status() ) {
			case self::STATUS_OPEN:
				self::$cmb_questions = new_cmb2_box( array(
					'id'           => self::get_prefix( 'question_metabox' ),
					'title'        => __( 'Results', 'wp_vote' ),
					'object_types' => array( self::get_post_type() ), // Post type
					'context'      => 'normal',
					'priority'     => 'high',
					'show_names'   => true, // Show field names on the left
				) );
				self::create_voter_meta_box();
				self::setup_cmb2_open_ballot_fields();
				do_action( WP_Vote::get_prefix( 'ballot_open_admin_meta' ) );
				break;
			case self::STATUS_CLOSED:
				self::$cmb_questions = new_cmb2_box( array(
					'id'           => self::get_prefix( 'question_metabox' ),
					'title'        => __( 'Results', 'wp_vote' ),
					'object_types' => array( self::get_post_type() ), // Post type
					'context'      => 'normal',
					'priority'     => 'high',
					'show_names'   => true, // Show field names on the left
				) );
				self::create_voter_meta_box();
				self::setup_cmb2_closed_ballot_fields();
				do_action( WP_Vote::get_prefix( 'ballot_closed_admin_meta' ) );
				break;
            default:
	            self::create_voter_meta_box();

	            self::$cmb_test_email = new_cmb2_box( array(
		            'id'           => self::get_prefix( 'test_email_metabox' ),
		            'title'        => __( 'Test Ballot Email', 'wp_vote' ),
		            'object_types' => array( self::get_post_type() ), // Post type
		            'context'      => 'side',
		            'priority'     => 'low',
		            'show_names'   => true, // Show field names on the left
	            ) );

                self::$cmb_questions = new_cmb2_box( array(
					'id'           => self::get_prefix( 'question_metabox' ),
					'title'        => __( 'Questions', 'wp_vote' ),
					'object_types' => array( self::get_post_type() ), // Post type
					'context'      => 'normal',
					'priority'     => 'high',
					'show_names'   => true, // Show field names on the left
				) );
				self::$cmb_footer    = new_cmb2_box( array(
					'id'           => self::get_prefix( 'footer_metabox' ),
					'title'        => __( 'Footer Content', 'wp_vote' ),
					'object_types' => array( self::get_post_type() ), // Post type
					'context'      => 'normal',
					'priority'     => 'high',
					'show_names'   => true, // Show field names on the left
				) );

				self::setup_cmb2_draft_ballot_fields();
				do_action( WP_Vote::get_prefix( 'ballot_new_admin_meta' ) );
		}


	}

	public static function setup_cmb2_open_ballot_fields() {

		// Voters
		self::$cmb_voters->add_field( array(
			'name'             => 'Ballot Voters',
			'desc'             => '',
			'id'               => self::get_prefix( '_voters' ),
			'type'             => 'XX_open_ballot_voters',
			'show_option_none' => false,
			'default'          => 'custom',
			'show_names'       => false, // Show field names on the left
			'after_field'      => array( __CLASS__, 'display_open_ballot_voters' ),
		) );

		// Questions
		self::$cmb_questions->add_field( array(
			'name'        => 'Ballot Questions',
			'desc'        => '',
			'id'          => self::get_prefix( '_questions' ),
			'type'        => 'XX_open_ballot_questions',
			'show_names'  => false, // Show field names on the left
			'after_field' => array( __CLASS__, 'display_open_ballot_questions' ),
		) );


	}

	public static function setup_cmb2_closed_ballot_fields() {

		// Voters
		self::$cmb_voters->add_field( array(
			'name'             => 'Ballot Voters',
			'desc'             => '',
			'id'               => self::get_prefix( '_voters' ),
			'type'             => 'XX_closed_ballot_voters',
			'show_option_none' => false,
			'default'          => 'custom',
			'show_names'       => false, // Show field names on the left
			'after_field'      => array( __CLASS__, 'display_open_ballot_voters' ),
		) );

		// Questions
		self::$cmb_questions->add_field( array(
			'name'             => 'Ballot Questions',
			'desc'             => '',
			'id'               => self::get_prefix( '_questions' ),
			'type'             => 'XX_closed_ballot_questions',
			'show_option_none' => false,
			'default'          => 'custom',
			'show_names'       => false, // Show field names on the left
			'after_field'      => array( __CLASS__, 'display_open_ballot_questions' ),
		) );


	}

	public static function setup_cmb2_draft_ballot_fields() {

		$voter_types        = Voter::get_voter_types_for_select();
		$voter_types_keys   = array_keys( $voter_types );
		$default_voter_type = array_shift( $voter_types_keys );

		// Voters
		self::$cmb_voters->add_field( array(
			'name'             => 'Voter Type',
			'desc'             => 'Select the type of voter for this ballot.',
			'id'               => self::get_prefix( 'voter_type' ),
			'type'             => 'select',
			'show_option_none' => false,
			'default'          => array_shift( $voter_types_keys ),
			'options'          => Voter::get_voter_types_for_select(),
		) );

		self::$cmb_voters->add_field( array(
			'name'       => __( 'Eligible Voters', 'wp_vote' ),
			'desc'       => __( 'Drag voters from the left column to the right column to attach them to this ballot.', 'wp_vote' ),
			'id'         => self::get_prefix( 'eligible' ),
			'type'       => 'custom_attached_posts',
			'show_names' => false, // Show field names on the left
			'options'    => array(
				'show_thumbnails' => false,
				'hide_selected'   => true,
				'filter_boxes'    => true,
				'query_args'      => array(
					'posts_per_page' => - 1,
					'post_type'      => Voter::get_post_type(),
				),
			),
		) );

		self::$cmb_test_email->add_field( array(
			'name'             => esc_html__( 'Select test Voter', 'wp-vote' ),
			'id'               => self::get_prefix( 'test_email' ),
			'type'             => 'select',
			'show_option_none' => false,
			'options' => Voter::test_email_options(),
			'attributes'       => array(
				'class' => 'test-email',
			),
			'after_field'      => array( __CLASS__, 'show_test_ballot_button' ),
		) );



		// Questions
		$group_field_id = self::$cmb_questions->add_field( array(
			'id'      => self::get_prefix( 'questions' ),
			'type'    => 'group',
			'options' => array(
				'group_title'   => __( 'Question {#}', 'wp_vote' ),
				// since version 1.1.4, {#} gets replaced by row number
				'add_button'    => __( 'Add Another Question', 'wp_vote' ),
				'remove_button' => __( 'Remove Question', 'wp_vote' ),
				'sortable'      => true,
				// beta
				// self::STATUS_CLOSED     => true, // true to have the groups closed by default
				'attributes'    => array(
					'class' => 'question-type',
				),
			),
		) );

		self::$cmb_questions->add_group_field( $group_field_id, array(
			'name'             => esc_html__( 'Question Type', 'wp-vote' ),
			'id'               => self::get_prefix( 'question_type' ),
			'type'             => 'select',
			'show_option_none' => false,
			'options'          => self::get_question_types_for_select(),
			'attributes'       => array(
				'class' => 'question-type',
			),
		) );

		self::$cmb_questions->add_group_field( $group_field_id, array(
			'name'       => esc_html__( 'Question', 'wp-vote' ),
			'id'         => self::get_prefix( 'question_title' ),
			'type'       => 'text',
			'before_row' => array( __CLASS__, 'before_question_title_row' ),
			'after_row'  => array( __CLASS__, 'after_row' ),
		) );

		self::$cmb_questions->add_group_field( $group_field_id, array(
			'name'       => esc_html__( 'Description', 'wp-vote' ),
			'id'         => self::get_prefix( 'question_description' ),
			'type'       => 'wysiwyg',
			'before_row' => array( __CLASS__, 'before_question_description_row' ),
			'after_row'  => array( __CLASS__, 'after_row' ),
			'options'    => array(
				'wpautop'       => true, // use wpautop?
				'media_buttons' => true, // show insert/upload button(s)
				'textarea_rows' => 5, // rows="..."
			),
		) );

		self::$cmb_questions->add_group_field( $group_field_id, array(
			'name'       => esc_html__( 'Answers', 'wp-vote' ),
			'desc'       => esc_html__( 'One option per line', 'wp-vote' ),
			'id'         => self::get_prefix( 'question_answers' ),
			'type'       => 'textarea',
			'before_row' => array( __CLASS__, 'before_answer_row' ),
			'after_row'  => array( __CLASS__, 'after_row' ),
			'attributes' => array(
				//'class' => 'hide',
				'rows' => 5,
			),
		) );


		self::$cmb_questions->add_field( array(
			'name'             => esc_html__( 'Ballot Save', 'wp-vote' ),
			'desc'             => '',
			'id'               => self::get_prefix( 'actions' ),
			'type'             => 'XX_show_save_ballot_button',
			'show_option_none' => false,
			'default'          => 'custom',
			'show_names'       => false, // Show field names on the left
			'after_field'      => array( __CLASS__, 'show_save_ballot_button' ),
		) );

		// Footer
		self::$cmb_footer->add_field( array(
			'name'       => esc_html__( 'Closing Comments', 'wp-vote' ),
			'desc'       => '',
			'id'         => self::get_prefix( 'footer' ),
			'type'       => 'wysiwyg',
			'show_names' => true, // Show field names on the left
			'options'    => array(
				'wpautop'       => true, // use wpautop?
				'media_buttons' => true, // show insert/upload button(s)
				'textarea_rows' => 5, // rows="..."
			),
		) );

		self::$cmb_footer->add_field( array(
			'name'        => esc_html__( 'Required Conditions', 'wp-vote' ),
			'desc'        => esc_html__( 'Will be linked to a checkbox that the user has to check before submitting their ballot', 'wp-vote' ),
			'id'          => self::get_prefix( 'conditions' ),
			'type'        => 'wysiwyg',
			'show_names'  => true, // Show field names on the left
			'options'     => array(
				'wpautop'       => true, // use wpautop?
				'media_buttons' => true, // show insert/upload button(s)
				'textarea_rows' => 5, // rows="..."
			),
			'after_field' => array( __CLASS__, 'show_template_shortcodes' ),
		) );
		self::$cmb_footer->add_field( array(
			'name' => esc_html__( 'Require signature for this ballot', 'wp-vote' ),
			'id'   => self::get_prefix( 'add_signature' ),
			'type' => 'checkbox',
		) );
		self::$cmb_footer->add_field( array(
			'name'       => esc_html__( 'Signature Text', 'wp-vote' ),
			'id'         => self::get_prefix( 'add_signature_text' ),
			'type'       => 'text',
			'attributes' => array(
				'style'       => 'width:100%',
				'placeholder' => self::get_add_signature_text_default(),
			),
		) );

		self::$cmb_footer->add_field( array(
			'name'             => esc_html__( 'Ballot Save', 'wp-vote' ),
			'desc'             => '',
			'id'               => self::get_prefix( 'actions' ),
			'type'             => 'XX_show_save_ballot_button_footer',
			'show_option_none' => false,
			'default'          => 'custom',
			'show_names'       => false, // Show field names on the left
			'after_field'      => array( __CLASS__, 'show_save_ballot_button' ),
		) );
	}

	public static function get_question_types() {

		return apply_filters( 'wp-vote_get_question_types', self::$question_types );

	}

	public static function get_add_signature_text_default() {

		return apply_filters( 'wp-vote-add-signature-text-default', sprintf(
		        //Translators: %s if the text of thesubmit button
		        __( "To complete your vote, please type out your name, which serves as your E-signature, and click '%s' ", 'wp-vote' ),
            Template_Actions::get_submit_button_text() ) );
	}


	/**
	 *
	 *
	 * @static
	 *
	 * @param $object
	 */
	public static function show_template_shortcodes( $object ) {

		echo( '<i>The follow shortcode can used in the template: {rep_name}, {voter_name}, {ballot_title}</i>' );
	}


	public static function get_question_types_for_select() {
		$question_types = self::get_question_types();
		$options        = array();
		if ( ! empty( $question_types ) ) {
			foreach ( $question_types as $slug => $data ) {
				$options[ $slug ] = $data['label'];
			}
		}

		return $options;
	}

	public static function display_open_ballot_questions() {
		echo '<div id="wp-vote-questions-wrap">';
		if ( self::STATUS_CLOSED === Ballot::get_ballot_status( self::get_ballot_id() ) ) {
			printf( '<p>%s</p>',
				__( 'The ballot is closed for voting.', 'wp-vote' )
			);
		}

		$questions = get_post_meta( self::get_ballot_id(), self::get_prefix( 'questions' ), true );

		echo '<div id="wp-vote-questions"><ul class="questions">';

		foreach ( $questions as $question_index => $question ) {
			if ( is_numeric( $question_index ) ) {
				\WP_Vote\Question::show_question( $question_index, $question );
			} else {
				do_action( WP_Vote::get_prefix( 'show_none_question' ), $question_index, $question );
			}
		}

		echo '</ul></div></div>';
	}


	public static function display_open_ballot_voters( $field_obj ) {


		global $post;
		$voters = get_post_meta( self::get_ballot_id(), self::get_prefix( 'voters' ), true );
		wp_enqueue_script( 'jquery-ui-dialog' );
		wp_enqueue_style( 'wp-jquery-ui-dialog' );

		if ( Ballot::get_ballot_status() ) {
			if ( $voters ) {
				if ( self::STATUS_CLOSED === Ballot::get_ballot_status( self::get_ballot_id() ) ) {
					printf( '<table class="voter-status"><thead><tr><th>%s</th><th>%s</th><th class="button-col">
							</th></tr></thead><tbody>',
						esc_html__( 'Voter', 'wp-vote' ),
						esc_html__( 'Status', 'wp-vote' ),
						absint( $post->ID )
					);
				} else {
					printf( '<table class="voter-status"><thead><tr><th>%s</th><th>%s</th><th class="button-col">
							<button class="button button-primary" id="wp-vote-send-ballot-to-all-voters" data-ballot-id="%d" type="button">%s</button>
							<span class="spinner"></span></th></tr></thead><tbody>',
						esc_html__( 'Voter', 'wp-vote' ),
						esc_html__( 'Status', 'wp-vote' ),
						absint( $post->ID ),
						esc_html__( 'Email all pending', 'wp-vote' )
					);
				}

				foreach ( $voters as $voter ) {

					$status = ( empty( $voter['voted'] ) ) ? __( 'Pending', 'wp-vote' ) : __( 'Voted', 'wp-vote' );
					if ( self::STATUS_CLOSED === self::get_ballot_status( self::get_ballot_id() ) ) {
						$status = ( empty( $voter['voted'] ) ) ? __( 'Did not Vote', 'wp-vote' ) : __( 'Voted', 'wp-vote' );
					}

					$action_id   = ( empty( $voter['voted'] ) ) ? 'wp-vote-send-ballot-to-individual' : 'wp-vote-show-individual-votes';
					$action_text = ( empty( $voter['voted'] ) ) ? __( 'Email voter', 'wp-vote' ) : __( 'Show Votes', 'wp-vote' );

					// do not show button if no vote
					if ( self::STATUS_CLOSED !== self::get_ballot_status( self::get_ballot_id() ) || ! empty( $voter['voted'] ) ) {

						printf( '<tr><td>%s</td><td>%s</td><td><button class="button button-primary %s" data-voter-id="%d" data-ballot-id="%d"  type="button">%s</button><span class="spinner"></span></td></tr>',
							esc_html( $voter['title'] ),
							esc_html( $status ),
							esc_attr( $action_id ),
							absint( $voter['ID'] ),
							absint( self::get_ballot_id() ),
							esc_html( $action_text )
						);
					} else {

						printf( '<tr><td>%s</td><td colspan="2">%s</td></tr>',
							esc_html( $voter['title'] ),
							esc_html( $status )
						);
					}

				}

				printf( '</tbody></table>' );
			}
		}

	}

	public static function before_question_title_row() {
		echo '<span class="question_title_wrap">';
	}

	public static function before_question_description_row() {
		echo '<span class="question_description_wrap">';
	}

	public static function before_answer_row() {
		echo '<span class="answers_wrap">';
	}

	public static function after_row() {
		echo '</span>';
	}

	public static function display_voters_stats( $ballot_id, $voter_id, $echo = true ) {

		$questions = get_post_meta( $ballot_id, self::get_prefix( 'questions' ), true );
		$voters    = get_post_meta( $ballot_id, self::get_prefix( 'voters' ), true );
		$html      = '';
		if ( isset( $voters[ $voter_id ]['votes'] ) ) {


			$voter_votes = $voters[ $voter_id ]['votes'];

			$html .= sprintf( '<h1>%s</h1>', esc_html__( sprintf( _x( 'The Votes cast for: %s', 'wp-vote', 'voters name' ), $voters[ $voter_id ]['title'] ) ) );
			$html .= '<div id="wp-vote-questions"><ul class="questions">Questions';

			foreach ( $questions as $question_index => $question ) {
				if ( is_numeric( $question_index ) ) {
					$question_title       = ( isset( $question[ Ballot::get_prefix( 'question_title' ) ] ) ) ? $question[ Ballot::get_prefix( 'question_title' ) ] : '';
					$question_description = ( isset( $question[ Ballot::get_prefix( 'question_description' ) ] ) ) ? $question[ Ballot::get_prefix( 'question_description' ) ] : '';

					$html .= sprintf(
						apply_filters( 'display_voters_stats_html_template', '<li class="clearfix"><strong>%s</strong><h4>%s</h4><div>%s</div></li>' ),
						$voter_votes[ $question_index ],
						esc_html( $question_title ),
						apply_filters( 'the_content', $question_description )
					);
				} else {
					$html = apply_filters( WP_Vote::get_prefix( 'show_not_question_filter' ), $html, $question_index, $question, $voters[ $voter_id ] );
				}
			}
		}

		if ( isset( $voters[ $voter_id ]['accepted_conditions'] ) ) {
			$html .= sprintf( '<h4>%s</h4>', __( 'The Voter Accepted Conditions', 'wp-vote' ) );
			$html .= sprintf( '<p>%s</p>', esc_html( $voters[ $voter_id ]['accepted_conditions'] ) );
		}
		if ( isset( $voters[ $voter_id ]['conditions_add_signature'] ) ) {
			$html .= sprintf( '<h4>%s</h4>', __( 'The Voter Signed the Ballot with', 'wp-vote' ) );
			$html .= sprintf( '<p>%s</p>', esc_html( $voters[ $voter_id ]['conditions_add_signature'] ) );
		}

		if ( isset( $voters[ $voter_id ]['ip_address'] ) ) {
			$html .= sprintf( '<h4>%s</h4>', __( 'Voters IP Address', 'wp-vote' ) );
			$html .= sprintf( '<p>%s</p>', esc_html( $voters[ $voter_id ]['ip_address'] ) );
		}

		if ( ! $echo ) {

			return $html;
		}

		echo $html;
	}


	/**
	 *
	 *
	 * @static
	 *
	 * @param $field_obj
	 */
	public static function show_save_ballot_button( $field_obj ) {

		printf( '<input type="submit" name="save" value="%s" class="button button-primary button-large save-post-etc">',
			esc_html__( 'Save Ballot', 'wp-vote' )
		);
	}

	public static function show_test_ballot_button( $field_obj ) {

		printf( '<input type="button" id="test_ballot" name="test_ballot" value="%s" class="button button-large"><br /><span id="test_email_message"></span>',
			esc_html__( 'Send Test Ballot Email', 'wp-vote' )
		);
	}

	public static function draft_message( $post ) {
		$screen = get_current_screen();
		if ( self::get_post_type() !== $screen->id ) {
			return;
		}
		if ( 'publish' !== $post->post_status && 'future' !== $post->post_status && 'pending' !== $post->post_status ) {
			printf( '<p style="clear: both; text-align: left; padding-top: 10px">%s</p>',
				__( 'Save this ballot as a draft if voting has not yet opened. When the ballot is in draft mode you may continue to make edits to questions and voters.', 'wp-vote' )
			);
		}
	}

	public static function post_submitbox_start() {
		$screen = get_current_screen();
		if ( self::get_post_type() !== $screen->id ) {
			return;
		}

		global $post;

		if ( self::get_post_type() !== $post->post_type ) {
			return false;
		}

		// Always hide the publish button
		?>
        <style>
            #publishing-action {
                display: none;
            }
        </style>
		<?php

		switch ( self::get_ballot_status() ) {
			case self::STATUS_NEW:
//				printf( '<p>%s</p>',
//					__( 'When you open the ballot for voting all voters will receive an email and the ballot will become read-only.', 'wp-vote' ) );
//				printf( '<div class="cmb-row"><input name="%s" type="%s" class="%s" id="%s" value="%s" data-ballot="%d"></div>',
//					'wp-vote_open_ballot',
//					'submit',
//					'button button-primary button-large',
//					'wp-vote_open_ballot',
//					__( 'Open Ballot & Email Voters', 'wp-vote' ),
//					Ballot::get_ballot_id() );
//				break;
			case self::STATUS_DRAFT:
				printf( '<p>%s</p>',
					__( 'Once you send the proxy to voters, voting will open and all voters will receive an email notification with link to vote and the ballot will become read-only.', 'wp-vote' ) );
				printf( '<div class="wp-vote-open-ballot-confirmation"><input name="%1$s" type="%2$s" class="" id="%1$s" value="%3$s"><label for="%1$s">%4$s</label></div>',
					'wp-vote_open_ballot_checkbox',
					'checkbox',
					'confirm',
					__( 'I acknowledge the terms above and am ready to open voting.', 'wp-vote' ) );
				printf( '<div class="wp-vote-open-ballot"><input name="%s" type="%s" class="%s" id="%s" value="%s" data-ballot="%d"></div>',
					'wp-vote_open_ballot',
					'submit',
					'button button-primary button-large',
					'wp-vote_open_ballot',
					__( 'Open Ballot & Email Voters', 'wp-vote' ),
					self::get_ballot_id() );
			        self::render_close_ballot_controls();
				break;
			case self::STATUS_OPEN:
				printf( '<p>%s</p>',
					__( 'This ballot is open. While the ballot is open it can receive votes. Close the ballot at any time to stop voting.', 'wp-vote' )
				);
				printf( '<div class="cmb-row"><input name="%s" type="%s" class="%s" id="%s" value="%s" data-ballot="%d"></div>',
					'wp-vote_close_ballot',
					'submit',
					'button button-primary button-large',
					'wp-vote_close_ballot',
					__( 'Close Ballot to Stop Voting', 'wp-vote' ),
					self::get_ballot_id()
				);
				self::render_close_ballot_controls();
				?>
                <style>
                    #delete-action, #publishing-action {
                        display: none;
                    }
                </style>
				<?php
				break;
			case self::STATUS_CLOSED:
				printf( '<p>%s</p>',
					__( 'This ballot is closed.', 'wp-vote' )
				);
				printf( '<input name="%s" type="%s" class="%s" id="%s" value="%s">',
					'wp-vote_export_results',
					'submit',
					'button button-primary button-large',
					'wp-vote_export_results',
					__( 'Export Results to CSV', 'wp-vote' )
				);

				?>
                <style>
                    #delete-action {
                        display: none;
                    }
                </style>
				<?php
				break;
		}
	}

    private static function render_close_ballot_controls(){
	    global $wp_locale;

	    if( Ballot::STATUS_CLOSED === self::get_ballot_status(  get_the_ID() ) ) {

	        return;
        }

	    $time = get_post_meta( get_the_ID(), Ballot::get_prefix( 'close_ballot_at' ) ,true );
	    $close_at = false;

	    if( ! empty( $time ) ) {
		    $date = new \DateTime( $time );

		    $close_at =  $date->format('Y-m-d H:i:s' );
        }



        printf('<div id="ballot_close_time"><strong>%s</strong> <a id="edit_ballot_close_time" href="#edit_ballot_close_time" >%s</a><div class="set_close_time">',
            ( false === $close_at ) ? esc_html__( 'Auto Close Ballot not set') :  sprintf( esc_html__( 'Closing ballot at: %s') , date_i18n( __( 'M j, Y @ H:i' ), strtotime( $close_at ) )),
	        ( false === $close_at ) ?  esc_html__( ' configure') : esc_html__( ' edit')
        );

	    $time_adj = current_time('timestamp');

	    $jj = ($close_at) ? mysql2date( 'd', $close_at, false ) : gmdate( 'd', $time_adj );
	    $mm = ($close_at) ? mysql2date( 'm', $close_at, false ) : gmdate( 'm', $time_adj );
	    $aa = ($close_at) ? mysql2date( 'Y', $close_at, false ) : gmdate( 'Y', $time_adj );
	    $hh = ($close_at) ? mysql2date( 'H', $close_at, false ) : gmdate( 'H', $time_adj );
	    $mn = ($close_at) ? mysql2date( 'i', $close_at, false ) : gmdate( 'i', $time_adj );
	    $ss = ($close_at) ? mysql2date( 's', $close_at, false ) : gmdate( 's', $time_adj );

	    $cur_jj = gmdate( 'd', $time_adj );
	    $cur_mm = gmdate( 'm', $time_adj );
	    $cur_aa = gmdate( 'Y', $time_adj );
	    $cur_hh = gmdate( 'H', $time_adj );
	    $cur_mn = gmdate( 'i', $time_adj );

	    $month = '<label><span class="screen-reader-text">' . __( 'Month' ) . '</span><select id="ballot-mm" name="mm"' . ">\n";
	    for ( $i = 1; $i < 13; $i = $i +1 ) {
		    $monthnum = zeroise($i, 2);
		    $monthtext = $wp_locale->get_month_abbrev( $wp_locale->get_month( $i ) );
		    $month .= "\t\t\t" . '<option value="' . $monthnum . '" data-text="' . $monthtext . '" ' . selected( $monthnum, $mm, false ) . '>';
		    /* translators: 1: month number (01, 02, etc.), 2: month abbreviation */
		    $month .= sprintf( __( '%1$s-%2$s' ), $monthnum, $monthtext ) . "</option>\n";
	    }
	    $month .= '</select></label>';

	    $day = '<label><span class="screen-reader-text">' . __( 'Day' ) . '</span><input type="text" id="ballot-jj" name="jj" value="' . $jj . '" size="2" maxlength="2" autocomplete="off" /></label>';
	    $year = '<label><span class="screen-reader-text">' . __( 'Year' ) . '</span><input type="text" id="ballot-aa" name="aa" value="' . $aa . '" size="4" maxlength="4" autocomplete="off" /></label>';
	    $hour = '<label><span class="screen-reader-text">' . __( 'Hour' ) . '</span><input type="text" id="ballot-hh" name="hh" value="' . $hh . '" size="2" maxlength="2" autocomplete="off" /></label>';
	    $minute = '<label><span class="screen-reader-text">' . __( 'Minute' ) . '</span><input type="text" id="ballot-mn" name="mn" value="' . $mn . '" size="2" maxlength="2" autocomplete="off" /></label>';

	    echo '<div class="timestamp-wrap">';
	    /* translators: 1: month, 2: day, 3: year, 4: hour, 5: minute */
	    printf( __( '%1$s %2$s, %3$s @ %4$s:%5$s' ), $month, $day, $year, $hour, $minute );

	    echo '</div><input type="hidden" id="ss" name="ss" value="' . $ss . '" />';

        echo "\n\n";
//        $map = array(
//            'mm' => array( $mm, $cur_mm ),
//            'jj' => array( $jj, $cur_jj ),
//            'aa' => array( $aa, $cur_aa ),
//            'hh' => array( $hh, $cur_hh ),
//            'mn' => array( $mn, $cur_mn ),
//        );
//        foreach ( $map as $timeunit => $value ) {
//            list( $unit, $curr ) = $value;
//
//            echo '<input type="hidden" id="hidden_' . $timeunit . '" name="hidden_' . $timeunit . '" value="' . $unit . '" />' . "\n";
//            $cur_timeunit = 'cur_' . $timeunit;
//            echo '<input type="hidden" id="' . $cur_timeunit . '" name="' . $cur_timeunit . '" value="' . $curr . '" />' . "\n";
//        }
	    wp_nonce_field( 'ballot_ajax-calls', 'ballot_ajax-calls' );

        ?>

        <p>
            <a href="#ballot_rest_time" class="reset-timestamp hide-if-no-js button"><?php _e('Clear Schedule'); ?></a>
            <a href="#ballot_close_time" class="save-timestamp hide-if-no-js button"><?php _e('set'); ?></a>
            <a href="#ballot_close_time" class="cancel-timestamp hide-if-no-js button-cancel"><?php _e('Cancel'); ?></a>
        </p>

	    <?php
        printf('<p class="note">%s</p></div>', esc_html__( 'Set the target time to close the ballot') );

	    echo '</div>';
    }


	private static function create_voter_meta_box() {
		self::$cmb_voters = new_cmb2_box( array(
			'id'           => self::get_prefix( 'voter_metabox' ),
			'title'        => __( 'Voters', 'wp_vote' ),
			'object_types' => array( self::get_post_type() ), // Post type
			'context'      => 'normal',
			'priority'     => 'high',
			'show_names'   => true, // Show field names on the left
		) );
	}


	function rename_publish_button( $translation, $text ) {

		global $post;

		if ( $text == 'Publish' && $post->post_type === self::get_post_type() ) {
			return __( 'Open Ballot', 'wp-vote' );
		}

		return $translation;
	}

	static function save_ballot_maybe_change_status( $post_id, $post, $update ) {

		if ( self::get_post_type() !== $post->post_type ) {
			return;
		}

		// Actions based on ballot status changes
		if ( isset( $_POST['wp-vote_draft_ballot'] ) ) {
			// No actions ... yet
		}

		if ( isset( $_POST['wp-vote_open_ballot'] ) ) {

			$voters = get_post_meta( $post_id, self::get_prefix( 'eligible' ), true );
			if ( '' === $voters ) {
				Admin_Notices::display_error( __( 'Cannot open ballot for voting without any voters attached!', 'wp-vote' ) );
				wp_safe_redirect( esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) );
				die();
			}

			// Open Ballot
			self::open_ballot( $post_id );

		}

		if ( isset( $_POST['wp-vote_close_ballot'] ) ) {

			// Close ballot
			self::close_ballot( $post_id );

		}

	}

	/**
	 * @return int
	 */
	public static function get_ballot_id() {
		global $post;

		$ballot_id = 0;

		if ( is_admin() ) {
			$ballot_id = ( isset( $_REQUEST['post'] ) ) ? $_REQUEST['post'] : 0;
		} else {
			$ballot_id = $post->ID;
		}

		return $ballot_id;
	}


	public static function get_ballot_status( $ballot_id = false ) {
		if ( ! $ballot_id ) {
			if ( isset( $_REQUEST['post'] ) ) {
				$ballot_id = $_REQUEST['post'];
			} else {
				return self::STATUS_NEW;
			}
		}

		$status = get_post_meta( $ballot_id, self::get_prefix( 'status' ), true );

		if ( self::STATUS_OPEN === $status || self::STATUS_CLOSED === $status ) {
			return $status;
		}

		return self::STATUS_DRAFT;
	}


	/**
	 * Generic ballot status function
	 * Used to set ballot status from draft/null -> open -> closed
	 * See: open_ballot(), close_ballot()
	 *
	 * @param $ballot_id
	 * @param $status
	 *
	 * @return bool|int
	 */
	public static function set_ballot_status( $ballot_id, $status ) {
		if ( in_array( $status, array( self::STATUS_OPEN, self::STATUS_CLOSED ) ) ) {
			self::$status = $status;

			return update_post_meta( $ballot_id, self::get_prefix( 'status' ), $status );
		}

		return false;
	}

	public static function get_voters( $ballot_id ) {
		return get_post_meta( $ballot_id, self::get_prefix( 'voters' ), true );
	}


	/**
	 * Open Ballot
	 *
	 * @param $ballot_id
	 *
	 * @return bool|int
	 */
	public static function open_ballot( $ballot_id ) {
		if ( self::STATUS_DRAFT !== self::get_ballot_status( $ballot_id ) ) {
			return false;
		}

		self::set_salt( $ballot_id );
		self::set_open_date( $ballot_id );
		self::set_ballot_status( $ballot_id, self::STATUS_OPEN );
		self::copy_voters_into_ballot( $ballot_id );
		self::copy_answers_into_ballot( $ballot_id );

		// Email voters
		self::email_eligible_voters( $ballot_id, self::STATUS_OPEN );

		// as we use crafted URL let flush the permlinks here ot make to it set
		flush_rewrite_rules();

		return true;
	}

	/**
	 * Close Ballot
	 *
	 * @param $ballot_id
	 *
	 * @return bool|int
	 */
	public static function close_ballot( $ballot_id ) {
		if ( self::STATUS_OPEN !== self::get_ballot_status( $ballot_id ) ) {
			return false;
		}
		// Resetting the salt nullifies any previous voter links
		self::set_salt( $ballot_id );
		self::set_closed_date( $ballot_id );
		self::set_ballot_status( $ballot_id, self::STATUS_CLOSED );

		return true;
	}


	/**
	 * Close Ballot
	 *
	 * @param $ballot_id
	 *
	 * @return bool|int
	 */
	public static function maybe_close_ballot() {

		$today = date('Ymd');
		$args = array(
			'post_type' => Ballot::get_post_type(),
			'posts_per_page' => '-1',
			'meta_key' =>  self::get_prefix( 'close_ballot_at' ),
			'meta_query' => array(

				array(
					'key' => self::get_prefix( 'close_ballot_at' ),
					'value' => $today,
					'compare' => '<='
				)
			),
			'orderby' => 'meta_value_num',
			'order' => 'ASC'
		);

	    // get all ballot with close date
        $posts = new \WP_Query($args);

        //loop and close all
		foreach ( $posts->posts as $post ) {
		    // only close open ballots
			if( Ballot::STATUS_OPEN === self::get_ballot_status(  get_the_ID() ) ) {

				self::close_ballot( $post->ID );
			}
        }
	}

	/**
	 * Get Salt
	 *
	 * @param $ballot_id
	 *
	 * @return mixed
	 */
	public static function get_salt( $ballot_id ) {
		if ( ! isset( self::$salt ) ) {
			self::$salt = get_post_meta( $ballot_id, self::get_prefix( 'salt' ), true );
		}

		return self::$salt;
	}


	/**
	 * Set Salt
	 *
	 * @param $ballot_id
	 * @param $salt
	 */
	public static function set_salt( $ballot_id, $salt = false ) {
		if ( false === $salt ) {
			$salt = rand( 999999, 10000000 );
		}
		self::$salt = $salt;
		add_post_meta( $ballot_id, self::get_prefix( 'salt' ), $salt, true );
	}

	/**
	 * Set Open Date
	 *
	 * @param $ballot_id
	 * @param $date_open
	 */
	public static function set_open_date( $ballot_id, $date_open = false ) {
		if ( false === $date_open ) {
			$date_open = current_time( 'timestamp', true );
		}
		self::$date_open = $date_open;
		add_post_meta( $ballot_id, self::get_prefix( 'date_open' ), $date_open, true );
	}

	/**
	 * Set Closed Date
	 *
	 * @param $ballot_id
	 * @param $date_closed
	 */
	public static function set_closed_date( $ballot_id, $date_closed = false ) {
		if ( false === $date_closed ) {
			$date_closed = current_time( 'timestamp', true );
		}
		self::$date_closed = $date_closed;
		add_post_meta( $ballot_id, self::get_prefix( 'date_closed' ), $date_closed, true );
	}


	public static function copy_voters_into_ballot( $ballot_id ) {

		$voters = get_post_meta( $ballot_id, self::get_prefix( 'eligible' ), true );
		$voters = explode( ',', $_POST[ self::get_prefix( 'eligible' ) ] );

		$ballot_voters = array();
		foreach ( $voters as $voter ) {
			$ballot_voters[ $voter ] = Voter::get_voter_details( $voter );
		}

		update_post_meta( $ballot_id, self::get_prefix( 'voters' ), $ballot_voters );
		//TODO:  remove the old data maybe
		//delete_post_meta( $ballot_id, Voter::get_prefix( 'eligible' ) );
	}

	public static function copy_answers_into_ballot( $ballot_id ) {

		$questions = get_post_meta( $ballot_id, self::get_prefix( 'questions' ), true );

		foreach ( $questions as $question_index => $question ) {

			$question_type  = $question[ self::get_prefix( 'question_type' ) ];
			$question_types = self::get_question_types();

			$answer_options = array();
			if ( ! empty( $question_types[ $question_type ]['answers'] ) ) {
				$answer_options = $question_types[ $question_type ]['answers'];
			} else {
				$raw_answers = explode( PHP_EOL, $questions[ $question_index ][ self::get_prefix( 'question_answers' ) ] );
				foreach ( $raw_answers as $raw_answer ) {
					$raw_answer_arr = explode( ' : ', $raw_answer );
					if ( 1 === count( $raw_answer_arr ) ) {
						$answer_options[ sanitize_title( $raw_answer_arr[0] ) ] = $raw_answer_arr[0];
					} else {
						$answer_options[ sanitize_title( $raw_answer_arr[0] ) ] = $raw_answer_arr[1];
					}
				}
			}

			$answers = array();
			foreach ( $answer_options as $answer_option_index => $answer_option ) {
				$answers[ $answer_option_index ] = array(
					'label' => $answer_option,
					'count' => 0,
				);
			}

			$questions[ $question_index ][ self::get_prefix( 'question_answers' ) ] = $answers;

		}
		$status = update_post_meta( $ballot_id, self::get_prefix( 'questions' ), $questions );

		return $status;

	}


	public static function email_eligible_voters( $ballot_id, $status ) {
		$eligible_voters = get_post_meta( $ballot_id, self::get_prefix( 'voters' ), true );

		add_filter( 'wp_mail_content_type', array(
			__NAMESPACE__ . '\\Settings',
			'set_content_type'
		) );
		add_filter( 'wp_mail_from', array(
			__NAMESPACE__ . '\\Settings',
			'custom_wp_mail_from'
		), 99 );
		add_filter( 'wp_mail_from_name', array(
			__NAMESPACE__ . '\\Settings',
			'wp_mail_from_name'
		), 99 );

		foreach ( $eligible_voters as $eligible_voter ) {
			$voter_type  = $eligible_voter['voter_type'];
			$voter_types = Voter::get_voter_types();
			$voter_class = $voter_types[ $voter_type ]['class'];

			$voter = new $voter_class( $eligible_voter );
			$voter->send_email_notification( $ballot_id, $status );
		}

		remove_filter( 'wp_mail_from_name', array(
			__NAMESPACE__ . '\\Settings',
			'wp_mail_from_name'
		), 99 );
		remove_filter( 'wp_mail_from', array(
			__NAMESPACE__ . '\\Settings',
			'custom_wp_mail_from'
		), 99 );
		remove_filter( 'wp_mail_content_type', array(
			__NAMESPACE__ . '\\Settings',
			'set_content_type'
		) );
	}

	/**
	 *
	 *
	 * @static
	 *
	 * @param $ballot_id
	 * @param $voter_id
	 * @param $rep_index
	 *
	 * @return string
	 */
	public static function get_representative_url( $ballot_id, $voter_id, $rep_index ) {

		$token = self::calculate_representative_token( $ballot_id, $voter_id, $rep_index );

		$permalink = get_permalink( $ballot_id );

		if ( 0 < strpos( $permalink, '?' ) ) {

			$ballot_url = add_query_arg( array(
				'voter_id' => $voter_id,
				'rep_id'   => $rep_index,
				'token'    => $token,
			), $permalink );

		} else {

			$ballot_url = trailingslashit( $permalink ) . "{$voter_id}/{$rep_index}/{$token}";
		}
		error_log( $ballot_url );

		error_log( $ballot_url );

		return $ballot_url;
	}

	/**
	 *
	 * @static
	 *
	 * @param $ballot_id
	 * @param $voter_id
	 * @param $rep_index
	 *
	 * @return string
	 */
	public static function calculate_representative_token( $ballot_id, $voter_id, $rep_index ) {

		$salt = self::get_salt( $ballot_id );

		$token = md5( $salt . $ballot_id . $voter_id . $rep_index );

		return $token;
	}

	/**
	 *
	 *
	 * @static
	 *
	 * @param $token
	 * @param $ballot_id
	 * @param $voter_id
	 * @param $rep_index
	 *
	 * @return bool
	 */
	public static function validate_token( $token, $ballot_id, $voter_id, $rep_index ) {

		$calculated_token = self::calculate_representative_token( $ballot_id, $voter_id, $rep_index );

		return ( $token === $calculated_token );
	}

	public static function remove_permalink( $return ) {
		global $post_type;

		if ( is_admin() && self::get_post_type() == $post_type ) {
			return '';
		}

		return $return;
	}

	/**
	 *
	 *
	 * @static
	 *
	 * @param $messages
	 *
	 * @return mixed
	 */
	public static function post_published_message( $messages ) {
		global $post;
		if ( null !== $post && $post->post_type === self::get_post_type() && is_admin() ) {

			$messages['post'][1]  = sprintf( __( 'Ballot updated', 'wp-vote' ) );
			$messages['post'][6]  = sprintf( __( 'Ballot saved', 'wp-vote' ) );
			$messages['post'][10] = str_replace( 'Post draft updated', __( 'Ballot saved', 'wp-vote' ), $messages['post'][10] );
			$messages['post'][10] = str_replace( 'Preview post', __( 'Preview ballot', 'wp-vote' ), $messages['post'][10] );
		}

		return $messages;
	}

	/**
	 *
	 *
	 * @static
	 *
	 * @param $trans
	 * @param $text
	 *
	 * @return string|void
	 */
	public static function filter_published_on( $trans, $text ) {

		if ( function_exists( 'get_current_screen' ) ) {

			$screen = get_current_screen();
			if ( null !== $screen && $screen->post_type === self::get_post_type() && is_admin() ) {

				switch ( $text ) {
					case 'Save Draft':

						//		return __( 'Save Ballot', 'wp-vote' );
						break;
				}

			}
		}

		return $trans;
	}


	public static function sniff_submits() {

		$nonce_key = WP_Vote::_get_prefix( 'ballot' );

		if ( isset( $_POST[ $nonce_key ] ) ) {

			$token = Template_Actions::get_token();
			if ( ! wp_verify_nonce( $_POST[ $nonce_key ], 'submit_ballot_' . sanitize_title( $token['token'] ) ) ) {
				wp_die( __( 'nonce failed', 'wp-vote' ) );
			}
			unset( $_POST[ $nonce_key ] );

			do_action( self::get_prefix( 'pre-votes-validation' ), self::get_ballot_id() );

			$orig_url = $_POST['_wp_http_referer'];
			unset( $_POST['_wp_http_referer'] );


			$votes = self::validate_form( $_POST );

			if ( false !== $votes ) {
				if ( self::STATUS_CLOSED !== self::get_ballot_status( self::get_ballot_id() ) ) {
					$state = self::save_votes( $votes );
					if ( $state ) {
						wp_safe_redirect( add_query_arg( array( 'state' => 'success' ), $orig_url ) );
					} else {
						wp_safe_redirect( add_query_arg( array( 'state' => $state ), $orig_url ) );
					}
				} else {
					wp_safe_redirect( add_query_arg( array( 'state' => 'closed' ), $orig_url ) );
				}
			} else {

				wp_safe_redirect( add_query_arg( array( 'state' => 'failed' ), $orig_url ) );

			}

			die();
		}
	}


	private static function validate_form( $post ) {

		$votes            = array();
		$ballot_questions = get_post_meta( self::get_ballot_id(), self::get_prefix( 'questions' ), true );

		foreach ( $ballot_questions as $question_index => $ballot_question ) {

			if ( is_numeric( $question_index ) ) {
				if ( empty( $post[ self::get_prefix( get_the_ID() . '_' . $question_index ) ] ) ) {

					return false;
				}

				$votes[ $question_index ] = $post[ self::get_prefix( get_the_ID() . '_' . $question_index ) ];
			}
		}

		return $votes;
	}

	public static function save_accepted_conditions( $votes ) {

		if ( isset( $_POST[ self::get_prefix( 'conditions' ) ] ) ) {

			$votes['accepted_conditions'] = ( $_POST[ self::get_prefix( 'conditions' ) ] ) ? __( 'Yes', 'wp-vote' ) : __( 'No', 'wp-vote' );

		}


		if ( isset( $_POST[ self::get_prefix( 'conditions_signature' ) ] ) ) {

			$votes['conditions_add_signature'] = ( $_POST[ self::get_prefix( 'conditions_signature' ) ] ) ? sanitize_text_field( $_POST[ self::get_prefix( 'conditions_signature' ) ] ) : __( 'No', 'wp-vote' );

		}

		$votes['ip_address'] = self::get_client_ip();

		return $votes;

	}


	private static function get_client_ip() {

		if ( isset( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ipaddress = $_SERVER['HTTP_CLIENT_IP'];
		} else if ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} else if ( isset( $_SERVER['HTTP_X_FORWARDED'] ) ) {
			$ipaddress = $_SERVER['HTTP_X_FORWARDED'];
		} else if ( isset( $_SERVER['HTTP_FORWARDED_FOR'] ) ) {
			$ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
		} else if ( isset( $_SERVER['HTTP_FORWARDED'] ) ) {
			$ipaddress = $_SERVER['HTTP_FORWARDED'];
		} else if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$ipaddress = $_SERVER['REMOTE_ADDR'];
		} else {
			$ipaddress = 'UNKNOWN';
		}

		return $ipaddress;
	}

	/**
	 *
	 *
	 * @static
	 *
	 * @param $votes
	 *
	 * @return bool|int
	 */
	private static function save_votes( $votes ) {

		if ( true === Template_Actions::is_allowed_to_vote() ) {

			$ballot_id = self::get_ballot_id();
			$token     = Template_Actions::get_token();
			$voter_id  = $token['voter_id'];

			$voters                       = self::get_voters( $ballot_id );
			$voters[ $voter_id ]['voted'] = array(
				'timestamp' => current_time( 'timestamp', true ),
				'rep'       => $token['rep_id'],
			);
			$voters[ $voter_id ]['votes'] = $votes;

			$voters[ $voter_id ] = apply_filters( self::get_prefix( 'votes_to_be_saved' ), $voters[ $voter_id ] );

			$questions = get_post_meta( $ballot_id, self::get_prefix( 'questions' ), true );

			foreach ( $votes as $index => $vote ) {
				$questions[ $index ][ self::get_prefix( 'question_answers' ) ][ $vote ]['count'] ++;
			}

			$questions = apply_filters( self::get_prefix( 'questions_to_be_saved' ), $questions );

			$success = update_post_meta( absint( $ballot_id ), self::get_prefix( 'voters' ), $voters )
			           && update_post_meta( absint( $ballot_id ), self::get_prefix( 'questions' ), $questions );

			if ( $success ) {

				$voter_types = Voter::get_voter_types();

				$voter_class = $voter_types[ $voters[ $voter_id ]['voter_type'] ]['class'];

				$voter = new $voter_class( $voters[ $voter_id ] );

				add_filter( 'wp_mail_content_type', array(
					__NAMESPACE__ . '\\Settings',
					'set_content_type'
				) );
				add_filter( 'wp_mail_from', array(
					__NAMESPACE__ . '\\Settings',
					'custom_wp_mail_from'
				), 99 );
				add_filter( 'wp_mail_from_name', array(
					__NAMESPACE__ . '\\Settings',
					'wp_mail_from_name'
				), 99 );

				$voter->send_email_notification( $ballot_id, self::STATUS_VOTED );

				remove_filter( 'wp_mail_from_name', array(
					__NAMESPACE__ . '\\Settings',
					'wp_mail_from_name'
				), 99 );
				remove_filter( 'wp_mail_from', array(
					__NAMESPACE__ . '\\Settings',
					'custom_wp_mail_from'
				), 99 );
				remove_filter( 'wp_mail_content_type', array(
					__NAMESPACE__ . '\\Settings',
					'set_content_type'
				) );


			}

			return $success;
		}

		return false;
	}

}
