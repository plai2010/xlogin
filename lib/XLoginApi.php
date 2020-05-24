<?php
/**
 * APIs for external login.
 * @copyright Copyright (c) 2020 Patrick Lai
 */

namespace PL2010\WordPress;

use WP_Error;
use WP_REST_Request;
use WP_User;

use Iterator;

/**
 * API support for external login service.
 */
class XLoginApi /*{{{*/
{
	/** @var \PL2010\WordPress\XLogin External login service to use. */
	private $xlogin;

	/**
	 * Construct handler for external login service API.
	 * @param \PL2010\WordPress\XLogin $xlogin External login service to use.
	 */
	public function __construct($xlogin) /*{{{*/
	{
		$this->xlogin = $xlogin;
	} /*}}}*/

	/**
	 * Produce error response for API call.
	 * @param mixed $result Result to check; WP_Error is indication of error.
	 * @return array|null Error responses or null if no error detected.
	 */
	public static function checkError($result) /*{{{*/
	{
		if (!$result instanceof WP_Error)
			return null;
		return [
			'error' => $result->get_error_code(),
			'error_description' => $result->get_error_message(),
		];
	} /*}}}*/

	/**
	 * REST callback to check permission for general admin operations.
	 * @return boolean
	 */
	public static function checkPermAdmin() /*{{{*/
	{
		return current_user_can('manage_options');
	} /*}}}*/

	/**
	 * REST callback to check permission to create items.
	 * @return boolean
	 */
	public static function checkPermCreate() /*{{{*/
	{
		return current_user_can('manage_options');
	} /*}}}*/

	/**
	 * REST callback to check permission to delete items.
	 * @return boolean
	 */
	public static function checkPermDelete() /*{{{*/
	{
		return current_user_can('manage_options');
	} /*}}}*/

	/**
	 * REST callback to check permission to read items.
	 * @return boolean
	 */
	public static function checkPermRead() /*{{{*/
	{
		return current_user_can('manage_options');
	} /*}}}*/

	/**
	 * REST callback to check permission to update items.
	 * @return boolean
	 */
	public static function checkPermUpdate() /*{{{*/
	{
		return current_user_can('manage_options');
	} /*}}}*/

	/**
	 * Delete all external login registrations.
	 * @return int|WP_Error Number of registrations deleted, or error.
	 */
	public function deleteAllXLogins() /*{{{*/
	{
		$result = $this->xlogin->registrationWipe();
		return $result;
	} /*}}}*/

	/**
	 * Delete an external login registration.
	 * @param int $id Registration ID.
	 * @return boolean|WP_Error Whether registration deleted, or error.
	 */
	public function deleteXLogin($id) /*{{{*/
	{
		$result = $this->xlogin->registrationDelete($id);
		return $result;
	} /*}}}*/

	/**
	 * Retrieve an external login registration user's external alias.
	 * @param string $alias External alias.
	 * @return array|WP_Error Registration data or error.
	 */
	public function getXLoginByAlias($alias) /*{{{*/
	{
		if ($err = static::checkUserAlias($alias, $type, $name))
			return $err;

		$xhash = XLogin::getXUserHash($type, $name);
		$reg = $this->xlogin->registrationGetBy('xhash', $xhash);
		return $reg instanceof WP_Error ? $reg : ($reg ? [
			'id' => $reg['id'],
			'user' => $reg['login'],
			'hint' => $reg['obscure'],
			'hash' => $reg['xhash'],
		] : null);
	} /*}}}*/

	/**
	 * Retrieve an external login registration by its ID.
	 * @param int $id Registration ID.
	 * @return array|WP_Error Registration data or error.
	 */
	public function getXLoginById($id) /*{{{*/
	{
		$reg = $this->xlogin->registrationGetBy('id', $id);
		return $reg instanceof WP_Error ? $reg : ($reg ? [
			'id' => $reg['id'],
			'user' => $reg['login'],
			'hint' => $reg['obscure'],
			'hash' => $reg['xhash'],
		] : null);
	} /*}}}*/

	/**
	 * Get list of external login registrations.
	 * @param array $srch Search criteria, e.g. 'login'.
	 * @param int $off Offset for pagination.
	 * @param int $max Limit for pagination.
	 * @param int &$total Return total # of registrations here if set.
	 * @return array|WP_Error List of registrations or error.
	 */
	public function getXLoginList(
		$srch=null,
		$off=0,
		$max=10,
		&$total=null
	) /*{{{*/
	{
		if ($total !== null)
			$total = 0;

		$conds = [];
		if ($srch && is_array($srch)) {
			if (($login = $srch['login'] ?? '') != '') {
				$user = static::lookupWpUser($login);
				if (!$user)
					return [];
				$conds[] = [ 'uid', '=', $user->ID ];
			}
			if (($alias = $srch['alias'] ?? '') != '') {
				$pattern = '%'
					. str_replace([
							'\\', '%', '_',
						], [
							'\\\\', '\\%', '\\_',
						], $alias)
					. '%';
				$conds[] = [ 'obscure', 'like', $pattern ];
			}
		}

		$rlist = $this->xlogin->registrationList($conds, $off, $max, $total);
		return $rlist instanceof WP_Error ? $rlist : array_map(function($reg) {
			$user = get_user_by('ID', $reg['uid']);
			return [
				'id' => $reg['id'],
				'user' => $reg['login'],
				'hint' => $reg['obscure'],
				'hash' => $reg['xhash'],
			];
		}, $rlist);
	} /*}}}*/

	/**
	 * Look up WordPress user by login name or email address, etc.
	 * @param string $name User name.
	 * @param array $types User name fields to try, specifically.
	 * @return WP_User|null User found or null.
	 */
	protected static function lookupWpUser($name, $fields=null) /*{{{*/
	{
		foreach ($fields ?? [
			'login',
			'email',
		] as $fld) {
			$user = get_user_by($fld, $name);
			if ($user instanceof WP_User)
				return $user;
		}
		return null;
	} /*}}}*/

	/**
	 * Check user alias for proper format.
	 * @param string $alias Alias.
	 * @param string &$type Name type.
	 * @param string &$name External name of user.
	 * @return WP_Error Error if malformed alias; null if okay.
	 */
	protected static function checkUserAlias($alias, &$type, &$name) /*{{{*/
	{
		$pieces = explode(':', $alias, 2);
		if (count($pieces) != 2) {
			// Email address by default.
			if (is_email($alias)) {
				$type = 'email';
				$name = $alias;
				return null;
			}
			return new WP_Error('input-invalid', 'Malformed user alias.');
		}
		list($type, $name) = $pieces;
		return null;
	} /*}}}*/

	/**
	 * Perform a miscellaneous admin operation.
	 * @param string $op Operation to perform.
	 * @param array $params Parameters for the operation.
	 * @return array|WP_Error Operation result, or WP_Error.
	 */
	public function performAdminOp($op, $params) /*{{{*/
	{
		$result = [];
		$SUCCESS =& $result['success'];
		$ERR_MSG =& $result['err_msg'];

		switch ($op) {
		case 'check-guest':
			$guestLogin = $params['login'] ?? null;
			if ($guestLogin == '')
				return new WP_Error('input-invalid', 'Missing guest login.');
			$guestUser = static::lookupWpUser($guestLogin);
			if (!$guestUser) {
				$SUCCESS = false;
				$ERR_MSG = 'Invalid guest login.';
				break;
			}
			if ($SUCCESS = $this->xlogin->isAcceptableGuest($guestUser, $emsg))
				$result['login'] = $guestUser->user_login;
			else
				$ERR_MSG = $emsg;
			break;
		default:
			return new WP_Error(
				'input-invalid',
				"Unknown admin operation '$op'."
			);
		}
		return $result;
	} /*}}}*/

	/**
	 * Register an external login.
	 * @param string $alias External alias, e.g. 'email:jdoe@example.com'.
	 * @param string $who WordPress user login name or email address.
	 * @param boolean $replace Replace any current registration in conflict.
	 * @param boolean $hint Whether to include alias hint.
	 * @return array|WP_Error Registration info, or WP_Error.
	 */
	public function registerXLogin(
		$alias,
		$who,
		$replace=true,
		$hint=true
	) /*{{{*/
	{
		if ($err = static::checkUserAlias($alias, $type, $name))
			return $err;

		$user = static::lookupWpUser($who);
		if (!$user)
			return new WP_Error('input-invalid', 'Unknown user.');

		$reg = $this->xlogin->registrationAdd($type,$name,$user,$replace,$hint);
		return $reg instanceof WP_Error ? $reg : [
			'id' => $reg['id'],
			'user' => $reg['login'],
			'hint' => $reg['obscure'],
			'hash' => $reg['xhash'],
		];
	} /*}}}*/

	/**
	 * Register a batch of external logins.
	 * Incomplete registrations are skipped.
	 * @param Iterator $batch Yielding array with items: 'alias', 'login'.
	 * @param array &$errs List of errors details, up to $emax of them.
	 * @param int $emax Maximum number of errors to return in $errs.
	 * @param boolean $hint Whether to include alias hint.
	 * @return array Tuple of numbers: success, failures, skipped.
	 */
	public function registerXLoginBatch(
		$batch,
		&$errs=null,
		$emax=10,
		$hint=true
	) /*{{{*/
	{
		$succ = $fail = $skip = 0;
		$errs = null;
		foreach ($batch as $reg) {
			$alias = $reg['alias'] ?? null;
			$login = $reg['login'] ?? null;
			if ($alias == '' || $login == '') {
				++$skip;
				continue;
			}

			$result = $this->registerXLogin($alias, $login, $repl=true, $hint);
			if ($error = static::checkError($result)) {
				++$fail;
				if ($fail < $emax)
					$errs[] = $error;
				continue;
			}
			++$succ;
		}
		return [ $succ, $fail, $skip ];
	} /*}}}*/

	/**
	 * REST callback to validate external user alias as input.
	 * Format of alias is "type:value".
	 * @param string $alias External alias, e.g. "email:jdoe@example.com".
	 * @param WP_REST_Request $req REST request.
	 * @param string $key Input name for the alias.
	 * @return boolean True if valid user alias.
	 */
	public static function validateUserAlias($alias, $req, $key) /*{{{*/
	{
		if ($err = static::checkUserAlias($alias, $type, $name))
			return false;

		switch ($type) {
		case 'email':
			return is_email($name);
		default:
			return false;
		}
	} /*}}}*/

	/**
	 * REST callback to validate WordPress user login name as input.
	 * @param string $name WP user login name or email.
	 * @param WP_REST_Request $req REST request.
	 * @param string $key Input name for the reference.
	 * @return boolean True if valid WP user login.
	 */
	public static function validateUserLogin($name, $req, $key) /*{{{*/
	{
		return static::lookupWpUser($name) ? true : false;
	} /*}}}*/
} /*}}}*/

// vim: set ts=4 noexpandtab fdm=marker syntax=php: ('zR' to unfold all)
