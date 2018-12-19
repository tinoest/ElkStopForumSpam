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

$spammer = false;

/**
 * integrate_register_check hook
 *
 * - Used to check the user against the stop spammer database on registration
 */
function int_stopSpammer(&$regOptions, &$reg_errors)
{
	global $modSettings, $spammer;
        
    // No point running if disabled
    if(empty($modSettings['stopspammer_enabled'])) {
        return;
    }
    
    $spammer = false;

    if($modSettings['stopforumspam_enabled']) {
        // We need this for fetch_web_data
        require_once(SUBSDIR . '/Package.subs.php');

        if(isset($modSettings['stopforumspam_threshold'])) {
            $confidenceThreshold = $modSettings['stopforumspam_threshold'];
        }
        else {
            $confidenceThreshold = 50;
        }

        $url    = 'https://api.stopforumspam.org/api';
        $data	= '?';
        if(!empty($modSettings['stopforumspam_ip_check'])) {
            $data   .= 'ip='.$regOptions['ip'].'&';
        }
        if(!empty($modSettings['stopforumspam_username_check'])) {
            $data	.= 'username=' . urlencode($regOptions['username']).'&';
        }
        if(!empty($modSettings['stopforumspam_email_check'])) {
            $data	.= 'email=' . urlencode($regOptions['email']).'&';
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

    if($modSettings['spamhaus_enabled']) {
        $revip  = implode(".", array_reverse(explode(".", $regOptions['ip'], 4), false));
        $dns    = dns_get_record($revip . ".zen.spamhaus.org");
        if ($dns != null && count($dns) > 0) {
            foreach ($dns as $entry) {
                if (in_array($entry['ip'], array('127.0.0.2', '127.0.0.3', '127.0.0.4'))) {
                    $spammer = true;
                }
            }
        }
    }
 
    if($modSettings['projecthoneypot_enabled'] && !empty($modSettings['projecthoneypot_key'])) {
        $results = explode( ".", gethostbyname($modSettings['projecthoneypot_key'].".".implode(".", array_reverse(explode(".", $regOptions['ip']))).".dnsbl.httpbl.org" ) ); 
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

                if($results['threat_score'] > $modSettings['projecthoneypot_threshold']) {
                    $spammer = true;
                }
            }
        }   
    }
    
    if($spammer == true && !empty($modSettings['stopspammer_block_register'])) {            
        $reg_errors->addError('not_guests');
    }

	return;

}

/**
 * integrate_register hook
 *
 * - Used to add the check to the database on registration
 */
function int_registerStopSpammer(&$regOptions, &$theme_vars, &$knownInts, &$knownFloats)
{
    global $spammer;

    $knownInts[] = 'is_spammer';
    if($spammer == true) {
        $regOptions['register_vars']['is_spammer'] = 1;
    }
    else {
        $regOptions['register_vars']['is_spammer'] = 0;
    }

}


/**
 * int_actionStopSpammer()
 *
 * - Action Hook, integrate_actions, called from SiteDispacher.php
 * - Used to add/modify action list
 *
 * @param mixed[] $actionArray
 * @param mixed[] $adminActions
 */
function int_actionsStopSpammer(&$actionArray, &$adminActions)
{

     

}


/**
 * int_adminAreasStopSpammer()
 *
 * - Admin Hook, integrate_admin_areas, called from Admin.php
 * - Used to add/modify admin menu areas
 *
 * @param mixed[] $admin_areas
 */
function int_adminAreasStopSpammer(&$admin_areas)
{
	global $txt;
	loadLanguage('StopSpammer');

    $admin_areas['config']['areas']['securitysettings']['subsections']['stopspammer'] = array ( 0 => $txt['stopspammer_title']);
    
}

/**
 * int_adminStopSpammer()
 *
 * - Admin Hook, integrate_sa_modify_security, called from ManageSecurity.controller.php
 * - Used to add subactions to the addon area
 *
 * @param mixed[] $sub_actions
 */
function int_adminStopSpammer(&$sub_actions)
{
	global $context, $txt;
	$sub_actions['stopspammer'] = array(
		'dir' => SOURCEDIR,
		'file' => 'StopSpammer.integration.php',
		'function' => 'stopspammer_settings',
		'permission' => 'admin_forum',
	);

	$context[$context['admin_menu_name']]['tab_data']['tabs']['stopspammer']['description'] = $txt['stopspammer_desc'];
}

/**
 * int_profileStopSpammer()
 *
 * - Admin Hook, integrate_profile_areas, called from Members.controller.php
 * - Used to add subactions to the profile area
 *
 * @param mixed[] $profile_areas
 */
function int_profileStopSpammer(&$profile_areas)
{


    global $txt, $scripturl, $memID, $modSettings;
    loadLanguage('StopSpammer');

    $profile_areas['profile_action']['areas'] = elk_array_insert($profile_areas['profile_action']['areas'], 'banuser', array(
            'checkmember'       => array(
                'label'         => $txt['stopspammer_check'],
                'custom_url'    => $scripturl . '?action=stopspammer;sa=checkmember;member_id='.$memID,
                'enabled'       => !empty($modSettings['stopspammer_enabled']),
                'sc'            => 'get',
                'permission'    => array(
                    'own'   => array('profile_remove_any', 'profile_remove_own'),
                    'any'   => array('profile_remove_any', 'moderate_forum'),
                ),
            )
        ), 
        'after'
    );

}


/**
 * int_adminStopSpammer()
 *
 * - Admin Hook, integrate_list_member_list, called from subs.php
 * - Used to add subactions to a list
 *
 * @param mixed[] $listOptions
 */
function int_listStopSpammer(&$listOptions)
{
    
    $listOptions['columns'] = elk_array_insert($listOptions['columns'], 'posts',
        array (
            'is_spammer' => array(
                'header' => array(
                    'value' => 'Spammer',
                ),
                'data' => array(
                    'db' => 'is_spammer',
                ),
                'sort' => array(
                    'default' => 'is_spammer',
                    'reverse' => 'is_spammer DESC',
                ),
            ),
        ), 'after'
    );

    // Override the default call so we can add the is_spammer check to the returned values
    $listOptions['get_items']['file']       = SOURCEDIR . '/StopSpammer.integration.php';
    $listOptions['get_items']['function']   = 'int_spammer_getMembers';
}


function int_spammer_getMembers($start, $items_per_page, $sort, $where, $where_params = array(), $get_duplicates = false) 
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

/**
 * stopspammer_settings()
 *
 * - Defines our settings array and uses our settings class to manage the data
 */
function stopspammer_settings()
{
	global $txt, $context, $scripturl, $modSettings;
	loadLanguage('StopSpammer');
	// Lets build a settings form
	require_once(SUBSDIR . '/SettingsForm.class.php');
	// Instantiate the form
	$stopSpammerSettings = new Settings_Form();
	// All the options, well at least some of them!
	$config_vars = array(
		array('check', 'stopspammer_enabled', 'postinput' => $txt['stopspammer_enabled_desc']),
		array('check', 'stopspammer_block_register'),
		array('title', 'stopforumspam_options'),
		array('check', 'stopforumspam_enabled', 'postinput' => $txt['stopforumspam_enabled_desc']),
		array('check', 'stopforumspam_ip_check'),
		array('check', 'stopforumspam_email_check'),
		array('check', 'stopforumspam_username_check'),
		array('int', 'stopforumspam_threshold'),
		array('title', 'spamhaus_options'),
		array('check', 'spamhaus_enabled', 'postinput' => $txt['spamhaus_enabled_desc']),
		array('title', 'projecthoneypot_options'),
		array('check', 'projecthoneypot_enabled', 'postinput' => $txt['projecthoneypot_enabled_desc']),
		array('text', 'projecthoneypot_key'),
		array('int', 'projecthoneypot_threshold'),
		array('int', 'projecthoneypot_history'),
	);
	// Load the settings to the form class
	$stopSpammerSettings->settings($config_vars);
	// Saving?
	if (isset($_GET['save']))
	{
		checkSession();
		Settings_Form::save_db($config_vars);
		redirectexit('action=admin;area=securitysettings;sa=stopspammer');
	}
	// Continue on to the settings template
	$context['settings_title'] = $txt['stopspammer_title'];
	$context['page_title'] = $context['settings_title'] = $txt['stopspammer_settings'];
	$context['post_url'] = $scripturl . '?action=admin;area=securitysettings;sa=stopspammer;save';
	Settings_Form::prepare_db($config_vars);
}

function int_memberListStopSpammer()
{
	global $context;

	if(allowedTo('admin_forum')) {
		$context['columns']['is_spammer'] = array ( 
			'label' 		=> 'Spammer' , 
			'class'			=> 'is_spammer',
			'default_sort_rev' 	=> 'true',
			'sort'			=> array ( 
				'down' 	=> 'mem.is_spammer DESC',
				'up'	=> 'mem.is_spammer ASC'
			),
		);
	}
}

function int_loadMemberDataStopSpammer(&$select_columns, &$select_tables, $set)
{
	if(allowedTo('admin_forum')) {
		$select_columns .= ', mem.is_spammer AS is_spammer';
	}
}

function int_memberContextStopSpammer($user, $custom_fields)
{
	global $memberContext, $user_profile;

	if(allowedTo('admin_forum')) {
		if($user_profile[$user]['is_spammer'] == 0) {
			$memberContext[$user]['is_spammer'] = '<i class="icon i-hand-up"></i>';
		}
		else {
			$memberContext[$user]['is_spammer'] = '<i class="icon i-check"></i>';
		}
	}
    
}


?>
