<?php

namespace WP_Vote;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Wp_Vote_Ballot_proxy
 * @package WP_Vote
 */
class Wp_Vote_Ballot_proxy {

	/**
	 * @var
	 */
	private static $cmb_proxy;
	/**
	 * @var string
	 */
	private static $select_id;
	/**
	 * @var string
	 */
	private static $proxy_free_text;
	/**
	 * @var string
	 */
	private static $replace_string;

	/**
	 * Wp_Vote_Ballot_proxy constructor.
	 */
	public function __construct() {

		self::$select_id       = WP_Vote::get_prefix( 'proxy-options' );
		self::$proxy_free_text = WP_Vote::get_prefix( 'proxy-free-text' );
		self::$replace_string  = apply_filters( WP_Vote::get_prefix( 'proxy-select-repace_string' ), __( '{proxy-select}', 'wp-options' ) );

		add_action( WP_Vote::get_prefix( 'ballot_new_admin_meta' ), array( __CLASS__, 'add_admin_metaboxes' ) );

		add_action( 'wp-vote_after_content_single_ballot_loop', array( __CLASS__, 'wp_vote_question_form_close' ), 30 );
		// TODO: look at option to see if the proxy is above or below the votes
		//add_action( 'wp-vote_after_ballot_loop', array( __CLASS__, 'wp_vote_question_form_close' ), 30 );

		//	add_action( WP_Vote::get_prefix( 'ballot_open_admin_meta' ), array( __CLASS__, 'show_proxy_stats' ) );
		//	add_action( WP_Vote::get_prefix( 'ballot_closed_admin_meta' ), array( __CLASS__, 'show_proxy_stats' ) );
		//	add_action( WP_Vote::get_prefix( 'pre-votes-validation' ), array( __CLASS__, 'validate' ) );
		add_action( WP_Vote::get_prefix( 'show_none_question' ), array( __CLASS__, 'show_none_question' ), 10, 2 );


		add_filter( WP_Vote::get_prefix( 'email-representative-message' ), array( __CLASS__, 'email_representative_message' ), 10, 7 );
		add_filter( Ballot::get_prefix( 'questions_to_be_saved' ), array( __CLASS__, 'questions_to_be_saved' ), 10 );
		add_filter( Ballot::get_prefix( 'votes_to_be_saved' ), array( __CLASS__, 'votes_to_be_saved' ), 10 );
		add_filter( WP_Vote::get_prefix( 'show_not_question_filter' ), array( __CLASS__, 'show_not_question_filter' ), 10, 4 );

	}

	/**
	 * called in action WP_Vote::get_prefix( 'ballot_new_admin_meta' )
	 */
	public static function add_admin_metaboxes() {

		global $pagenow;
		self::$cmb_proxy = new_cmb2_box( array(
			'id'           => WP_Vote::get_prefix( 'proxy_metabox' ),
			'title'        => __( 'Add a Proxy voter option', 'wp_vote' ),
			'object_types' => array( Ballot::get_post_type() ), // Post type
			'context'      => 'normal',
			'priority'     => 'high',
			'show_names'   => true, // Show field names on the left
		) );

		self::$cmb_proxy->add_field( array(
			'name' => esc_html__( 'Enable for this ballot', 'wp-vote' ),
			'id'   => WP_Vote::get_prefix( 'add_proxy' ),
			'type' => 'checkbox',
		) );
		self::$cmb_proxy->add_field( array(
			'name'        => esc_html__( 'Description', 'wp-vote' ),
			'id'          => WP_Vote::get_prefix( 'proxy_description' ),
			'type'        => 'wysiwyg',
			'default'     => ( in_array( $pagenow, array( 'post-new.php' ), true ) ) ? 'In the event that a motion is raised from the floor during the AGM I elect the following to act as my proxy:':'',
			'options'     => array(
				'wpautop'       => true, // use wpautop?
				'media_buttons' => true, // show insert/upload button(s)
				'textarea_rows' => 5, // rows="..."
			),
			'after_field' => array( __CLASS__, 'show_template_shortcodes' ),
		) );
		// Questions
		$group_field_id = self::$cmb_proxy->add_field( array(
			'id'      => WP_Vote::get_prefix( 'proxy_options' ),
			'name'    => esc_html__( 'Proxy Options', 'wp-vote' ),
			'type'    => 'group',
			'options' => array(
				'group_title'   => __( 'Option {#}', 'wp_vote' ),
				// since version 1.1.4, {#} gets replaced by row number
				'add_button'    => __( 'Add Another Proxy default option', 'wp_vote' ),
				'remove_button' => __( 'Remove option', 'wp_vote' ),
				'sortable'      => true,
				'attributes'    => array(
					'class' => 'question-type',
				),
			),
		) );

		self::$cmb_proxy->add_group_field( $group_field_id, array(
			'name' => esc_html__( 'Proxy Names', 'wp-vote' ),
			'id'   => WP_Vote::get_prefix( 'proxy_name' ),
			'type' => 'text',
		) );

		self::$cmb_proxy->add_field( array(
			'name'    => esc_html__( 'Options', 'wp-vote' ),
			'id'      => WP_Vote::get_prefix( 'proxy_other' ),
			'type'    => 'multicheck',
			'options' => array(
				'require'    => __( 'Require a proxy is set', 'wp-vote' ),
				'free-text'  => __( 'Add option to specify name of proxy', 'wp-vote' ),
				'all-voters' => __( 'Include all the other voters in the ballot', 'wp-vote' ),
				'allow_none' => __( 'Allow no proxy to be set', 'wp-vote' ),
			),
		) );

		self::$cmb_proxy->add_field( array(
			'name'             => esc_html__( 'Ballot Save', 'wp-vote' ),
			'desc'             => '',
			'id'               => WP_Vote::get_prefix( 'actions' ),
			'type'             => 'XX_show_save_ballot_button_footer',
			'show_option_none' => false,
			'default'          => 'custom',
			'show_names'       => false, // Show field names on the left
			'after_field'      => array( __CLASS__, 'show_save_ballot_button' ),
		) );
	}

	//TODO: remove this and call the function in ballot
	public static function show_save_ballot_button( $field_obj ) {

		printf( '<input type="submit" name="save" value="%s" class="button button-primary button-large save-post-etc">',
			esc_html__( 'Save Ballot', 'wp-vote' )
		);
	}

	/**
	 *
	 *
	 * @static
	 *
	 */
	public static function show_template_shortcodes() {
		echo '<i>';
		printf( __( 'The %s shortcode can used in the template and will be replaced ny the proxy selection option.', 'wp-vote' ), esc_html( self::$replace_string ) );
		echo '</i>';
	}

	/**
	 * called in action wp-vote_after_single_ballot_loop
	 *
	 * @param      $ballot_id
	 * @param bool $echo
	 *
	 * @return mixed|string
	 */
	public static function wp_vote_question_form_close( $ballot_id, $echo = true ) {

		if ( null === $ballot_id ) {
			$ballot_id = get_the_ID();
		}

		if ( 'on' === get_post_meta( $ballot_id, WP_Vote::get_prefix( 'add_proxy' ), true ) ) {

			$token = Template_Actions::get_token();

			$ballot_voters = get_post_meta( get_the_ID(), 'wp-vote-ballot_voters', true );
			$vote          = ( isset( $ballot_voters[ $token['voter_id'] ]['proxy'] ) ) ? $ballot_voters[ $token['voter_id'] ]['proxy'] : false;
			$html          = '';
			if ( $vote ) {

				$proxy_description = get_post_meta( $ballot_id, WP_Vote::get_prefix( 'proxy_description' ), true );
				$proxy_description = sprintf( '<span class="wp-vote-proxy-desc">%s</span>', wp_kses_post( $proxy_description ) );


				if ( false !== $proxy_description ) {
					if ( strpos( $proxy_description, self::$replace_string ) ) {

						$html = str_replace( self::$replace_string, $vote, $proxy_description );
					} else {

						$html = $proxy_description . ' ' . $vote;
					}
				}

			} else {

				$proxy_options = get_post_meta( $ballot_id, WP_Vote::get_prefix( 'proxy_options' ), true );
				$proxy_other   = get_post_meta( $ballot_id, WP_Vote::get_prefix( 'proxy_other' ), true );

				$free_text = $all_voters = $allow_none = false;
				$required  = '';
				if ( is_array( $proxy_other ) ) {
					$free_text  = in_array( 'free-text', $proxy_other );
					$all_voters = in_array( 'all-voters', $proxy_other );
					$allow_none = in_array( 'allow_none', $proxy_other );
					$required   = ( in_array( 'require', $proxy_other ) ) ? 'required="required"' : '';
				}
				$have_options = is_array( $proxy_options );


				$option_html = '';

				if ( false !== $proxy_options ) {

					$option_html .= sprintf( '<select name="%1$s" id="%1$s" %2$s>', esc_attr( self::$select_id ), esc_html( $required ) );

					if ( ! empty( $required ) ) {
						$option_html .= sprintf( '<option value="">%s</option>',
							esc_attr( apply_filters( WP_Vote::get_prefix( 'proxy-default-optgroup-text' ), __( 'Select your proxy', 'wp-vote' ) ) )
						);
					}

					if ( $all_voters && $have_options ) {
						$option_html .= sprintf( '<optgroup label="%s">',
							esc_attr( apply_filters( WP_Vote::get_prefix( 'proxy-default-optgroup-text' ), __( 'Default options', 'wp-vote' ) ) )
						);
					}
					if ( $have_options ) {
						foreach ( $proxy_options as $proxy_option ) {
							$option_html .= sprintf( '<option value="%s">%s</option>', esc_attr( $proxy_option['wp_vote_proxy_name'] ), esc_html( $proxy_option['wp_vote_proxy_name'] ) );
						}
					}

					if ( $free_text ) {
						$option_html .= sprintf( '<option value="-1">%s</option>',
							esc_attr( apply_filters( WP_Vote::get_prefix( 'proxy-default-optgroup-text' ), __( 'Specify name of proxy', 'wp-vote' ) ) )
						);
					}

					if ( $allow_none ) {
						$option_html .= sprintf( '<option value="none">%s</option>',
							esc_html( apply_filters( WP_Vote::get_prefix( 'proxy-no_proxy_text' ), __( 'No Proxy', 'wp-vote' ) ) )
						);
					}

					if ( $all_voters ) {
						if ( $have_options ) {
							$option_html .= '</optgroup>';

							$option_html .= sprintf( '<optgroup label="%s">',
								esc_attr( apply_filters( WP_Vote::get_prefix( 'proxy-default-optgroup-text' ), __( 'Allowed Voters', 'wp-vote' ) ) )
							);
						}

						$all_voters_data = get_post_meta( $ballot_id, Ballot::get_prefix( 'voters' ), true );

						if ( '' === $all_voters_data ) {
							if ( is_preview() ) {
								$option_html .= sprintf( '<option>%s</option>', esc_html( __( 'Voters not listed in preview', 'wp-vote' ) ) );
							} else {
								$option_html .= sprintf( '<option>%s</option>', esc_html( __( 'No Voters Found', 'wp-vote' ) ) );
							}
						} else {
							foreach ( $all_voters_data as $voter ) {
								$option_html .= sprintf( '<option value="%s">%s</option>', esc_attr( $voter['title'] ), esc_html( $voter['title'] ) );
							}
						}
						if ( $have_options ) {
							$option_html .= '</optgroup>';
						}
					}
					$option_html .= '</select>';

					if ( $free_text ) {
						$option_html .= sprintf( '<div><input type="text" name="%1$s" id="%1$s" placeholder="%2$s" /></div>',
							esc_attr( self::$proxy_free_text ),
							esc_attr( apply_filters( WP_Vote::get_prefix( 'proxy-free-text-placeholder' ), __( 'Proxy Name', 'wp-vote' ) ) )
						);
					}


					$option_html = sprintf( '<span class="wp-vote-proxy-select">%s</span>', $option_html );
				}

				$proxy_description = get_post_meta( $ballot_id, WP_Vote::get_prefix( 'proxy_description' ), true );
				$proxy_description = sprintf( '<span class="wp-vote-proxy-desc">%s</span>', wp_kses_post( $proxy_description ) );


				if ( false !== $proxy_description ) {
					if ( strpos( $proxy_description, self::$replace_string ) ) {

						$html = str_replace( self::$replace_string, $option_html, $proxy_description );
					} else {

						$html = $proxy_description . ' ' . $option_html;
					}
				}

				if ( $free_text ) {
					$html .= sprintf( '
						<script type="application/javascript">
							if ( "-1" !== jQuery(\'#%2$s\').val() ) {
								jQuery(\'#%1$s\').hide();
							}
							jQuery(\'#%2$s\').on( \'change\', function(e){
								var $input = jQuery(\'#%1$s\');
								if( \'-1\' === jQuery( this ).val() ){
									$input.show()
								} else {
									$input.hide()
								}

							});
						</script>',
						esc_js( self::$proxy_free_text ),
						esc_js( self::$select_id )
					);
				}

			}
			$html = sprintf( apply_filters( WP_Vote::get_prefix( 'proxy-default-optgroup-text_wrap' ), '<div id="wp-vote-proxy-wrap">%s</div>' ), $html );
			if ( ! $echo ) {

				return $html;
			}

			echo $html;
		}

		return true;
	}

	/**
	 *
	 *
	 * @static
	 */
	public static function show_proxy_stats() {
		self::$cmb_proxy = new_cmb2_box( array(
			'id'           => WP_Vote::get_prefix( 'proxy_metabox' ),
			'title'        => __( 'Proxy votes', 'wp_vote' ),
			'object_types' => array( Ballot::get_post_type() ), // Post type
			'context'      => 'normal',
			'priority'     => 'high',
			'show_names'   => true, // Show field names on the left
		) );

		// Questions
		self::$cmb_proxy->add_field( array(
			'name'             => 'Ballot Questions',
			'desc'             => '',
			'id'               => WP_Vote::get_prefix( '_proxy' ),
			'type'             => 'XX_proxy_stats',
			'show_option_none' => false,
			'default'          => 'custom',
			'show_names'       => false, // Show field names on the left
			'after_field'      => array( __CLASS__, 'display_proxy_stats' ),
		) );
	}

	/**
	 *
	 *
	 * @static
	 */
	public static function display_proxy_stats() {

		echo 'Not coded yet: The list of proxy votes save will show here';
	}


	/**
	 * add proxy if to the email meassage
	 *
	 * @static
	 *
	 * @param $message
	 * @param $voter_id
	 * @param $rep_index
	 * @param $rep_email
	 * @param $rep_name
	 * @param $ballot_id
	 * @param $ballot_status
	 */
	public static function email_representative_message( $message, $voter_id, $rep_index, $rep_email, $rep_name, $ballot_id, $ballot_status ) {

		//	die('email_representative_message');
		return $message;
	}

	/**
	 * add the totals to the question data
	 *
	 * @static
	 *
	 * @param $questions
	 * @param $ballot_id
	 */
	public static function questions_to_be_saved( $questions ) {

		if ( ! isset( $questions['proxy'] ) ) {
			$questions['proxy'] = array();
		}

		if ( isset( $_POST[ self::$select_id ] ) ) {

			$proxy = ( isset( $_POST[ self::$proxy_free_text ] ) && ! empty( $_POST[ self::$proxy_free_text ] ) && '-1' === $_POST[ self::$select_id ] ) ?
				sanitize_text_field( wp_unslash( $_POST[ self::$proxy_free_text ] ) ) : sanitize_text_field( wp_unslash( $_POST[ self::$select_id ] ) );

			if ( ! isset( $questions['proxy'][ $proxy ] ) ) {
				$questions['proxy'][ $proxy ] = 1;
			} else {
				$questions['proxy'][ $proxy ] = $questions['proxy'][ $proxy ] + 1;
			}
		}

		return $questions;
	}

	/**
	 * add the proxy details to the voter
	 *
	 * @static
	 *
	 * @param $votes
	 * @param $ballot_id
	 */
	public static function votes_to_be_saved( $votes ) {

		if ( isset( $_POST[ self::$select_id ] ) ) {

			$votes['proxy'] = ( isset( $_POST[ self::$proxy_free_text ] ) && ! empty( $_POST[ self::$proxy_free_text ] ) && '-1' === $_POST[ self::$select_id ] ) ?
				sanitize_text_field( wp_unslash( $_POST[ self::$proxy_free_text ] ) ) : sanitize_text_field( wp_unslash( $_POST[ self::$select_id ] ) );

		}

		return $votes;
	}


	/**
	 * @param $ballot_id
	 */
	public static function validate( $ballot_id ) {
		$proxy_other = get_post_meta( $ballot_id, WP_Vote::get_prefix( 'proxy_other' ), true );

		$free_text  = in_array( 'free-text', $proxy_other );
		$allow_none = in_array( 'allow_none', $proxy_other );
		$required   = ( in_array( 'require', $proxy_other ) ) ? 'required="required"' : '';

		// do we care
		if ( $required && ! $allow_none ) {
			// do we have a select value
			if (
				(
					isset( $_POST[ self::$select_id ] ) &&
					! empty( $_POST[ self::$select_id ] )
				) ||
				// is the select set to -1 and allow none is true then do have a value in the free text
				(
					'-1' === $_POST[ self::$select_id ] &&
					$free_text &&
					isset( $_POST[ self::$proxy_free_text ] ) &&
					! empty( $_POST[ self::$proxy_free_text ] )
				)
			) {

				return true;
			} else {
				die( 'Falied' );
				$orig_url = $_POST['_wp_http_referer'];
				wp_safe_redirect( add_query_arg( array( 'state' => 'failed' ), $orig_url ) );
				//	die();
			}
		}

		return true;
	}

	public static function show_none_question( $question_index, $question ) {

		if ( 'proxy' === $question_index && is_admin() ) {
			esc_html_e( 'Proxy Votes', 'wp-vote' );
			foreach ( $question as $name => $proxy_count ) {
				printf(
					apply_filters( WP_Vote::get_prefix( 'proxy-show-none-question-text_wrap' ), '<li class="clearfix" ><span><strong>%s</strong> %s</span><h4>%s</h4></li>' ),
					esc_html( apply_filters( WP_Vote::get_prefix( 'proxy-show-none-question-count-text' ), 'Delegated:' ) ),
					esc_html( $proxy_count ),
					esc_html( $name )
				);
			}
		}
	}

	public static function show_not_question_filter( $html, $question_index, $question, $answers ) {

		if ( 'proxy' === $question_index  && isset( $answers[ $question_index ] ) ) {
			$html .= esc_html__( 'Proxy Vote', 'wp-vote' );
				$html .= sprintf(
					apply_filters( WP_Vote::get_prefix( 'proxy-show-none-question-filter-text_wrap' ), '<li class="clearfix" ><h4>%s %s</h4></li>' ),
					esc_html( apply_filters( WP_Vote::get_prefix( 'proxy-show-none-question-filter-count-text' ), 'Delegated to:' ) ),
					esc_html( $answers[ $question_index ] )
				);
		}

		return $html;
	}
}