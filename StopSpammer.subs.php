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


function stopSpammerStopforumspamCheck(&$spammer, $confidenceThreshold = null, $ip = null, $username = null, $email = null)
{

    // We need this for fetch_web_data
    require_once(SUBSDIR . '/Package.subs.php');

    $url        = 'https://api.stopforumspam.org/api';
    $data	    = '?';
    $noCheck    = true;

    if(!empty($ip)) {
        $data   .= 'ip='.$ip.'&';
        $noCheck = false;
    }

    if(!empty($username)) {
        $data	.= 'username=' . urlencode($username).'&';
        $noCheck = false;
    }

    if(!empty($email)) {
        $data	.= 'email=' . urlencode($email).'&';
        $noCheck = false;
    }

    // Nothing was set?
    if($noChecks) {
        return $spammer;
    }

    $data	.= 'json';

    $result	= fetch_web_data($url.$data);
    $result = json_decode($result, true);

    if ( is_array($result) && $result['success'] === 1 ) {
        if ( ( $result['ip']['appears'] === 1 )  && ( $result['ip']['confidence'] > $confidenceThreshold ) ) {
            $spammer = true;
        }
        if ( ( $result['username']['appears'] === 1 )  && ( $result['username']['confidence'] > $confidenceThreshold ) ) {
            $spammer = true;
        }
        if ( ( $result['email']['appears'] === 1 )  && ( $result['email']['confidence'] > $confidenceThreshold ) ) {
            $spammer = true;
        }
    }

}


function stopSpammerSpamhausCheck(&$spammer, $ip = null)
{    

    if(!empty($ip)) {
        $revip  = implode(".", array_reverse(explode(".", $ip, 4), false));
        $dns    = dns_get_record($revip . ".zen.spamhaus.org");
        if ($dns != null && count($dns) > 0) {
            foreach ($dns as $entry) {
                if (in_array($entry['ip'], array('127.0.0.2', '127.0.0.3', '127.0.0.4'))) {
                    $spammer = true;
                }
            }
        }
    }

}
 
function stopSpammerProjecthoneypotCheck(&$spammer, $confidenceThreshold = null, $key = null, $ip = null)
{    

    foreach(array('confidenceThreshold', 'key', 'ip') as $v) {
        if(is_null($$v)) {
            return $spammer;
        }
    }

    if(!empty($ip)) {
        $results = explode( ".", gethostbyname($modSettings['projecthoneypot_key'].".".implode(".", array_reverse(explode(".", $ip))).".dnsbl.httpbl.org" ) ); 
        if ($results != null && count($results) && isset($results[0]['ip'])) {
            $results = explode('.', $results[0]['ip']);
            if ($results[0] == 127) {
                $categories = array (
                    0 => 'Search Engine',
                    1 => 'Suspicious',
                    2 => 'Harvester',
                    3 => 'Suspicious,Harvester',
                    4 => 'Comment Spammer',
                    5 => 'Suspicious,Comment Spammer',
                    6 => 'Harvester,Comment Spammer',
                    7 => 'Suspicious,Harvester,Comment Spammer',
                );

                $results = array (
                    'last_activity' => $results[1],
                    'threat_score'  => $results[2],
                    'categories'    => $categories[$results[3]],
                );

                if($results['threat_score'] > $confidenceThreshold) {
                    $spammer = true;
                }
            }
        }
    }   

}
    
function stopSpammerGetMembers($start, $items_per_page, $sort, $where, $where_params = array(), $get_duplicates = false) 
{
    $db = database();
    // Load default call
    require_once( SUBSDIR . '/Members.subs.php');

    $members = list_getMembers($start, $items_per_page, $sort, $where, $where_params = array(), $get_duplicates = false);

    if(is_array($members) && count($members)) {
        foreach($members as $k => $member) {
            $memberSpammer = $db->fetchQuery('
                SELECT is_spammer 
                FROM {db_prefix}members AS mem
                WHERE id_member = {int:id_member}',
                array (
                    'id_member' => $member['id_member'],
                )
            );
            if(array_key_exists('0', $memberSpammer) && array_key_exists('is_spammer', $memberSpammer[0])) {
                $members[$k]['is_spammer'] = $memberSpammer[0]['is_spammer'];
            }
            else {
                $members[$k]['is_spammer'] = 0;
            }
        }
    }

    return $members;
}

function stopSpammerCheckUser($user_id)
{

    // Retrieve user information?


}


?>
