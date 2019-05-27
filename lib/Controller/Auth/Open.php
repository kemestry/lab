<?php
/**
 * Open the Session
 */

namespace App\Controller\Auth;

use Edoceo\Radix;
use Edoceo\Radix\Session;

class Open extends \OpenTHC\Controller\Base
{
	function __invoke($REQ, $RES, $ARG)
	{
		$data = array(
			'Page' => array('title' => 'Connect to OpenTHC'),
			'pipe_token' => $_GET['pipe-token'],
			'oauth_client' => \OpenTHC\Config::get('oauth.client') ?: $_SERVER['SERVER_NAME'],
		);

		// Posted a Token?
		switch ($_POST['a']) {
		case 'open-pipe':

			unset($_SESSION['pipe-token']);

			// Validate Input
			$tok = trim($_POST['pipe-token']);
			if (empty($tok)) {
				_exit_text('Invalid Token [CAO#026]');
			}
			if (!preg_match('/^\w{26,128}$/', $tok)) {
				_exit_text('Invalid Token Format [CAO#029]');
			}

			// Test Connection
			$cre = new \OpenTHC\RCE($tok);
			$res = $cre->ping();

			// Save and redirect
			if ('success' == $res['status']) {
				$_SESSION['pipe-token'] = $tok;
				return $RES->withRedirect('/home');
			}

			return $RES->withJSON($res, 500);

			break;

		}

		return $this->_container->view->render($RES, 'page/auth/open.html', $data);
	}

}
