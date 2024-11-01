<?php
/**
 * wp-vote.
 * User: Peter
 * Date: 2016-05-01
 *
 */

namespace WP_Vote;

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Voter_Ajax extends Voter {

	public function __construct() {

		add_action( 'wp_ajax_export_voters_to_csv', array(
			__CLASS__,
			'export_voters_to_csv_callback'
		) );
	}

	public static function export_voters_to_csv_callback() {

		global $post;

// Setup the response meta
		$response = array(
			'what'   => 'voter_action',
			'action' => 'export_voters_to_csv',
		);

		// Strip referer URL for query variables in an attempt to isolate ballot ID
		$referer_args = array();
		parse_str( parse_url( $_SERVER["HTTP_REFERER"], PHP_URL_QUERY ), $referer_args );

		// Extract Voter Type from $_POST object and confirm it's valid
		$voter_type  = filter_input( INPUT_POST, 'voter_type', FILTER_SANITIZE_STRING );
		$voter_types = Voter::get_voter_types();

		if ( ! isset( $voter_types[ $voter_type ] ) ) {
			return false;
		}

		$voter_args = array(
			'post_type'              => Voter::get_post_type(),
			// Performance Options
			'posts_per_page'         => - 1, // set to a reasonable limit
			'meta_key'               => 'wp-vote-voter_voter_type',
			'meta_value'             => $voter_type,
//			'no_found_rows'          => true, // useful when pagination is not needed
//			'update_post_meta_cache' => false, // useful when post meta will not be utilized
			'update_post_term_cache' => false, // useful when taxonomy terms will not be utilized
//			'fields'                 => 'ids', // useful when only the post IDs are needed
		);

		$voter_query = new \WP_Query( $voter_args );

		$voters = array();
		if ( $voter_query->have_posts() ) :

			$voter = array();

			while ( $voter_query->have_posts() ) :
				$voter_query->the_post();

				$voter['post']       = $post;
				$voter['meta']       = self::filter_voter_type_meta( $voter_type, get_post_meta( $post->ID ) );
				$voters[ $post->ID ] = $voter;

			endwhile;

			wp_reset_postdata();

		endif;


		// Let's prepare the base header
		$header = array(
			'id',
			'title',
		);

		// Additional header fields
		$fields = call_user_func( array( $voter_types[ $voter_type ]['class'], 'get_fields' ) );

		$header = array_merge(
			$header,
			array_keys( $fields )
		);

		// Now we'll cycle through the voters
		$data = array();
		foreach ( $voters as $voter ) {
			$voter_data = array(
				$voter['post']->ID,
				$voter['post']->post_title,
			);
			foreach( $voter['meta'] as $meta_key => $meta_value ) {
				$voter_data[] = $meta_value[0];
			}

//			foreach ( $voter['votes'] as $vote ) {
//				$voter_data[] = $vote;
//			}

			$data[] = $voter_data;

		}

		$wp_upload_dir = wp_upload_dir();
		$upload_dir    = trailingslashit( $wp_upload_dir['basedir'] ) . WP_Vote::SLUG;
		if ( ! file_exists( $upload_dir ) ) {
			mkdir( $upload_dir, 0755, true );
		}

		$date     = new \DateTime();
		$ts       = $date->format( "Y-m-d-Hi" );
		$filename = $voter_type . '_' . $ts . '.csv';
		$filepath = trailingslashit( $upload_dir ) . $filename;
		$fileurl  = trailingslashit( trailingslashit( $wp_upload_dir['baseurl'] ) . WP_Vote::SLUG ) . $filename;

		$fp = fopen( $filepath, 'w' );

		fputcsv( $fp, $header );

		foreach ( $data as $val ) {
			fputcsv( $fp, $val );
		}
		fclose( $fp );

		$response['id']   = true;
		$response['data'] = $fileurl;//__( 'Exported results to CSV.', 'wp-vote' );
		//$response['supplemental'] = array( $filename );
		$xmlResponse = new \WP_Ajax_Response( $response );
		$xmlResponse->send();
	}

	/**
	 * Filters an array of $meta returning only those meta values
	 * that match $fields on the voter type
	 *
	 * @param $voter_type string
	 * @param $meta array
	 */
	static function filter_voter_type_meta( $voter_type, $meta ) {

		$voter_types = Voter::get_voter_types();
		$fields      = call_user_func( array(
			$voter_types[ $voter_type ]['class'],
			'get_fields'
		) );

		$meta_keys = array();
		foreach ( $fields as $field_key => $field_value ) {
			$meta_keys[ $field_key ] = call_user_func( array(
				$voter_types[ $voter_type ]['class'],
				'get_prefix'
			), $field_key );
		}

		return array_intersect_key( $meta, array_flip( $meta_keys ) );
	}
}
