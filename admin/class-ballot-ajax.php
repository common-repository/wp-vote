<?php
/**
 * wp-vote.
 * User: Paul
 * Date: 2016-05-01
 *
 */

namespace WP_Vote;

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Ballot_Ajax extends Ballot {

	public function __construct() {

		add_action( 'wp_ajax_email_ballot_to_individual', array(
			__CLASS__,
			'email_ballot_to_individual_callback'
		) );
		add_action( 'wp_ajax_email_ballot_to_all_voters', array(
			__CLASS__,
			'email_ballot_to_all_voters_callback'
		) );
		add_action( 'wp_ajax_export_results_to_csv', array(
			__CLASS__,
			'export_results_to_csv_callback'
		) );
		add_action( 'wp_ajax_show-individual-votes', array(
			__CLASS__,
			'show_individual_votes_callback'
		) );

		add_action( 'wp_ajax_email_test_ballot', array(
			__CLASS__,
			'email_test_ballot_callback'
		) );

		add_action( 'wp_ajax_edit_ballot_close_time', array(
			__CLASS__,
			'edit_ballot_close_time_callback'
		) );

		add_action( 'wp_ajax_clear_ballot_close_time', array(
			__CLASS__,
			'clear_ballot_close_time_callback'
		) );

	}

	public static function email_ballot_to_individual_callback() {

		// Setup the response meta
		$response = array(
			'what'   => 'ballot_action',
			'action' => 'email_ballot_to_individual',
		);

		// Strip referer URL for query variables in an attempt to isolate ballot ID
		$referer_args = array();
		parse_str( parse_url( $_SERVER["HTTP_REFERER"], PHP_URL_QUERY ), $referer_args );

		// Bail if we didn't get a ballot id
		if ( ! isset( $referer_args['post'] ) ) {
			$response['id']   = new \WP_Error( 'ballot-id-missing-error', __( 'Ballot ID missing.', 'wp-vote' ) );
			$response['data'] = __( 'Ballot ID missing.', 'wp-vote' );
			$xmlResponse      = new \WP_Ajax_Response( $response );
			$xmlResponse->send();

		}

		// Try to get the ballot
		$ballot_id = $referer_args['post'];
		$ballot    = get_post( $ballot_id );

		// Bail if there isn't a ballot with that ID
		if ( ! $ballot ) {
			$response['id']   = new \WP_Error( 'ballot-missing-error', __( 'No ballot with that ID.', 'wp-vote' ) );
			$response['data'] = __( 'No ballot with that ID.', 'wp-vote' );
			$xmlResponse      = new \WP_Ajax_Response( $response );
			$xmlResponse->send();
		}

		// Try to get the voter

		// Bail if we don't have any eligible voters
		if ( ! isset ( $_POST['voter_id'] ) || empty( $_POST['voter_id'] ) ) {
			$response['id']   = new \WP_Error( 'ballot-email-error', __( 'Voter ID missing.', 'wp-vote' ) );
			$response['data'] = __( 'No eligible voters.', 'wp-vote' );
			$xmlResponse      = new \WP_Ajax_Response( $response );
			$xmlResponse->send();
		}

		$voter_id = $_POST['voter_id'];


		// Try to get the eligible voter list
		$eligible_voters = Ballot::get_voters( $ballot_id );

		// Bail if we don't have any eligible voters
		if ( empty( $eligible_voters ) || ! array_key_exists( $voter_id, $eligible_voters ) ) {
			$response['id']   = new \WP_Error( 'ballot-email-error', __( 'No eligible voters.', 'wp-vote' ) );
			$response['data'] = __( 'No eligible voters.', 'wp-vote' );
			$xmlResponse      = new \WP_Ajax_Response( $response );
			$xmlResponse->send();
		}


		// Things are looking good! Let's email the voter.
		$voter_types = Voter::get_voter_types();

		$voter_class = $voter_types[ $eligible_voters[ $voter_id ]['voter_type'] ]['class'];

		$voter = new $voter_class( $eligible_voters[ $voter_id ] );

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

		$email_status = $voter->send_email_notification( $ballot_id, Ballot::STATUS_REMINDER );

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

		$response['id']   = $email_status;
		$response['data'] = __( 'Successfully emailed voter.', 'wp-vote' );
		$xmlResponse      = new \WP_Ajax_Response( $response );
		$xmlResponse->send();

	}

	public static function email_test_ballot_callback() {

		// Setup the response meta
		$response = array(
			'what'   => 'ballot_action',
			'action' => 'test_email_ballot_to_individual',
		);

		// Strip referer URL for query variables in an attempt to isolate ballot ID
		$referer_args = array();
		parse_str( parse_url( $_SERVER["HTTP_REFERER"], PHP_URL_QUERY ), $referer_args );

		// Bail if we didn't get a ballot id
		if ( ! isset( $referer_args['post'] ) ) {
			$response['id']   = new \WP_Error( 'ballot-id-missing-error', __( 'Ballot ID missing.', 'wp-vote' ) );
			$response['data'] = __( 'Ballot ID missing.', 'wp-vote' );
			$xmlResponse      = new \WP_Ajax_Response( $response );
			$xmlResponse->send();

		}

		// Try to get the ballot
		$ballot_id = $referer_args['post'];
		$ballot    = get_post( $ballot_id );

		// Bail if there isn't a ballot with that ID
		if ( ! $ballot ) {
			$response['id']   = new \WP_Error( 'ballot-missing-error', __( 'No ballot with that ID.', 'wp-vote' ) );
			$response['data'] = __( 'No ballot with that ID.', 'wp-vote' );
			$xmlResponse      = new \WP_Ajax_Response( $response );
			$xmlResponse->send();
		}

		// Try to get the voter

		// Bail if we don't have any eligible voters
		if ( ! isset ( $_POST['voter_id'] ) || empty( $_POST['voter_id'] ) ) {
			$response['id']   = new \WP_Error( 'ballot-email-error', __( 'Voter ID missing.', 'wp-vote' ) );
			$response['data'] = __( 'No eligible voters.', 'wp-vote' );
			$xmlResponse      = new \WP_Ajax_Response( $response );
			$xmlResponse->send();
		}

		$voter_id = $_POST['voter_id'];


		// Try to get the eligible voter list
		$eligible_voters = Voter::get_voter_details( $voter_id );

		// Bail if we don't have any eligible voters
		if ( empty( $eligible_voters ) ) {
			$response['id']   = new \WP_Error( 'ballot-email-error', __( 'No eligible voters.', 'wp-vote' ) );
			$response['data'] = __( 'No eligible voters.', 'wp-vote' );
			$xmlResponse      = new \WP_Ajax_Response( $response );
			$xmlResponse->send();
		}


		// Things are looking good! Let's email the voter.
		$voter_types = Voter::get_voter_types();

		$voter_class = $voter_types[ $eligible_voters['voter_type'] ]['class'];

		$voter = new $voter_class( $eligible_voters );

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

		$email_status = $voter->send_email_notification( $ballot_id, 'TEST' );

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

		$response['id']   = $email_status;
		$response['data'] = __( 'Successfully emailed test voter.', 'wp-vote' );
		$xmlResponse      = new \WP_Ajax_Response( $response );
		$xmlResponse->send();

	}

	public static function email_ballot_to_all_voters_callback() {
		// Setup the response meta
		$response = array(
			'what'   => 'ballot_action',
			'action' => 'email_ballot_to_all_voters',
		);

		// Strip referer URL for query variables in an attempt to isolate ballot ID
		$referer_args = array();
		parse_str( parse_url( $_SERVER["HTTP_REFERER"], PHP_URL_QUERY ), $referer_args );

		// Bail if we didn't get a ballot id
		if ( ! isset( $referer_args['post'] ) ) {
			$response['id']   = new \WP_Error( 'ballot-id-missing-error', __( 'Ballot ID missing.', 'wp-vote' ) );
			$response['data'] = __( 'Ballot ID missing.', 'wp-vote' );
			$xmlResponse      = new \WP_Ajax_Response( $response );
			$xmlResponse->send();

		}

		// Try to get the ballot
		$ballot_id = $referer_args['post'];
		$ballot    = get_post( $ballot_id );

		// Bail if there isn't a ballot with that ID
		if ( ! $ballot ) {
			$response['id']   = new \WP_Error( 'ballot-missing-error', __( 'No ballot with that ID.', 'wp-vote' ) );
			$response['data'] = __( 'No ballot with that ID.', 'wp-vote' );
			$xmlResponse      = new \WP_Ajax_Response( $response );
			$xmlResponse->send();
		}

		// Try to get the eligible voter list
		$eligible_voters = Ballot::get_voters( $ballot_id );

		// Bail if we don't have any eligible voters
		if ( empty( $eligible_voters ) ) {
			$response['id']   = new \WP_Error( 'ballot-email-error', __( 'No eligible voters.', 'wp-vote' ) );
			$response['data'] = __( 'No eligible voters.', 'wp-vote' );
			$xmlResponse      = new \WP_Ajax_Response( $response );
			$xmlResponse->send();
		}

		$voter_types = Voter::get_voter_types();
		// Things are looking good! Let's email the voter.
		foreach ( $eligible_voters as $eligible_voter ) {
			if ( ! isset( $eligible_voter['voted'] ) ) {
				$voter = new $voter_types[ $eligible_voter['voter_type'] ]['class']( $eligible_voter );

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

				$email_status = $voter->send_email_notification( $ballot_id, Ballot::STATUS_REMINDER );

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
		}

		$response['id']   = $email_status;
		$response['data'] = __( 'Successfully emailed voters.', 'wp-vote' );
		$xmlResponse      = new \WP_Ajax_Response( $response );
		$xmlResponse->send();

	}

	public static function show_individual_votes_callback() {

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			//var_dump( $_POST['voter_id'] );
			$voter_id  = absint( $_POST['voter_id'] );
			$ballot_id = absint( $_POST['ballot_id'] );
			Ballot::display_voters_stats( $ballot_id, $voter_id );
			die();
		}


	}


	public static function export_results_to_csv_callback() {

		// Setup the response meta
		$response = array(
			'what'   => 'ballot_action',
			'action' => 'export_results_to_csv',
		);

		// Strip referer URL for query variables in an attempt to isolate ballot ID
		$referer_args = array();
		parse_str( parse_url( $_SERVER["HTTP_REFERER"], PHP_URL_QUERY ), $referer_args );

		// Bail if we didn't get a ballot id
		if ( ! isset( $referer_args['post'] ) ) {
			$response['id']   = new \WP_Error( 'ballot-id-missing-error', __( 'Ballot ID missing.', 'wp-vote' ) );
			$response['data'] = __( 'Ballot ID missing.', 'wp-vote' );
			$xmlResponse      = new \WP_Ajax_Response( $response );
			$xmlResponse->send();

		}

		// Try to get the ballot
		$ballot_id = $referer_args['post'];
		$ballot    = get_post( $ballot_id );

		// Bail if there isn't a ballot with that ID
		if ( ! $ballot ) {
			$response['id']   = new \WP_Error( 'ballot-missing-error', __( 'No ballot with that ID.', 'wp-vote' ) );
			$response['data'] = __( 'No ballot with that ID.', 'wp-vote' );
			$xmlResponse      = new \WP_Ajax_Response( $response );
			$xmlResponse->send();
		}

		$questions = get_post_meta( $ballot_id, Ballot::get_prefix( 'questions' ), true );

		if ( empty( $questions ) ) {
			$response['id']   = new \WP_Error( 'question-missing-error', __( 'No questions found.', 'wp-vote' ) );
			$response['data'] = __( 'No questions found.', 'wp-vote' );
			$xmlResponse      = new \WP_Ajax_Response( $response );
			$xmlResponse->send();

		}

		$voters = get_post_meta( $ballot_id, Ballot::get_prefix( 'voters' ), true );

		if ( empty( $voters ) ) {
			$response['id']   = new \WP_Error( 'votes-missing-error', __( 'No votes found.', 'wp-vote' ) );
			$response['data'] = __( 'No votes found.', 'wp-vote' );
			$xmlResponse      = new \WP_Ajax_Response( $response );
			$xmlResponse->send();
		}

		// Let's prepare the base header
		$header = array(
			'ballot_id',
			'ballot_title',
			'ballot_open',
			'ballot_closed',
			'voter',
			'voted_timestamp',
			'rep_name',
			'rep_email',
			'proxy',
			'accepted_conditions',
			'ip_address',
		);

		// Voter Details
		$first_voter = reset( $voters );
		if ( isset( $first_voter['export_fields'] ) ) {
			$field_names = array_keys( $first_voter['export_fields'] );
			foreach ( $field_names as $field_name ) {
				$header[] = $field_name;
			}
		}

		// Let's add the question titles across the top
		foreach ( $questions as $question ) {
			$header[] = $question[ Ballot::get_prefix( 'question_title' ) ];
		}


		// Now we'll cycle through the votes
		$data = array();
		foreach ( $voters as $voter ) {
			$voter_data = array(
				$ballot_id,
				$ballot->post_title,
				date( "Y-m-d-Hi", get_post_meta( $ballot_id, Ballot::get_prefix( 'date_open' ), true ) ),
				date( "Y-m-d-Hi", get_post_meta( $ballot_id, Ballot::get_prefix( 'date_closed' ), true ) ),
				$voter['title'],
				( isset( $voter['voted']['timestamp'] ) ) ? date( "Y-m-d-Hi", $voter['voted']['timestamp'] ) : '',
				$voter['reps'][ $voter['voted']['rep'] ]['name'],
				$voter['reps'][ $voter['voted']['rep'] ]['email'],
				( isset( $voter['proxy'] ) ) ? $voter['proxy'] : '',
			);

			$voter_data[] = ( isset( $voter['accepted_conditions'] ) ) ? $voter['accepted_conditions'] : '';
			$voter_data[] = ( isset( $voter['ip_address'] ) ) ? $voter['ip_address'] : '';

			if ( isset( $voter['export_fields'] ) ) {
				foreach ( $voter['export_fields'] as $value ) {
					$voter_data[] = $value;
				}
			}

			// Vote details
			foreach ( $voter['votes'] as $vote ) {
				$voter_data[] = $vote;
			}

			$data[] = $voter_data;

		}

		$wp_upload_dir = wp_upload_dir();
		$upload_dir    = trailingslashit( $wp_upload_dir['basedir'] ) . WP_Vote::SLUG;
		if ( ! file_exists( $upload_dir ) ) {
			mkdir( $upload_dir, 0755, true );
		}

		$date     = new \DateTime();
		$ts       = $date->format( "Y-m-d-Hi" );
		$filename = $ballot->post_title . '-ballot-results-' . $ts . '.csv';
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

	public static function edit_ballot_close_time_callback(){

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {

			check_ajax_referer( 'ballot_ajax-calls', 'ballot_ajax-calls' );

			$m = $_REQUEST['mm'];
			$d = $_REQUEST['jj'];
			$y = $_REQUEST['aa'];
			$h = $_REQUEST['hh'];
			$n = $_REQUEST['mn'];
			$id = $_REQUEST['ballot_id'];

			$date = new \DateTime( "$y-$m-$d $h:$n:00");

			update_post_meta( $id, Ballot::get_prefix( 'close_ballot_at' ), strtotime( $date->format('Y-m-d H:i:s' ) ) );

			$response['data'] = sprintf( esc_html__( 'Closing ballot at: %s') , $date->format('Y-m-d H:i:s' ) );
			wp_send_json($response);
		} else {

			return false;
		}

	}
	public static function clear_ballot_close_time_callback(){

	if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {

		check_ajax_referer( 'ballot_ajax-calls', 'ballot_ajax-calls' );

		$id = $_REQUEST['ballot_id'];
		delete_post_meta( $id, Ballot::get_prefix( 'close_ballot_at' ) );

		$response['data'] = esc_html__( 'Auto Close Ballot not set');
		wp_send_json($response);
	} else {

		return false;
	}

}
}