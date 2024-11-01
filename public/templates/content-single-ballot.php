<?php
namespace WP_Vote;
/**
 * The template for displaying ballot content in the single-ballot.php template
 *
 * This template can be overridden by copying it to yourtheme/wp-question/content-single-ballot.php.
 *
 * HOWEVER, on occasion wp-question will need to update template files and you (the theme developer).
 * will need to copy the new files to your theme to maintain compatibility. We try to do this.
 * as little as possible, but it does happen. When this occurs the version of the template file will.
 * be bumped and the readme will list any important changes.
 *
 * @author        pbearne
 * @package    wp-question/Templates
 * @version     1.6.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

?>

<?php
/**
 * vote_before_single_ballot hook.
 *
 * @hooked wp_question_check_access - 1
 */
do_action( 'wp-vote_before_single_ballot' );

if ( post_password_required() ) {
	echo get_the_password_form();

	return;
}
/**
 * wp-question_before_single_ballot hook.
 *
 * @hooked wp_question_check_access - 1
 */


?>

<div id="wp-vote-wrap">

	<?php
	/**
	 * wp-vote_before_single_ballot_loop hook.
	 *
	 * @hooked wp-question/Template_Actions::show_archive_title - 20
	 */
	do_action( 'wp-vote_before_single_ballot_loop', get_the_ID() );

	$is_allowed_to_see_question = apply_filters( 'wp-vote_is_allowed_to_see_question', false );

	if ( $is_allowed_to_see_question ) {

		the_content();

		/**
		 * wp-vote_before_single_ballot_loop hook.
		 *
		 * @hooked wp-question/Template_Actions::show_archive_title - 20
		 */
		do_action( 'wp-vote_after_content_single_ballot_loop', get_the_ID() );
		$ballot_id = get_the_ID();
		\WP_Vote\Ballot::display_open_ballot_questions( $ballot_id );

		$parent_id = get_post_meta( get_the_ID(), 'question_ballot_select', true );
		$footer    = get_post_meta( $ballot_id, \WP_Vote\Ballot::POST_TYPE . '_footer', true );

		if ( '' !== $footer ) {
			printf( '<div class="wp-vote-ballot-footer">%s</div>', apply_filters( 'the_content', $footer ) );
		}
		$conditions         = get_post_meta( $ballot_id, \WP_Vote\Ballot::POST_TYPE . '_conditions', true );
		$is_allowed_to_vote = apply_filters( 'wp-vote_is_allowed_to_vote', false );

		if ( '' !== $conditions && true === $is_allowed_to_vote ) {

			$token = Template_Actions::get_token();
			if ( false !== $token ) {
				$voters = get_post_meta( $ballot_id, Ballot::get_prefix( 'voters' ), true );

				$voter_types = Voter::get_voter_types();
				$voter_class = $voter_types[ $voters[ $token['voter_id'] ]['voter_type'] ]['class'];
				$voter       = new $voter_class( $voters[ $token['voter_id'] ] );

				$rep_name = $voter->get_rep_name( $token['rep_id'] );


				$conditions = str_replace( '{rep_name}', $rep_name, $conditions );

				$conditions = str_replace( '{ballot_title}', get_the_title(), $conditions );

				//representative_voter_id
				$voter_name = $voter->get_title();
				$conditions = str_replace( '{voter_name}', $voter_name, $conditions );
			}


			echo( '<div class="wp-vote-ballot_conditions squaredFour" >' );

			printf( '<h3>%s</h3>', esc_html( apply_filters( 'wp-vote_required_conditions_title', __( 'Required Conditions', 'wp-vote' ) ) ) );

			printf( '<div class="wp-vote-ballot_condition_text">%s</div>', apply_filters( 'the_content', $conditions ) );

			printf( '<label for="wp-vote-ballot_conditions">
								<input  required="required" type="checkbox" name="wp-vote-ballot_conditions" id="wp-vote-ballot_conditions" >
								%s
								</label>',
				esc_html( apply_filters( 'wp-vote_required_conditions', __( 'I accept', 'wp-vote' ) ) )
			);


			?>
            <script type="application/javascript">
              jQuery(function() {
                jQuery('#wp-vote-questions').submit(function() {
                  if (0 === jQuery('#wp-vote-ballot_conditions:checked').length) {
                    alert('<?php echo( esc_js( __( 'You need to agree to the conditions.', 'wp-vote' ) ) ); ?>');
                    return false;
                  }
                });
              });
            </script>
		<?php

		echo '</div>';

		$add_signature      = get_post_meta( $ballot_id, \WP_Vote\Ballot::POST_TYPE . '_add_signature', true );
		$add_signature_text = get_post_meta( $ballot_id, \WP_Vote\Ballot::POST_TYPE . '_add_signature_text', true );
		if ( 'on' === $add_signature ) {

		printf( '<div class="wp-vote-ballot_conditions_signature squaredFour" >
                                    <label for="wp-vote-ballot_conditions_signature">%s</label>
                                    <input required="required" type="text" name="wp-vote-ballot_conditions_signature" id="wp-vote-ballot_conditions_signature" ></div>',
			esc_html( ( ! empty( $add_signature_text ) ) ? $add_signature_text : Ballot::get_add_signature_text_default() )
		);

		?>
            <script type="application/javascript">
              jQuery(function() {
                jQuery('#wp-vote-questions').submit(function() {
                  if (0 === jQuery('#wp-vote-ballot_conditions_signature').val().length) {
                    alert('<?php echo( esc_js( __( 'You need to type your name in the box.', 'wp-vote' ) ) ); ?>');
                    return false;
                  }
                });
              });

            </script>
			<?php

		}

		}
	} // $is_allowed_to_vote

	/**
	 * wp-vote_after_single_ballot__loop hook.
	 *
	 */
	do_action( 'wp-vote_after_single_ballot_loop', get_the_ID() );
	?>

</div><!-- #ballot-<?php the_ID(); ?> -->

<?php do_action( 'wp-vote_after_single_ballot' ); ?>
