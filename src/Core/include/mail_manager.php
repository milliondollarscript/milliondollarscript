<?php
/*
 * Million Dollar Script Two
 *
 * @author      Ryan Rhode
 * @copyright   (C) 2025, Ryan Rhode
 * @license     https://opensource.org/licenses/GPL-3.0 GNU General Public License, version 3
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 *
 *    Million Dollar Script
 *    Pixels to Profit: Ignite Your Revolution
 *    https://milliondollarscript.com/
 *
 */

use MillionDollarScript\Classes\Data\Config;
use MillionDollarScript\Classes\Email\Mail;

defined( 'ABSPATH' ) or exit;

function q_mail_error( $s ) {

	mail( get_bloginfo( 'admin_email' ), get_bloginfo( 'name' ) . 'email q error', $s . "\n" );
}

function queue_mail( $to_address, $to_name, $from_address, $from_name, $subject, $message, $html_message, $template_id, $att = false ) {
	global $wpdb;

	$to_address   = substr( trim( $to_address ), 0, 128 );
	$to_name      = substr( trim( $to_name ), 0, 128 );
	$from_address = substr( trim( $from_address ), 0, 128 );
	$from_name    = substr( trim( $from_name ), 0, 128 );
	$subject      = substr( trim( $subject ), 0, 255 );
	$message      = trim( $message );
	$html_message = trim( $html_message );

	$attachments = 'N';

	$now = current_time( 'mysql' );

	$data = array(
		'mail_date'    => $now,
		'to_address'   => $to_address,
		'to_name'      => $to_name,
		'from_address' => $from_address,
		'from_name'    => $from_name,
		'subject'      => $subject,
		'message'      => $message,
		'html_message' => $html_message,
		'attachments'  => $attachments,
		'status'       => 'queued',
		'error_msg'    => '',
		'retry_count'  => 0,
		'template_id'  => intval( $template_id ),
		'date_stamp'   => $now
	);

	$formats = array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s' );

	$result = $wpdb->insert( MDS_DB_PREFIX . 'mail_queue', $data, $formats );

	if ( false === $result ) {
		q_mail_error( $wpdb->last_error );
	}

	$mail_id = $wpdb->insert_id;

	return $mail_id;
}

function process_mail_queue( $send_count = 1 ) {

	$now       = current_time( 'mysql' );
	$unix_time = time();

	// get the time of last run
	$last_mail_queue_run = \get_option(MDS_PREFIX . 'last-mail-queue-run', $now);

	if ( isset( $DB_ERROR ) && $DB_ERROR != '' ) {
		return $DB_ERROR;
	}

	// Poor man's lock (making sure that this function is a Singleton)
	$mail_queue_running = \get_option(MDS_PREFIX . 'mail-queue-running', 'NO');	
	if ( $mail_queue_running == 'NO' ) {
		\update_option(MDS_PREFIX . 'mail-queue-running', 'YES');
	} else {

		// make sure it cannot be locked for more than 30 secs 
		// This is in case the proccess fails inside the lock
		// and does not release it.

		if ( $unix_time > $last_mail_queue_run + 30 ) {
			// release the lock
			\update_option(MDS_PREFIX . 'mail-queue-running', 'NO');

			// update timestamp
			\update_option(MDS_PREFIX . 'last-mail-queue-run', $unix_time);
		}

		return; // this function is already executing in another process.
	}

	if ( $unix_time > $last_mail_queue_run + 5 ) { // did 5 seconds elapse since last run?
		global $wpdb;

		$and_mail_id = "";
		if ( func_num_args() > 1 ) {
			$mail_id = func_get_arg( 1 );

			$and_mail_id = " AND mail_id=" . intval( $mail_id ) . " ";
		}

		$EMAILS_MAX_RETRY = Config::get( 'EMAILS_MAX_RETRY' );
		if ( $EMAILS_MAX_RETRY == '' ) {
			$EMAILS_MAX_RETRY = 5;
		}

		$EMAILS_ERROR_WAIT = Config::get( 'EMAILS_ERROR_WAIT' );
		if ( $EMAILS_ERROR_WAIT == '' ) {
			$EMAILS_ERROR_WAIT = 10;
		}

		$sql = $wpdb->prepare(
			"SELECT * from " . MDS_DB_PREFIX . "mail_queue where (status='queued' OR status='error') AND retry_count <= %d $and_mail_id order by mail_date DESC",
			intval( $EMAILS_MAX_RETRY )
		);
		$results = $wpdb->get_results( $sql, ARRAY_A );
		if ( $wpdb->last_error ) {
			q_mail_error( $wpdb->last_error . $sql );
		}
		foreach ( $results as $row ) {
			if ( $send_count <= 0 ) {
				break;
			}
			$time_stamp = strtotime( $row['date_stamp'] );
			$now        = current_time( 'timestamp' );
			$wait       = $EMAILS_ERROR_WAIT * 60;
			//echo "(($now - $wait) > $time_stamp) status:".$row['status']."\n";
			if ( ( ( ( $now - $wait ) > $time_stamp ) && ( $row['status'] == 'error' ) ) || ( $row['status'] == 'queued' ) ) {
				$send_count --;
				Mail::send( $row['to_address'], $row['subject'], $row['message'], '', $row['subject'], $row['message'] );
			}
		}

		// delete old stuff

		$EMAILS_DAYS_KEEP = Config::get( 'EMAILS_DAYS_KEEP' );

		if ( $EMAILS_DAYS_KEEP == 'EMAILS_DAYS_KEEP' ) {
			Config::set( '0', 'EMAILS_DAYS_KEEP' );
		}

		if ( $EMAILS_DAYS_KEEP > 0 ) {

			$now = current_time( 'mysql' );

			$sql = $wpdb->prepare(
				"SELECT mail_id, att1_name, att2_name, att3_name from " . MDS_DB_PREFIX . "mail_queue where status='sent' AND DATE_SUB(%s,INTERVAL %d DAY) >= date_stamp",
				$now,
				intval( $EMAILS_DAYS_KEEP )
			);

			$results = $wpdb->get_results( $sql, ARRAY_A );
			if ( $wpdb->last_error ) {
				mds_secure_sql_die( $sql, 'process_mail_queue cleanup' );
			}

			foreach ( $results as $row ) {

				if ( $row['att1_name'] != '' ) {
					unlink( $row['att1_name'] );
				}

				if ( $row['att2_name'] != '' ) {
					unlink( $row['att2_name'] );
				}

				if ( $row['att3_name'] != '' ) {
					unlink( $row['att3_name'] );
				}

				$delete_result = $wpdb->delete(
					MDS_DB_PREFIX . 'mail_queue',
					array( 'mail_id' => intval( $row['mail_id'] ) ),
					array( '%d' )
				);
				if ( false === $delete_result ) {
					mds_secure_sql_die( 'DELETE FROM ' . MDS_DB_PREFIX . 'mail_queue WHERE mail_id=' . intval( $row['mail_id'] ), 'process_mail_queue delete' );
				}
			}
		}
	}

	// release the poor man's lock
	\update_option(	MDS_PREFIX . 'mail-queue-running', 'NO');
}

// From WordPress /wp-includes/formatting.php
/**
 * Converts a number of HTML entities into their special characters.
 *
 * Specifically deals with: &, <, >, ", and '.
 *
 * $quote_style can be set to ENT_COMPAT to decode " entities,
 * or ENT_QUOTES to do both " and '. Default is ENT_NOQUOTES where no quotes are decoded.
 *
 * @param string $string The text which is to be decoded.
 * @param string|int $quote_style Optional. Converts double quotes if set to ENT_COMPAT,
 *                                both single and double if set to ENT_QUOTES or
 *                                none if set to ENT_NOQUOTES.
 *                                Also compatible with old _wp_specialchars() values;
 *                                converting single quotes if set to 'single',
 *                                double if set to 'double' or both if otherwise set.
 *                                Default is ENT_NOQUOTES.
 *
 * @return string The decoded text without HTML entities.
 * @since 2.8.0
 *
 */
function mds_specialchars_decode( $string, $quote_style = ENT_NOQUOTES ) {
	$string = (string) $string;

	if ( 0 === strlen( $string ) ) {
		return '';
	}

	// Don't bother if there are no entities - saves a lot of processing
	if ( strpos( $string, '&' ) === false ) {
		return $string;
	}

	// Match the previous behaviour of _wp_specialchars() when the $quote_style is not an accepted value
	if ( empty( $quote_style ) ) {
		$quote_style = ENT_NOQUOTES;
	} else if ( ! in_array( $quote_style, array( 0, 2, 3, 'single', 'double' ), true ) ) {
		$quote_style = ENT_QUOTES;
	}

	// More complete than get_html_translation_table( HTML_SPECIALCHARS )
	$single      = array(
		'&#039;' => '\'',
		'&#x27;' => '\'',
	);
	$single_preg = array(
		'/&#0*39;/'   => '&#039;',
		'/&#x0*27;/i' => '&#x27;',
	);
	$double      = array(
		'&quot;' => '"',
		'&#034;' => '"',
		'&#x22;' => '"',
	);
	$double_preg = array(
		'/&#0*34;/'   => '&#034;',
		'/&#x0*22;/i' => '&#x22;',
	);
	$others      = array(
		'&lt;'   => '<',
		'&#060;' => '<',
		'&gt;'   => '>',
		'&#062;' => '>',
		'&amp;'  => '&',
		'&#038;' => '&',
		'&#x26;' => '&',
	);
	$others_preg = array(
		'/&#0*60;/'   => '&#060;',
		'/&#0*62;/'   => '&#062;',
		'/&#0*38;/'   => '&#038;',
		'/&#x0*26;/i' => '&#x26;',
	);

	if ( $quote_style === ENT_QUOTES ) {
		$translation      = array_merge( $single, $double, $others );
		$translation_preg = array_merge( $single_preg, $double_preg, $others_preg );
	} else if ( $quote_style === ENT_COMPAT || $quote_style === 'double' ) {
		$translation      = array_merge( $double, $others );
		$translation_preg = array_merge( $double_preg, $others_preg );
	} else if ( $quote_style === 'single' ) {
		$translation      = array_merge( $single, $others );
		$translation_preg = array_merge( $single_preg, $others_preg );
	} else if ( $quote_style === ENT_NOQUOTES ) {
		$translation      = $others;
		$translation_preg = $others_preg;
	}

	// Remove zero padding on numeric entities
	$string = preg_replace( array_keys( $translation_preg ), array_values( $translation_preg ), $string );

	// Replace characters according to translation table
	return strtr( $string, $translation );
}
