<?php
/**
 * OAuth2 provider for Yahoo!.
 * @copyright Copyright (c) 2020 Patrick Lai
 */

namespace PL2010\WordPress\OAuth2;

use League\OAuth2\Client\Grant\AbstractGrant;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\GoogleUser;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Tool\BearerAuthorizationTrait;
use Psr\Http\Message\ResponseInterface;

/**
 * OAuth2 client provider for Yahoo!.
 */
class YahooProvider extends AbstractProvider /*{{{*/
{
	use BearerAuthorizationTrait;

	// Override various {@link League\OAuth2\Client\Provider\AbstractProvider}
	// methods.
	//
	protected function createAccessToken(array $resp, AbstractGrant $grant) {
		if (empty($resp['resource_owner_id'])
			&& !empty($resp['xoauth_yahoo_guid'])
		) {
			$resp['resource_owner_id'] = $resp['xoauth_yahoo_guid'];
		}
		$token = parent::createAccessToken($resp, $grant);
		return $token;
	}

	// Implement various {@link League\OAuth2\Client\Provider\AbstractProvider}
	// methods.
	//
	public function getBaseAuthorizationUrl() {
		return 'https://api.login.yahoo.com/oauth2/request_auth';
	}

	public function getBaseAccessTokenUrl(array $params) {
		return 'https://api.login.yahoo.com/oauth2/get_token';
	}

	public function getResourceOwnerDetailsUrl(AccessToken $token) {
		return 'https://api.login.yahoo.com/openid/v1/userinfo';
	}

	protected function getDefaultScopes() {
		return [
			'openid',
			'profile',
			'email',
		//	'sdpp-r',
		//	'sdpp-w',
		];
	}

	protected function checkResponse(ResponseInterface $resp, $data) {
		$code = 0;

		// Invalid response?
		if ($data && !is_array($data))
			throw new IdentityProviderException('invalid response',$code,$data);

		// No error indication (per OAuth2)?
		if (empty($data['error']))
			return;

		$msg = $data['error_description'] ?? $data['error'];
		throw new IdentityProviderException($msg, $code, $data);
	}

	protected function createResourceOwner(array $resp, AccessToken $token) {
		return new YahooUser($resp);
	}
} /*}}}*/

// vim: set ts=4 noexpandtab fdm=marker syntax=php: ('zR' to unfold all)
