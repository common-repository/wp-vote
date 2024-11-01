<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       http://bearne.ca
 * @since      1.0.0
 *
 * @package    WP_Vote
 * @subpackage WP_Vote/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    WP_Vote
 * @subpackage WP_Vote/includes
 * @author     Paul Bearne, Peter Toi <paul@bearne.ca>
 */

namespace WP_Vote;

class WP_Vote {

	const SLUG = 'wp_vote';
	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string $plugin_name The string used to uniquely identify this plugin.
	 */
	static protected $plugin_name;


	static public $settings;
	static public $settings_import;
	static public $admin_ballot_ajax;
	static public $admin_voter_ajax;
	static public $ballot_admin;
	static public $admin_notices;
	static public $ballot_proxy;


	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string $version The current version of the plugin.
	 */
	static protected $version;

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	static public function get_plugin_name() {
		return self::$plugin_name;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	static public function get_version() {
		return self::$version;
	}

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      WP_Vote_Loader $loader Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 *
	 * @param $args
	 */
	public function __construct( $args ) {

		self::$plugin_name = $args['plugin_name'];
		self::$version     = $args['version'];

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();

	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - WP_Vote_Loader. Orchestrates the hooks of the plugin.
	 * - WP_Vote_i18n. Defines internationalization functionality.
	 * - WP_Vote_Admin. Defines all hooks for the admin area.
	 * - WP_Vote_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-vote-loader.php';

		$this->loader = new WP_Vote_Loader();

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-vote-i18n.php';

		/**
		 * Load CMB2 core and any additional field types
		 */
		if ( ! class_exists( 'CMB2_Bootstrap_212' ) ) {
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'third-party/cmb/init.php';
		};
		if ( ! class_exists( 'WDS_CMB2_Attached_Posts_Field' ) ) {
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'third-party/cmb2-attached-posts/cmb2-attached-posts-field.php';
		};

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-wp-vote-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-wp-vote-public.php';

		$this->loader->add_filter( 'plugins_loaded', __NAMESPACE__ . '\\Ballot', 'plugins_loaded' );
		$this->loader->add_filter( 'plugins_loaded', __NAMESPACE__ . '\\Voter', 'plugins_loaded' );


		//	$this->loader->add_filter( 'init', __NAMESPACE__ . '\\Email_Address_Voter', 'init' );
		// TODO: re-add 'Email_Address_Voter' and move over ride into sub plugin
		foreach ( self::get_active_voter_types() as $voter_types ) {
			$this->loader->add_filter( 'init', __NAMESPACE__ . '\\' . $voter_types, 'init' );
		}


		foreach ( self::get_active_question_types() as $question_types ) {
			$this->loader->add_filter( 'init', __NAMESPACE__ . '\\' . $question_types, 'init' );
		}

		$this->loader->add_filter( 'admin_notices', __NAMESPACE__ . '\\Admin_Notices', 'display_admin_notice' );


		$this->public = new WP_Vote_Public();

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-question.php';


		self::$settings = new Settings();

//		self::$settings_import = new Settings_Import();

		self::$admin_ballot_ajax = new Ballot_Ajax();
		self::$admin_voter_ajax  = new Voter_Ajax();

		self::$ballot_admin = new Wp_Vote_Ballot_Admin();
		self::$ballot_proxy = new Wp_Vote_Ballot_proxy();

	}

	/**
	 * Returns the list of Question types.
	 *
	 * each Item in the array is the class that provides the question support
	 *
	 * @since    1.0.1
	 * @access   public
	 *
	 * @return array
	 */
	public function get_active_question_types() {
		/*
		 * Filters the question types class's to be loaded
		 *
		 * @param array $classes the list of class names to be loaded
		 * @since    1.0.1
		 */
		return apply_filters( 'wp-vote-allowed-question-types',
			array(
				'For_Withhold_Question',
				'For_Against_Question',
				'For_Against_Abstain_Question',
//				'Yes_No_Question',
//				'Yes_No_Abstain_Question',
//				'Custom_Question',
//			'Open_Text_Question', // TODO: get working
			)
		);
	}

	/**
	 * Returns the list of Voter types.
	 *
	 * each Item in the array is the class that provides the question support
	 *
	 * @since    1.0.1
	 * @access   public
	 *
	 * @return array
	 */
	public function get_active_voter_types() {
		/*
		 * Filters the votes types class's to be loaded
		 *
		 * @param array $classes the list of class names to be loaded
		 * @since    1.0.1
		 */
		return apply_filters( 'wp-vote-allowed-voter-types',
			array(
				'Basic_Individual_Voter',
				'Single_Rep_Organization_Voter',
//				'Single_Split_Name_Rep_Organization_Voter',
////				'Dual_Rep_Organization_Voter',
//				'Dual_Split_Name_Rep_Organization_Voter',
			)
		);
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the WP_Vote_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new WP_Vote_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$this->loader->add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\WP_Vote_Admin', 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\WP_Vote_Admin', 'enqueue_scripts' );

		$this->loader->add_action( 'admin_menu', __NAMESPACE__ . '\\WP_Vote_Admin', 'admin_menu' );

		$this->loader->add_action( 'admin_init', __NAMESPACE__ . '\\WP_Vote', 'wp_vote_addon_edd_plugin_updater' );
	}

	public static function wp_vote_addon_edd_plugin_updater() {


		if ( ! class_exists( '\EDD_SL_Plugin_Updater' ) ) {
			require_once dirname( dirname( __FILE__ ) ) . '/third-party/edd/class-edd-sl-plugin-update.php';

			// retrieve our license key from the DB
			$options     = get_option( Ballot::get_prefix( 'options' ) );

			if ( isset( $options[ WP_Vote::get_prefix( 'licence_key' ) ] ) ) {
				$license_key = trim( $options[ WP_Vote::get_prefix( 'licence_key' ) ] );

				// setup the updater
				$edd_updater = new \EDD_SL_Plugin_Updater( WP_VOTE_UPDATER_URL, __FILE__, array(
						'version'   => self::get_version(),
						// current version number
						'license'   => $license_key,
						// license key (used get_option above to retrieve from DB)
						'item_name' => self::get_plugin_name(),
						// name of this plugin
						'author'    => 'Paul Bearne',
						// author of this plugin
					)
				);

			}
		}
	}


	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		$this->loader->add_action( 'wp_enqueue_styles', __NAMESPACE__ . '\\WP_Vote_Public', 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\WP_Vote_Public', 'enqueue_scripts' );

	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    WP_Vote_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	public static function get_prefix( $append = '' ) {
		return join( '_', array( static::SLUG, sanitize_title( $append ) ) );
	}

	public static function _get_prefix( $append = '' ) {
		return '_' . self::get_prefix( $append );
	}

}
