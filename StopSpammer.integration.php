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
 * integrate_register_check hook
 *
 * - Used to check the user against the stop spammer database on registration
 */
function int_stopSpammer(&$regOptions, &$reg_errors)
{
	global $modSettings;
        
    $regOptions['spammer'] = 0;
    
    // No point running if disabled
    if(empty($modSettings['stopspammer_enabled'])) {
        return;
    }
   
    require_once(SUBSDIR . '/StopSpammer.subs.php');
    
    $spammer = false;

    if($modSettings['stopforumspam_enabled']) {
        $ip         = null;
        $username   = null; 
        $email      = null;

        if(isset($modSettings['stopforumspam_threshold'])) {
            $confidenceThreshold = $modSettings['stopforumspam_threshold'];
        }
        else {
            $confidenceThreshold = 50;
        }

        if(!empty($modSettings['stopforumspam_ip_check'])) {
            $ip         = $regOptions['ip'];
        }

        if(!empty($modSettings['stopforumspam_username_check'])) {
            $username   = $regOptions['username'];
        }

        if(!empty($modSettings['stopforumspam_email_check'])) {
            $email      = $regOptions['email'];
        }

        stopSpammerStopforumspamCheck($spammer, $confidenceThreshold, $ip, $username, $email);

    }

    if($modSettings['spamhaus_enabled']) {
        stopSpammerSpamhausCheck($spammer, $regOptions['ip']);
    }
 
    if($modSettings['projecthoneypot_enabled'] && !empty($modSettings['projecthoneypot_key'])) {
        stopSpammerProjecthoneypotCheck($spammer, $modSettings['projecthoneypot_threshold'], $modSettings['projecthoneypot_key'], $regOptions['ip']);
    }
    
    if($spammer == true && !empty($modSettings['stopspammer_block_register'])) {            
        $reg_errors->addError('not_guests');
    }
    else {
        $regOptions['spammer']              = 1;
        $regOptions['require']              = 'approval';
        $modSettings['registration_method'] = 2;
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

    $knownInts[] = 'is_spammer';
    $regOptions['register_vars']['is_spammer'] = $regOptions['spammer'];

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
    global $modSettings;

    if(!empty($modSettings['stopspammer_enabled'])) {
        $actionArray['stopspammer'] = array (
            'StopSpammer.controller.php',
            'StopSpammer_Controller',
            'action_index'
        );
    }

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
                'custom_url'    => $scripturl . '?action=stopspammer;sa=check;'.$memID,
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

    if(!empty($modSettings['stopforumspam_key'])) {
        $profile_areas['profile_action']['areas'] = elk_array_insert($profile_areas['profile_action']['areas'], 'checkmember', array(
                'reportmember'       => array(
                    'label'         => $txt['stopspammer_report'],
                    'custom_url'    => $scripturl . '?action=stopspammer;sa=report;'.$memID,
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
    $listOptions['get_items']['file']       = SUBSDIR . '/StopSpammer.subs.php';
    $listOptions['get_items']['function']   = 'stopSpammerGetMembers';
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
		array('text', 'stopforumspam_key'),
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
