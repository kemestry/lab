<?php
/**
 * oAuth2 Base Controller
 */

namespace App\Controller\Auth;

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
		}

		if (empty($b)) {
			_exit_text('Invalid State [CAO#030]', 400);
		}

		if ($a != $b) {
			_exit_text('Invalid State [CAO#036]', 400);
		}
	}

	/**
	 * Return the oAuth Provider
	 */
	protected function getProvider($r=null)
	{
		$cfg = \OpenTHC\Config::get('oauth');

		$u = sprintf('https://%s/auth/oauth/back?%s', $_SERVER['SERVER_NAME'], http_build_query(array('r' => $r)));
		$u = trim($u, '?');

		$cfg['redirectUri'] = $u;
		$cfg['verify'] = true;

		$p = new \League\OAuth2\Client\Provider\GenericProvider($cfg);

		return $p;
	}
}
