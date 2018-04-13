<?php

/**
 * @package "Stop Spammer" Addon for Elkarte
 * @author tinoest
 * @license BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0.0
 *
 */

if (!defined('ELK'))
{
	die('No access...');
}

/**
 * integrate_register hook
 *
 * - Used to check the user against the stop spammer database on registration
 */
function int_stopSpammer(&$regOptions, &$reg_errors)
{

	$confidenceThreshold = 50;

	$url    = 'https://api.stopforumspam.org/api';
	$data	= '?';
	$data   .= '&ip='.$regOptions['ip'];
	$data	.= '&username=' . urlencode($regOptions['username']);
	$data	.= '&email=' . urlencode($regOptions['email']);
	$data	.= '&json';


	require_once(SUBSDIR . '/Package.subs.php');
	$result	= fetch_web_data($url.$data);
	$result = json_decode($result, true);

	if ( is_array($result) && $result['success'] === 1 ) {
		if ( ( $result['ip']['appears'] === 1 )  && ( $result['ip']['confidence'] > $confidenceThreshold ) ) {
			$reg_errors->addError('no_guests');
		}
		if ( ( $result['username']['appears'] === 1 )  && ( $result['username']['confidence'] > $confidenceThreshold ) ) {
			$reg_errors->addError('no_guests');
		}
		if ( ( $result['email']['appears'] === 1 )  && ( $result['email']['confidence'] > $confidenceThreshold ) ) {
			$reg_errors->addError('bad_email');
		}
	}

	return;

}
?>
