<?php

/**
 * @package "YAPortal" Addon for Elkarte
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

class StopSpammer_Controller extends Action_Controller
{
	public function action_index()
	{

        $this->action_check();

	}

	public function action_check()
	{
		global $context, $scripturl, $modSettings;

		require_once(SUBSDIR . '/StopSpammer.subs.php');

        if(array_key_exists('member_id')) {
            stopSpammerCheckUser($memberID):
        }

        redirectexit('action=admin;area=viewmembers;');
	}
	
}
