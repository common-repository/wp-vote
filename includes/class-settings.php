<?php
/**
 * Created by IntelliJ IDEA.
 * User: Paul
 * Date: 11/16/2015
 * Time: 9:57 AM
 */

namespace WP_Vote;

//test for CMB2 plugin befload local

if ( ! class_exists( 'CMB2_Bootstrap_212' ) ) {
	require_once plugin_dir_path( dirname( __FILE__ ) ) . 'third-party/cmb/init.php';
};

/**
 * Class Settings
 * @package WP_Vote
 */
class Settings {
	/**
	 * @var
	 */
	private static $options_nonce_key;

	/**
	 * Option key, and option page slug
	 * @var string
	 */
	private static $key;

	/**
	 * Array of metaboxes/fields
	 * @var array
	 */
	protected static $option_metabox = array();

	/**
	 * @var
	 */
	private static $fields;

	/**
	 * @var
	 */
	private static $wp_vote_ballot_options ;


	/**
	 * Settings constructor.
	 */
	public function __construct() {

		self::$options_nonce_key;
		self::$key = Ballot::get_prefix( 'options' );


		add_action(  Ballot::get_prefix( 'options_footer' ), function () {
			echo '<span style="float:right; padding: 10px 20px;">Wp Vote Version: ' . WP_Vote::get_version() . '</span>';
		} );

		// Set our CMB fields

		$this->hooks();
	}

	/**
	 * Initiate our hooks
	 * @since 0.1.0
	 */
	public function hooks() {
		add_action( 'admin_init', array( __CLASS__, 'init' ) );
	//	add_action( 'admin_menu', array( __CLASS__, 'add_options_page' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );

		//add_action( 'wp-vote_option_post_menu', array( 'WP_Vote\Import', 'menu' ) );
		if( isset( $_POST[ WP_Vote::get_prefix( 'licence_key' ) ] ) ) {

			self::activate_licenses( $_POST[ WP_Vote::get_prefix( 'licence_key' ) ] );
        }
	}


	/**
	 * Register our setting to WP
	 * @since  0.1.0
	 */
	public static function init() {
		register_setting( self::$key, self::$key );
	}

	/**
	 *
	 */
	public static function menu() {

		$current_user    = wp_get_current_user();
		$roles           = $current_user->roles;
		$allowed_roles   = self::get_option_value( 'allowed_roles' );
		$allowed_roles[] = 'administrator';

		$authorized = (bool) array_intersect( $roles, $allowed_roles );

		if ( ! $authorized ) {
			return false;
		}

		add_menu_page(
			__( 'WP Vote Settings', 'wp-vote' ),
			__( 'WP Vote', 'wp-vote' ),
			'edit_posts',
			'wp-vote',
			array( __CLASS__, 'options_page' ),
			'dashicons-yes'
		);

		add_submenu_page(
			'wp-vote',
			__( 'WP Vote Settings', 'wp-vote' ),
			__( 'Options', 'wp-vote' ),
			'manage_options',
			'wp-vote-options',
			array( __CLASS__, 'options_page' )
		);

	//	if ( isset( $_GET['page'] ) ) {

		if ( isset( $_GET['page'] ) && 'wp-vote-options' !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
		//	remove_submenu_page( 'wp-vote', 'wp-vote-options' );
		}
	//	}

		//do_action( 'wp-vote_option_post_menu' );


		//edit.php?post_type=courses
		// add_submenu_page(
		// 	'wp-vote',
		// 	__( 'WP Vote Settings', 'wp-vote' ),
		// 	__( 'Add on\'s', 'wp-vote' ),
		// 	'manage_options',
		// 	'addons',
		// 	array( __CLASS__, 'addon_page' )
		// );
//		remove from menu
		// if( ! isset( $_GET['page'] ) || 'addons' !== sanitize_key( wp_unslash( $_GET['page'] ) ) ){
		// 	remove_submenu_page( 'wp-vote', 'addons' );
		// }


		add_submenu_page(
			'wp-vote',
			__( 'Upgrade WP Vote to Pro', 'wp-vote' ),
			__( 'Get Pro', 'wp-vote' ),
			'manage_options',
			'pro',
			array( __CLASS__, 'pro_page' )
		);

	}

	/**
	 *
	 */
	public static function options_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'wp_vote' ) );
		}
		self::load_tabs();

//		$intro = get_option( Config::get_archive_intro_option_key() );
//		$title = get_option( Config::get_archive_title_option_key() );

		echo '<div class="wrap">';
		?>
		<h2><?php _e( 'Addon Plugin License Options' ); ?></h2>
		<?php \cmb2_metabox_form( self::option_metabox(), self::$key ); ?>
		<?php

		echo '</div>';
		do_action(  Ballot::get_prefix( 'options_footer' ) );
	}


	/**
	 * Method: Activate Licenses
	 *
	 * Called on save of a settings page (form data).
	 * Don't call if directly saving one setting.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function activate_licenses( $license_key ) {


					$license_data = null;

					$params = array(
						'edd_action' => 'activate_license',
						'license'    => $license_key,
						'item_id'    => WP_VOTE_UPDATER_ID,
						'url'        => home_url(),
					);

					$response = wp_remote_post( WP_VOTE_UPDATER_URL, array(
						'timeout'   => 15,
						'sslverify' => false,
						'body'      => $params,
					) );

					// make sure the response came back okay
					if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {

						$message = ( is_wp_error( $response ) && ! empty( $response->get_error_message() ) ) ? $response->get_error_message() : esc_html__( 'An error occurred, please try again.', 'matador-jobs' );

					} else {

						$license_data = json_decode( wp_remote_retrieve_body( $response ) );
						if ( false === $license_data->success ) {
							switch ( $license_data->error ) {
								case 'expired':
									// translators: Placeholder contains date license expired.
									$message = sprintf( esc_html__( 'Your license key expired on %s.', 'matador-jobs' ), date_i18n( get_option( 'date_format' ), strtotime( $license_data->expires, current_time( 'timestamp' ) ) ) );
									break;
								case 'revoked':
									$message = esc_html__( 'Your license key has been disabled.', 'matador-jobs' );
									break;
								case 'missing':
									$message = esc_html__( 'You provided an invalid license key. Check to make sure you entered the correct key.', 'matador-jobs' );
									break;
								case 'invalid':
								case 'site_inactive':
									$message = esc_html__( 'Your license is not active for this URL.', 'matador-jobs' );
									break;
								case 'item_name_mismatch':
									$message = esc_html__( 'This appears to be an invalid license key.', 'matador-jobs' );
									break;
								case 'no_activations_left':
									$message = esc_html__( 'Your license key has reached its activation limit.', 'matador-jobs' );
									break;
								default:
									$message = esc_html__( 'An error occurred, please try again.', 'matador-jobs' );
									break;
							}
						}
					}

					if ( ! empty( $message ) ) {
						Admin_Notices::display_error( $message );
					} elseif ( isset( $license_data->license ) ) {
						update_option( WP_Vote::get_prefix( 'licence_status' ), $license_data->license );
						Admin_Notices::display_success( esc_html__( 'Your license was activated.', 'matador-jobs' ) );
						return true;
					}

		return false;
	}


	/**
	 * Defines the theme option metabox and field configuration
	 * @since  0.1.0
	 * @return array
	 */
	public static function option_metabox() {
		return array(
			'id'         => WP_Vote::get_prefix( 'option_metabox' ),
			'show_on'    => array( 'key' => 'options-page', 'value' => array( self::$key ) ),
			'show_names' => true,
			'fields'     => self::option_metabox_values(),
		);
	}

	/**
	 *
	 *
	 * @static
	 * @return mixed
	 */
	private static function option_metabox_values() {
		global $wp_roles;
		$roles = $wp_roles->get_names();
		unset( $roles['administrator'] );

		$key_desc = ( 'valid' === get_option( WP_Vote::get_prefix( 'licence_status' ) ) )? __( 'Valid Key', 'wp_vote' ) : __( 'Enter a Valid Key', 'wp_vote' );



		/**
		 * Allows you to override the options on the settings page
		 *
		 * @static
		 * @array default settings
		 */
		return apply_filters( 'wp-vote_option_metabox_values',
			array(
// 				array(
// 					'name'        => __( 'Licence Key', 'wp_vote' ),
// 					'desc'        => $key_desc,
// 					'id'          => WP_Vote::get_prefix( 'licence_key' ),
// 					'type'        => 'text',
// //					'after_field' => array( __CLASS__, 'show_email_from_notes' ),
// 				),


				array(
					'name'              => __( 'Allow access to ballots by other roles', 'wp_vote' ),
					'desc'              => __( 'Select which roles can create, edit and delete ballots and voters. Administrators can always access ballots.', 'wp_vote' ),
					'id'                => 'allowed_roles',
					'type'              => 'multicheck',
					'select_all_button' => false,
					'options'           => $roles,
				),

				array(
					'name'        => __( 'From Email name', 'wp_vote' ),
					'desc'        => __( 'The Name to used for emails send to voters.', 'wp_vote' ),
					'id'          => WP_Vote::get_prefix( 'from_name' ),
					'type'        => 'text',
					'after_field' => array( __CLASS__, 'show_email_from_notes' ),
				),
				array(
					'name'        => __( 'From Email address', 'wp_vote' ),
					'desc'        => __( 'The email address to used for emails send to voters..', 'wp_vote' ),
					'id'          => WP_Vote::get_prefix( 'from_email' ),
					'type'        => 'text_email',
					'after_field' => array( __CLASS__, 'show_email_from_notes' ),
				),
				array(
					'name'        => __( 'Subject: Vote open', 'wp_vote' ),
					'desc'        => __( 'The subject line to use when opening an vote', 'wp_vote' ),
					'id'          => WP_Vote::get_prefix( 'email_subject_open' ),
					'type'        => 'text',
					'after_field' => array( __CLASS__, 'show_email_subject_shortcodes' ),
				),
				array(
					'name'        => __( 'Email template: Vote open', 'wp_vote' ),
					'desc'        => __( 'The message sent when the ballot is opened', 'wp_vote' ),
					'id'          => WP_Vote::get_prefix( 'email_template_open' ),
					'type'        => 'wysiwyg',
					'default'     => self::default_email_templates( 'open' ),
					'options'     => array(
						'wpautop'       => true, // use wpautop?
						'media_buttons' => false, // show insert/upload button(s)
						'textarea_rows' => 12, // rows="..."
					),
					'after_field' => array( __CLASS__, 'show_email_template_shortcodes' ),
				),
				array(
					'name'        => __( 'Subject: Vote reminder', 'wp_vote' ),
					'desc'        => __( 'The subject line to use when sending a reminder', 'wp_vote' ),
					'id'          => WP_Vote::get_prefix( 'email_subject_remind' ),
					'type'        => 'text',
					'after_field' => array( __CLASS__, 'show_email_subject_shortcodes' ),
				),
				array(
					'name'        => __( 'Email template: Vote reminder', 'wp_vote' ),
					'desc'        => __( 'The message sent when the ballot when a reminder is sent', 'wp_vote' ),
					'id'          => WP_Vote::get_prefix( 'email_template_remind' ),
					'type'        => 'wysiwyg',
					'default'     => self::default_email_templates( 'remind' ),
					'options'     => array(
						'wpautop'       => true, // use wpautop?
						'media_buttons' => false, // show insert/upload button(s)
						'textarea_rows' => 12, // rows="..."
					),
					'after_field' => array( __CLASS__, 'show_email_template_shortcodes' ),
				),
				array(
					'name'        => __( 'Email votes', 'wp_vote' ),
					'desc'        => __( 'When checked we will email a copy of the votes cast via email', 'wp_vote' ),
					'id'          => WP_Vote::get_prefix( 'email_votes' ),
					'type'        => 'checkbox',
				),
				array(
					'name'        => __( 'Bcc Vote Notifications', 'wp_vote' ),
					'desc'        => __( 'Bcc the following comma separated addresses whenever a vote is cast.', 'wp_vote' ),
					'id'          => WP_Vote::get_prefix( 'bcc_email' ),
					'type'        => 'text',
					//'after_field' => array( __CLASS__, 'show_email_from_notes' ),
				),
				array(
					'name'        => __( 'Subject: Voted message', 'wp_vote' ),
					'desc'        => __( 'The subject line to use when sending the conformation of the vote', 'wp_vote' ),
					'id'          => WP_Vote::get_prefix( 'email_subject_voted' ),
					'type'        => 'text',
					'after_field' => array( __CLASS__, 'show_email_subject_shortcodes' ),
				),
				array(
					'name'        => __( 'Email template: Vote confirmed', 'wp_vote' ),
					'desc'        => __( 'The message sent when the votes is saves ', 'wp_vote' ),
					'id'          => WP_Vote::get_prefix( 'email_template_voted' ),
					'type'        => 'wysiwyg',
					'default'     => self::default_email_templates( 'voted' ),
					'options'     => array(
						'wpautop'       => true, // use wpautop?
						'media_buttons' => false, // show insert/upload button(s)
						'textarea_rows' => 12, // rows="..."
					),
					'after_field' => array( __CLASS__, 'show_email_template_shortcodes' ),
				),
				array(
					'name'        => __( 'Send plain text email', 'wp_vote' ),
					'desc'        => __( 'When checked we will email will be sent as plain text', 'wp_vote' ),
					'id'          => WP_Vote::get_prefix( 'email_type' ),
					'type'        => 'checkbox',
				),
			)
		);
	}


	/**
	 *
	 *
	 * @static
	 *
	 * @param null $type
	 *
	 * @return mixed
	 */
	public static function default_email_templates( $type = null ) {

		$messages['open'] = sprintf( '<p>%s</p> <p>%s</p> <p>%s</p> <p>%s</p> ',
			__( 'Hi {rep_name}', 'wp-vote' ),
			__( 'You are invited to vote on the behalf of {voter_name} in the {ballot_title} ballot', 'wp-vote' ),
			__( 'Voting has now opened. To cast your vote please click the following link:', 'wp-vote' ),
			__( '{ballot_link}', 'wp-vote' )
		);

		$messages['remind'] = sprintf( '<p>%s</p> <p>%s</p> <p>%s</p> <p>%s</p> ',
			__( 'Hi {rep_name}', 'wp-vote' ),
			__( 'This is a reminder to vote on the behalf of {voter_name} in the {ballot_title} ballot', 'wp-vote' ),
			__( 'Voting is still open. To cast your vote please click the following link:', 'wp-vote' ),
			__( '{ballot_link}', 'wp-vote' )
		);

		$messages['voted'] = sprintf( '<p>%s</p> <p>%s</p> <p>%s</p> <p>%s</p> ',
			__( 'Hi {rep_name}', 'wp-vote' ),
			__( '{voted_name} voted on the behalf of {voter_name} in {ballot_title} ballot', 'wp-vote' ),
			__( 'This a copy of your vote for your records.', 'wp-vote' ),
			__( '{vote_record}', 'wp-vote' )
		);

		return ( null === $type ) ? $messages : $messages[ $type ];
	}


	/**
	 *
	 *
	 * @static
	 *
	 * @param string $key
	 *
	 * @return string
	 */
	public static function get_option_value( $key = '' ) {
	    $value = \cmb2_get_option( self::$key, $key );
	    if( is_array( $value ) ){

	        return $value[0];
        }

		return $value;
	}

	/**
	 *
	 *
	 * @static
	 *
	 * @param $object
	 */
	public static function show_email_template_shortcodes( $object ) {

		switch ( $object['id'] ) {
			case WP_Vote::get_prefix( 'email_template_voted' ):
				echo( '<i>The follow shortcodes can used in the template: {rep_name}, {rep_first_name}, {rep_last_name}, {voted_name}, {voter_name}, {ballot_title}, {vote_record}</i>' );
				break;
			default:
				echo( '<i>The follow shortcodes can used in the template: {rep_name}, {rep_first_name}, {rep_last_name}, {voter_name}, {ballot_title}, {ballot_link}</i>' );
				break;
		}
	}

	/**
	 *
	 *
	 * @static
	 *
	 */
		public static function show_email_subject_shortcodes() {

			echo( '<i>The follow shortcodes can used in the template: {rep_name}, {rep_first_name}, {rep_last_name}, {voter_name}, {ballot_title}</i>' );
		}

	/**
	 *
	 *
	 * @static
	 */
	public static function show_email_from_notes() {

		echo( '<i>Make sure the email is from the same domain as your website to avoid being marked as spam.</i>' );

	}

	/**
	 *
	 */
	public static function addon_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'wp_vote' ) );
		}
		self::load_tabs();
		echo '<div class="wrap">';
		printf( '<p>%s</p>', __( 'Add a plugin to you site for extra functions', 'wp-vote' ) );

		$add_on_data = self::get_addon_data();

		if ( false !== $add_on_data ) {
			foreach ( $add_on_data->products as $product ) {

				if ( null === $product ) {
					continue;
				}
				// TODO: un-rem to not show main product
//			if ( 'wp_vote' === $product->info->slug ) {
//				continue;
//			}

				printf(
					'<div class="pwep_addon_prod"><img src="%s" /><h4>%s</h4><p>%s</p><a href="%s" target="_blank" class="button-secondary" >%s</a> </div>',
					esc_url( $product->info->thumbnail ),
					esc_html( $product->info->title ),
					esc_html( ( ! empty( $product->info->excerpt ) ) ? $product->info->excerpt : $product->info->content ),
					esc_url( $product->info->link ),
					__( 'Details', WP_Vote::SLUG, 'wp_vote' )
				);
			}
		} else {
			printf( '<p>%s</p>', __( 'Failed to fetch the list of add on\'s ', WP_Vote::SLUG, 'wp_vote' ) );
		}

		echo '</div>';
	}


	public static function pro_page(){

		printf( '<h1>%s</h1>', __( 'Upgrade to WP Vote Pro', WP_Vote::SLUG, 'wp_vote' ) );

		echo '<div class="wrap">';
		printf( '<p>%s</p>', __( 'We have a pro version has more voter and question types(including custom questions) Plus the import of voters and export of the results.',WP_Vote::SLUG,  'wp-vote' ) );
		printf( '<p>%s</p>', __( 'At somepoint we will get the website all setup at http://wp-vote.com/ but in the meantime email paul@bearne.ca.',WP_Vote::SLUG,  'wp-vote' ) );
		printf( '<p>%s</p>', __( 'And we will provde the full version and support.',WP_Vote::SLUG,  'wp-vote' ) );
		printf( '<p>%s</p>', __( 'All Pro support license will help support the development of the plugin.',WP_Vote::SLUG,  'wp-vote' ) );
		printf( '<p>%s</p>', __( 'Many thanks for trying WP vote.',WP_Vote::SLUG,  'wp-vote' ) );
		printf( '<p>%s</p>', __( 'The WP vote team Paul and Peter.',WP_Vote::SLUG,  'wp-vote' ) );

		echo '</div>';
	}

	/**
	 * @param      $tabs
	 * @param null $current
	 *
	 * @return string
	 */
	private static function admin_tabs( $tabs, $current = null ) {
		if ( is_null( $current ) ) {
			if ( isset( $_GET['page'] ) ) {
				$current = $_GET['page'];
			}
		}
		$content = '<div id="icon-themes" class="icon32"><br></div><h2 class="nav-tab-wrapper">';
		foreach ( $tabs as $location => $tabname ) {
			if ( $current == $location ) {
				$class = ' nav-tab-active';
			} else {
				$class = '';
			}
			$content .= '<a class="nav-tab' . $class . '" href="?page=' . $location . '">' . $tabname . '</a>';
		}
		$content .= '</h2>';

		return $content;
	}

	/**
	 *
	 */
	public static function load_tabs() {
		$my_plugin_tabs = apply_filters( 'wp_vote_option_tabs', array( 'wp-vote-options' => 'Settings' ) );
		printf( '<h1>%s</h1>', __( 'WP Vote Options', WP_Vote::SLUG, 'wp_vote' ) );
		// TODO: make set for adds and re-able
		//	$my_plugin_tabs['addons'] = 'Available Add-on\'s';
		echo self::admin_tabs( $my_plugin_tabs );
	}


	/**
	 * @return array
	 */
	private static function get_addon_data() {
		$transient_key = 'wp_vote_option_product_data';

		$data = get_transient( $transient_key );
		if ( false === $data || empty( $data ) ) {
			$data      = array();
			$store_url = 'http://wp-vote.com/edd-api/products/';

			$response = wp_remote_post( $store_url, array( 'timeout' => 15, 'sslverify' => false ) );

			if ( is_wp_error( $response ) || 200 !== $response['response']['code'] ) {
				return false;
			}
			$data = json_decode( wp_remote_retrieve_body( $response ) );
			set_transient( $transient_key, $data, HOUR_IN_SECONDS * 1 );
		}

		return $data;
	}

	/**
	 *
	 *
	 * @static
	 *
	 * @param string $content_type
	 *
	 * @return string
	 */
	public static function set_content_type( $content_type ) {

	    if( 'on' !== self::get_ballot_options(  WP_Vote::get_prefix( 'email_type' ) ) ) {

	        return 'text/html';
        }

        return $content_type;
	}


	/**
	 * get the email subject
	 *
	 * @static
	 *
	 * @param $original_email_subject
	 * @param $type string - voted, remind, open
	 *
	 * @return string
	 * @internal param $original_email_address
	 *
	 */
	public static function custom_wp_mail_subject( $original_email_subject, $type ) {

	    $maybe_email_subject = self::get_ballot_options( WP_Vote::get_prefix( 'email_subject_' . $type ) );

		if( false !== $maybe_email_subject ){

			return sanitize_text_field( $maybe_email_subject );
		}

		return $original_email_subject;
	}

	/**
	 *
	 *
	 * @static
	 *
	 * @param $original_email_address
	 *
	 * @return string
	 */
	public static function custom_wp_mail_from( $original_email_address ) {

		$maybe_email = self::get_ballot_options( WP_Vote::get_prefix( 'from_email') );

		if( false !== $maybe_email ){

			return sanitize_email( $maybe_email );
		}

		return $original_email_address;
	}

	/**
	 *
	 *
	 * @static
	 *
	 * @param $original_email_name
	 *
	 * @return string
	 * @internal param $original_email_address
	 *
	 */
	public static function wp_mail_from_name( $original_email_name ) {

		$maybe_email_name = self::get_ballot_options( WP_Vote::get_prefix( 'from_name' ) );

		if( false !== $maybe_email_name ){

			return sanitize_text_field( $maybe_email_name );
		}

		return $original_email_name;
	}

	/**
	 * @param $name
	 *
	 * @return bool
	 */
	private static function get_ballot_options( $name ){

		if ( null === self::$wp_vote_ballot_options){
			self::$wp_vote_ballot_options = get_option( Ballot::get_prefix( 'options' ) );
		}

		if ( isset( self::$wp_vote_ballot_options[ $name ] ) ){

			return self::$wp_vote_ballot_options[ $name ];
		}

		return false;
	}
}