<?php

namespace WP_Vote;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Wp_Vote_Ballot_Admin
 * @package WP_Vote
 */
class Wp_Vote_Ballot_Admin {

	/**
	 * Wp_Vote_Ballot_Admin constructor.
	 */
	public function __construct() {
		add_filter( 'title_save_pre', array( __CLASS__, 'title_save_pre' ) );

		add_filter( 'post_date_column_status', array( __CLASS__, 'post_date_column_status' ), 10, 2 );

		add_filter( 'manage_posts_columns', array( __CLASS__, 'columns_head' ) );
		add_action( 'manage_posts_custom_column', array( __CLASS__, 'columns_content' ), 10, 2 );
	}


	/**
	 * @param $title
	 *
	 * @return mixed
	 */
	public static function title_save_pre( $title ) {

		if ( is_admin() && 'wp-vote-voter' ===  get_post_type() && '' == $title ) {
			Admin_Notices::display_error( __( 'Title Required', 'wp-vote' ) );
			wp_safe_redirect( esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) );
			die();
		}

		return $title;
	}


	/**
	 * add column to listing page
	 *
	 * @static
	 *
	 * @param $defaults
	 *
	 * @return mixed
	 */
	public static function columns_head( $defaults ) {
		$date = $defaults['date'];
		unset( $defaults['date'] );
		$defaults['votes'] = __( 'Votes/Pending', 'wp-vote' );
		$defaults['date']  = $date;

		return $defaults;
	}

	public static function columns_content( $column_name, $post_ID ) {
		if ( $column_name == 'votes' ) {
			$pending = 0;
			$voters  = get_post_meta( $post_ID, Ballot::get_prefix( 'voters' ), true );

			if ( ! is_array( $voters ) ) {

				esc_html_e( 'Draft' );

				return;
			}
			$total_voters = count( $voters );
			foreach ( $voters as $voter ) {

				if ( ! empty( $voter['voted'] ) ) {
					$pending ++;
				}
			}

			$percent = $pending / $total_voters * 100;
			echo esc_html( $pending . '/' . $total_voters . ' (' . $percent . '%)' );
		}

	}

	/**
	 * @param $status
	 * @param $post
	 * @param $column
	 * @param $mode
	 */
	public static function post_date_column_status( $status, $post ) {

		switch ( $post->post_type ) {
			case Ballot::get_post_type();

				switch ( Ballot::get_ballot_status( $post->ID ) ) {
					case Ballot::STATUS_OPEN:
						$status = __( 'Ballot Open', 'wp-vote' );
						break;
					case Ballot::STATUS_CLOSED:
						$status = __( 'Ballot Closed', 'wp-vote' );
						break;
				}
				break;
			case  Voter::get_post_type():

				if ( 'publish' === $post->post_status ) {
					$status = __( 'Voter Created', 'wp-vote' );
				}

				break;
		}

		return $status;
	}
}
