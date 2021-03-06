<?php

/**
* Class representing Yubikey token handler objects
*
* @copyright	Copyright Madfish (Simon Wilkinson) 2011
* @license		http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License (GPL)
* @since		1.0
* @author		Madfish (simon@isengard.biz)
* @package		yubikey
* @version		$Id$
*/

class YubikeyTokenHandler extends icms_ipf_Handler {

	/**
	 * Constructor
	 */
	public function __construct(& $db) {
		parent::__construct($db, 'token', 'token_id', 'public_id', 'public_id',
				'yubikey');
	}

	/**
	 * Toggles Yubikey authentication online or offline
	 *
	 * @param int $token_id
	 * @param str $field
	 * @return int $visibility
	 */
	public function changeStatus($token_id, $field) {

		$visibility = $tokenObj = '';

		$tokenObj = $this->get($token_id);
		if ($tokenObj->getVar($field, 'e') == TRUE) {
			$tokenObj->setVar($field, 0);
			$visibility = 0;
		} else {
			$tokenObj->setVar($field, 1);
			$visibility = 1;
		}
		$this->insert($tokenObj, TRUE);

		return $visibility;
	}

	/** 
	 * Check validity of key and user details before saving
	 * 
	 * @param type $tokenObj
	 * @return boolean 
	 */
	protected function beforeSave(& $tokenObj)
	{
		$one_time_password = $yubikey_user = '';
		$valid_key = FALSE;
		$yubikey_token_handler = icms_getModuleHandler('token', basename(dirname(dirname(__FILE__))), 
				'yubikey');
		
		$user_id = $tokenObj->uid();
		$one_time_password = $tokenObj->getVar('public_id');
		$public_id_length = strlen($tokenObj->getVar('public_id'));
		
		// Check the length of the public_id field. Admins have a choice of entering either:
		// 
		// 1. The public ID of the key (the first 12 characters of the key output), or
		// 
		// 2. Discharging a Yubikey in the field (which will result in 44 characters of output, 
		// ie. 12 character public ID + 32 character one-time password). If a 44 character input is 
		// detected, the module will validate it against the Yubico validation servers as an 
		// additional test.
		// 
		// Output must be alphanumeric only.
		
		switch ($public_id_length)
		{
			case "12": // Only the public ID of the key was submitted
				$valid_key = ctype_alnum($one_time_password) ? TRUE : FALSE;
				break;
			
			case "44": // Public ID + one time password submitted, validate the key against Yubico
				$valid_key = ctype_alnum($one_time_password) ? TRUE : FALSE;
				if ($valid_key)
				{
					$valid_key = $tokenObj->verify($one_time_password);
					$tokenObj->setVar('public_id', substr($tokenObj->getVar('public_id'), 0,12));
				}
				break;
			
			default: // If some other length was entered, reject input.
				$valid_key = FALSE;
				break;
		}

		// Check for duplicate Yubikeys. Each key may only be assigned to one account, duplicates are rejected.
		if ($valid_key)
		{
			$criteria = icms_buildCriteria(array('public_id' => $tokenObj->getVar('public_id')));
			$duplicate_keys = $yubikey_token_handler->getObjects($criteria);
			$duplicate = array_shift($duplicate_keys);
			if (!empty($duplicate))
			{
				if ($tokenObj->getVar('token_id') != $duplicate->getVar('token_id'))
				{
					$tokenObj->setErrors("Error: Duplicate keys are not allowed.");
					$valid_key = FALSE;
				}
			}
		}
		
		// Check that the designated user actually exists. If the user isn't there, reject.
		$member_handler = icms::handler("icms_member");
		$yubikey_user = $member_handler->getUser($user_id);
		if (empty($yubikey_user))
		{
			$valid_key = FALSE;
		}
		
		return $valid_key;
	}
}