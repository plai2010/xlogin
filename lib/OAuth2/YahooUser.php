<?php
/**
 * OAuth2 user for Yahoo!.
 * @copyright Copyright (c) 2020 Patrick Lai
 */

namespace PL2010\WordPress\OAuth2;

use League\OAuth2\Client\Provider\ResourceOwnerInterface;

/**
 * OAuth2 Yahoo! user.
 */
class YahooUser implements ResourceOwnerInterface /*{{{*/
{
	/** @var array User info. */
	private $uinfo;

	/**
	 * Construct Yahoo! user from the get user info API response.
	 * @param array $resp An OpenID UserInfo response.
	 */
	public function __construct($resp) {
		$this->uinfo = $resp;
	}

	/**
	 * Get email address of Yahoo! user.
	 * @return string
	 */
	public function getEmail() {
		return $this->uinfo['email'] ?? null;
	}

	/**
	 * Get first name of Yahoo! user.
	 * @return string
	 */
	public function getFirstName() {
		return $this->uinfo['given_name'] ?? null;
	}

	/**
	 * Get last name of Yahoo! user.
	 * @return string
	 */
	public function getLastName() {
		return $this->uinfo['family_name'] ?? null;
	}

	/**
	 * Get locale of Yahoo! user.
	 * @return string
	 */
	public function getLocale() {
		return $this->uinfo['locale'] ?? null;
	}

	/**
	 * Implement ResourceOwnerInterface::getId().
	 */
	public function getId() {
		return $this->uinfo['sub'] ?? null;
	}

	/**
	 * Implement ResourceOwnerInterface::toArray().
	 */
	public function toArray() {
		return $this->uinfo;
	}
} /*}}}*/

// vim: set ts=4 noexpandtab fdm=marker syntax=php: ('zR' to unfold all)
