<?php
/**
 * Custom API for external login plugin.
 *
 * @param array $CTX See init.php.
 * @copyright Copyright (c) 2020 Patrick Lai
 */
use PL2010\WordPress\XLogin;
use PL2010\WordPress\XLoginApi;

/** Custom APIs for external user registration. */
add_action('rest_api_init', function() use($CTX) /*{{{*/ {
	$xlogin = XLogin::getInstance($CTX['plugin']);
	$xapi = new XLoginApi($xlogin);
	$namespace = 'pl2010/xlogin/v1';

	//--------------------------------------------------------------
	// Retrieve customization configuration. {{{
	//
	register_rest_route($namespace, '/customize', [
		'methods' => 'GET',
		'callback' => function($req) use($xlogin) {
			$conf = $xlogin->getCustomization();
			return XLoginApi::checkError($conf) ?? [
				'data' => $conf,
			];
		},
		'permission_callback' => 'PL2010\WordPress\XLoginApi::checkPermRead',
	]);
	// }}}
	//--------------------------------------------------------------
	// Update customization configuration. {{{
	//
	register_rest_route($namespace, '/customize', [
		'methods' => 'POST',
		'callback' => function($req) use($xlogin) {
			$conf = $req->get_param('data');
			$conf = $xlogin->updateCustomization($conf);
			return XLoginApi::checkError($conf) ?? [
				'data' => $conf,
			];
		},
		'args' => [
			'data' => [
				'required' => true,
				'validate_callback' => function($data) {
					return is_array($data);
				},
			],
		],
		'permission_callback' => 'PL2010\WordPress\XLoginApi::checkPermUpdate',
	]);
	// }}}
	//--------------------------------------------------------------
	// Miscellaneous admin API. {{{
	//
	register_rest_route($namespace, '/admin', [
		'methods' => 'POST',
		'callback' => function($req) use($xapi) {
			$op = $req->get_param('op');
			$params = $req->get_param('params');
			$result = $xapi->performAdminOp($op, $params);
			return XLoginApi::checkError($result) ?? [
				'result' => $result,
			];
		},
		'args' => [
			'op' => [
				'required' => true,
				'validate_callback' => function($op) {
					return is_string($op);
				},
			],
			'params' => [
				'required' => false,
				'validate_callback' => function($params) {
					return is_array($params);
				},
			],
		],
		'permission_callback' => 'PL2010\WordPress\XLoginApi::checkPermAdmin',
	]);
	// }}}
	//--------------------------------------------------------------
	// Get list of external services. {{{
	//
	register_rest_route($namespace, '/xsvcs', [
		'methods' => 'GET',
		'callback' => function($req) use($CTX, $xlogin) {
			$pluginUrl = plugin_dir_url($CTX['plugin']);
			$imgBaseUrl = "{$pluginUrl}images/";

			$result = [];
			foreach ($xlogin->getAuthTypes() as $type) {
				$result[] = [
					'type' => $type,
					'name' => $xlogin->getAuthTypeName($type),
					'model' => $xlogin->getLoginModel($type),
					'redir' => $xlogin->getCallbackUri('recv', $type),
					'icon' => $imgBaseUrl.$type.'/btn-signin.png',
				];
			}
			return [
				'data' => $result,
			];
		},
		'permission_callback' => 'PL2010\WordPress\XLoginApi::checkPermRead',
	]);
	// }}}
	//--------------------------------------------------------------
	// Look up an external service config. {{{
	//
	register_rest_route($namespace, '/xsvcs/(?P<type>.+)/config', [
		'methods' => 'GET',
		'callback' => function($req) use($xlogin) {
			$type = $req->get_param('type');
			$conf = $xlogin->getProviderConfig($type, $enabledOnly=false);
			return XLoginApi::checkError($conf) ?? [
				'data' => $conf,
			];
		},
		'args' => [
			'type' => [
				'required' => true,
				'validate_callback' => function($type) use($xlogin) {
					return in_array($type, $xlogin->getAuthTypes());
				},
			],
		],
		'permission_callback' => 'PL2010\WordPress\XLoginApi::checkPermRead',
	]);
	// }}}
	//--------------------------------------------------------------
	// Update an external service config. {{{
	//
	register_rest_route($namespace, '/xsvcs/(?P<type>.+)/config', [
		'methods' => 'POST',
		'callback' => function($req) use($xlogin) {
			$type = $req->get_param('type');
			$conf = $req->get_param('data');

			$conf = $xlogin->updateProviderConfig($type, $conf);
			return XLoginApi::checkError($conf) ?? [
				'data' => $conf,
			];
		},
		'args' => [
			'type' => [
				'required' => true,
				'validate_callback' => function($type) use($xlogin) {
					return in_array($type, $xlogin->getAuthTypes());
				},
			],
			'data' => [
				'required' => true,
				'validate_callback' => function($data) {
					return is_array($data);
				},
			],
		],
		'permission_callback' => 'PL2010\WordPress\XLoginApi::checkPermUpdate',
	]);
	// }}}
	//--------------------------------------------------------------
	// Create an external user registration. {{{
	//
	register_rest_route($namespace, '/xusers', [
		'methods' => 'POST',
		'callback' => function($req) use($xapi) {
			$data = $req->get_param('data');
			$hint = true;
			if (isset($data['hint'])) {
				$hint = filter_var(
					$data['hint'],
					FILTER_VALIDATE_BOOLEAN,
					FILTER_NULL_ON_FAILURE
				) ?? true;
			}
			$reg = $xapi->registerXLogin(
				$data['alias'],
				$data['login'],
				$replace=true,
				$hint
			);
			return XLoginApi::checkError($reg) ?? [
				'data' => $reg,
			];
		},
		'args' => [
			'data' => [
				'required' => true,
				'validate_callback' => function($data, $req, $key) {
					if (!is_array($data)
						|| !XLoginApi::validateUserLogin(
							$data['login'] ?? null, $req, "$key.login"
						)
						|| !XLoginApi::validateUserAlias(
							$data['alias'] ?? null, $req, "$key.user"
						)
					) {
						return false;
					}
					return true;
				},
			],
		],
		'permission_callback' => 'PL2010\WordPress\XLoginApi::checkPermCreate',
	]);
	// }}}
	//--------------------------------------------------------------
	// Retrieve list of external user registrations. {{{
	//
	register_rest_route($namespace, '/xusers', [
		'methods' => 'GET',
		'callback' => function($req) use($xapi) {
			$off = $req->get_param('offset');
			$max = $req->get_param('limit');
			$search = [
				'login' => $req->get_param('login'),
				'alias' => $req->get_param('alias'),
			];
			$total = 0;
			$rlist = $xapi->getXLoginList($search, $off, $max, $total);
			return XLoginApi::checkError($rlist) ?? [
				'data' => $rlist,
				'total' => $total,
				'offset' => $off,
			];
		},
		'args' => [
			'offset' => [
				'default' => 0,
				'sanitize_callback' => function($off) {
					$off = (int)filter_var($off, FILTER_VALIDATE_INT);
					return $off >= 0 ? $off : 0;
				},
			],
			'limit' => [
				'default' => 10,
				'sanitize_callback' => function($max) {
					$max = (int)filter_var($max, FILTER_VALIDATE_INT);
					return ($max >= 1 && $max <= 100) ? $max : 10;
				},
			],
			'login' => [
				'required' => false,
				'sanitize_callback' => function($login) {
					return sanitize_text_field($login);
				},
			],
			'alias' => [
				'required' => false,
				'sanitize_callback' => function($alias) {
					return sanitize_text_field($alias);
				},
			],
		],
		'permission_callback' => 'PL2010\WordPress\XLoginApi::checkPermRead',
	]);
	// }}}
	//--------------------------------------------------------------
	// Look up external user registration by ID. {{{
	//
	register_rest_route($namespace, '/xusers/(?P<id>\d+)', [
		'methods' => 'GET',
		'callback' => function($req) use($xapi) {
			$reg = $xapi->getXLoginById($req->get_param('id'));
			return XLoginApi::checkError($reg) ?? [
				'data' => $reg,
			];
		},
		'permission_callback' => 'PL2010\WordPress\XLoginApi::checkPermRead',
	]);
	// }}}
	//--------------------------------------------------------------
	// Look up external login registration by alias. {{{
	//
	register_rest_route($namespace, '/xusers/alias/(?P<alias>.+)', [
		'methods' => 'GET',
		'callback' => function($req) use($xapi) {
			$reg = $xapi->getXLoginByAlias($req->get_param('alias'));
			return XLoginApi::checkError($reg) ?? [
				'data' => $reg,
			];
		},
		'args' => [
			'alias' => [
				'required' => true,
				'sanitize_callback' => function($alias) {
					return sanitize_text_field($alias);
				},
			],
		],
		'permission_callback' => 'PL2010\WordPress\XLoginApi::checkPermRead',
	]);
	// }}}
	//--------------------------------------------------------------
	// Delete a registration. {{{
	//
	register_rest_route($namespace, '/xusers/(?P<id>\d+)', [
		'methods' => 'DELETE',
		'callback' => function($req) use($xapi) {
			$result = $xapi->deleteXLogin($req->get_param('id'));
			return XLoginApi::checkError($result) ?? [
				'success' => $result,
			];
		},
		'permission_callback' => 'PL2010\WordPress\XLoginApi::checkPermDelete',
	]);
	// }}}
	//--------------------------------------------------------------
	// Upload CSV file of external user registrations. {{{
	//
	register_rest_route($namespace, '/xusers/upload', [
		'methods' => 'POST',
		'callback' => function($req) use($xapi) {
			$hint = $req->get_param('hint');
			$incr = $req->get_param('incr');
			$emax = $req->get_param('emax');
			$user = (string)$req->get_param('user');
			$file = null;
			if (isset($_FILES['file']['tmp_name'])) {
				// Calling realpath() on 'tmp_name' really is unnecessary,
				// as that is filled in by PHP.
				$file = realpath($_FILES['file']['tmp_name']);
			}
			if ($file == '') {
				return XLoginApi::checkError(new WP_Error(
					'input-missing',
					'No file uploaded.'
				));
			}
			$input = fopen($file, 'r');
			if (!$input) {
				return XLoginApi::checkError(new WP_Error(
					'internal-error',
					'Uploaded file unavailable.'
				));
			}

			try {
				if (!$incr) {
					$result = $xapi->deleteAllXLogins();
					if ($error = XLoginApi::checkError($result))
						return $error;
				}

				$batch = call_user_func(function($fh, $defaultUser) {
					while ($rec = fgetcsv($fh)) {
						// Expect alias and optional login in each CSV record.
						$alias = $rec[0];
						$login = empty($rec[1]) ? $defaultUser : $rec[1];
						yield [
							'login' => $login,
							'alias' => $alias,
						];
					}
				}, $input, $user);
				$errors = null;
				$result = $xapi->registerXLoginBatch(
					$batch,
					$errors,
					$emax,
					$hint
				);
			}
			finally {
				fclose($input);
			}

			return XLoginApi::checkError($result) ?? [
				'data' => [
					'success' => $result[0],
					'failure' => $result[1],
					'skipped' => $result[2],
					'errors' => $errors,
				],
			];
		},
		'args' => [
			'emax' => [
				'default' => 10,
				'sanitize_callback' => function($emax) {
					$emax = filter_var($emax, FILTER_VALIDATE_INT);
					return ($emax > 1 && $emax <= 100) ? $emax : 10;
				},
			],
			'hint' => [
				'default' => true,
				'sanitize_callback' => function($hint) {
					$hint = filter_var(
						$hint,
						FILTER_VALIDATE_BOOLEAN,
						FILTER_NULL_ON_FAILURE
					);
					return $hint === null ? true : $hint;
				},
			],
			'incr' => [
				'default' => true,
				'sanitize_callback' => function($incr) {
					$incr = filter_var(
						$incr,
						FILTER_VALIDATE_BOOLEAN,
						FILTER_NULL_ON_FAILURE
					);
					return $incr === null ? true : $incr;
				},
			],
			'user' => [
				'default' => false,
				'validate_callback' => function($user, $req, $key) {
					if ($user == '')
						return true;
					return XLoginApi::validateUserLogin($user, $req, $key);
				},
			],
		],
		'permission_callback' => 'PL2010\WordPress\XLoginApi::checkPermCreate',
	]);
	// }}}
	//--------------------------------------------------------------
} /*}}}*/);

// vim: set ts=4 noexpandtab fdm=marker syntax=php: ('zR' to unfold all)
