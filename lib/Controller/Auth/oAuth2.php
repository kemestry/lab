<?php
/**
 * oAuth2 Against OpenTHC Authentication System
 */

namespace App\Controller\Auth;

use Edoceo\Radix;
use Edoceo\Radix\Session;

class oAuth2 extends \OpenTHC\Controller\Base
{
	/**
		Verify the State or DIE
	*/
	function checkState()
	{
		$a = $_SESSION['oauth2-state'];
		$b = $_GET['state'];

		unset($_SESSION['oauth2-state']);

		if (empty($a)) {
			_exit_text('Invalid State [CAO#024]', 400);
			Session::flash('fail', 'Invalid State');
			Radix::redirect('/auth/fail');
		}

		if (empty($b)) {
			_exit_text('Invalid State [CAO#030]', 400);
			Session::flash('fail', 'Invalid State');
			Radix::redirect('/auth/fail');
		}

		if ($a != $b) {
			_exit_text('Invalid State [CAO#036]', 400);
			Session::flash('fail', 'Invalid State');
			Radix::redirect('/');
		}
	}

	/**
		Return the oAuth Provider
	*/
	protected function getProvider($r=null)
	{
		$cfg = \OpenTHC\Config::get('oauth');

		$u = sprintf('https://%s/auth/back?%s', $_SERVER['SERVER_NAME'], http_build_query(array('r' => $r)));
		$u = trim($u, '?');
		$p = new \League\OAuth2\Client\Provider\GenericProvider([
			'clientId' => $cfg['client'] ?: $_SERVER['SERVER_NAME'],
			'clientSecret' => $cfg['secret'],
			'redirectUri' => $u,
			'urlAuthorize' => $cfg['service_authz'],
			'urlAccessToken' => $cfg['service_token'],
			'urlResourceOwnerDetails' => $cfg['service_ident'],
			'verify' => true
		]);

		return $p;
	}
}
