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
	global $modSettings;
        
    require_once(SUBSDIR . '/Package.subs.php');

    if($modSettings['stopspammer_enabled']) {
        if(isset($modSettings['stopspammer_threshold'])) {
            $confidenceThreshold = $modSettings['stopspammer_threshold'];
        }
        else {
            $confidenceThreshold = 50;
        }

        $url    = 'https://api.stopforumspam.org/api';
        $data	= '?';
        if(!empty($modSettings['stopspammer_ip_check'])) {
            $data   .= 'ip='.$regOptions['ip'].'&';
        }
        if(!empty($modSettings['stopspammer_username_check'])) {
            $data	.= 'username=' . urlencode($regOptions['username']).'&';
        }
        if(!empty($modSettings['stopspammer_email_check'])) {
            $data	.= 'email=' . urlencode($regOptions['email']).'&';
        }
        $data	.= 'json';

        $result	= fetch_web_data($url.$data);
        $result = json_decode($result, true);

        if ( is_array($result) && $result['success'] === 1 ) {
            if ( ( $result['ip']['appears'] === 1 )  && ( $result['ip']['confidence'] > $confidenceThreshold ) ) {
                $reg_errors->addError('not_guests');
            }
            if ( ( $result['username']['appears'] === 1 )  && ( $result['username']['confidence'] > $confidenceThreshold ) ) {
                $reg_errors->addError('not_guests');
            }
            if ( ( $result['email']['appears'] === 1 )  && ( $result['email']['confidence'] > $confidenceThreshold ) ) {
                $reg_errors->addError('bad_email');
            }
        }
    }

    if($modSettings['spamhaus_enabled']) {
        $revip  = implode(".", array_reverse(explode(".", $regOptions['ip'], 4), false));
        $dns    = dns_get_record($revip . ".zen.spamhaus.org");
        if ($dns != null && count($dns) > 0) {
            foreach ($dns as $entry) {
                if (in_array($entry['ip'], array('127.0.0.2', '127.0.0.3', '127.0.0.4'))) {
                    $reg_errors->addError('not_guests');
                }
            }
        }
    }
 
    if($modSettings['projecthoneypot_enabled'] && !empty($modSettings['projecthoneypot_key'])) {
        $response = explode( ".", gethostbyname($modSettings['projecthoneypot_key'].".".implode(".", array_reverse(explode(".", $regOptions['ip']))).".dnsbl.httpbl.org" ) ); 
        if ($resonse != null && count($response) > 0) {
            $reg_errors->addError('not_guests');
        }
    }   
    
	return;

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
	$admin_areas['config']['areas']['addonsettings']['subsections']['stopspammer'] = array($txt['stopspammer_title']);
}

/**
 * int_adminStopSpammer()
 *
 * - Admin Hook, integrate_sa_modify_modifications, called from AddonSettings.controller.php
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
		array('title', 'stopspammer_options'),
		array('check', 'stopspammer_ip_check'),
		array('check', 'stopspammer_email_check'),
		array('check', 'stopspammer_username_check'),
		array('int', 'stopspammer_threshold'),
		array('title', 'spamhaus_options'),
		array('check', 'spamhaus_enabled', 'postinput' => $txt['spamhaus_enabled_desc']),
	);
	// Load the settings to the form class
	$stopSpammerSettings->settings($config_vars);
	// Saving?
	if (isset($_GET['save']))
	{
		checkSession();
		Settings_Form::save_db($config_vars);
		redirectexit('action=admin;area=addonsettings;sa=stopspammer');
	}
	// Continue on to the settings template
	$context['settings_title'] = $txt['stopspammer_title'];
	$context['page_title'] = $context['settings_title'] = $txt['stopspammer_settings'];
	$context['post_url'] = $scripturl . '?action=admin;area=addonsettings;sa=stopspammer;save';
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
