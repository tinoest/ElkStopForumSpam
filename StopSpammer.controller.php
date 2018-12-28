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
        require_once(SUBSDIR . '/Action.class.php');
		// Where do you want to go today?
		$subActions = array(
			'index'		=> array($this, 'action_check'),
			'check' 	=> array($this, 'action_check'),
		);

		// We like action, so lets get ready for some
		$action = new Action('');
		// Get the subAction, or just go to action_index
		$subAction = $action->initialize($subActions, 'index');

		// Finally go to where we want to go
		$action->dispatch($subAction);
	}

	public function action_check()
	{
		global $context, $scripturl, $modSettings;

		require_once(SUBSDIR . '/StopSpammer.subs.php');

        if(array_key_exists('u', $_GET)) {
            stopSpammerCheckUser($_GET['u']);
            redirectexit('action=profile;u='.$_GET['u']);
        }

        redirectexit('action=admin;area=viewmembers;');
	}
	
}
