<?php
/**
 * oAuth2 Lands Back Here
 */

namespace App\Controller\Auth\oAuth2;

use Edoceo\Radix;
use Edoceo\Radix\Session;

class Back extends \App\Controller\Auth\oAuth2
{
	function __invoke($REQ, $RES, $ARG)
	{
		$p = $this->getProvider();

		if (empty($_GET['code'])) {
			_exit_text('Invalid Request [CAB#019]', 400);
		}

		// Check State
		$this->checkState();

		// Try to get an access token using the authorization code grant.
		try {
			$tok = $p->getAccessToken('authorization_code', [
				'code' => $_GET['code']
			]);
		} catch (\Exception $e) {
			//_exit_text($e, 500);
			Session::flash('fail', 'CAB#037: Invalid Access Token');
			Radix::redirect('/auth/fail');
		}

		if (empty($tok)) {
			Session::flash('fail', 'CAB#042: Invalid Access Token');
			Radix::redirect('/auth/fail');
		}

		// Array-ify
		$tok_a = json_decode(json_encode($tok), true);

		if (empty($tok_a['access_token'])) {
			Session::flash('fail', 'Invalid Access Token');
			Radix::redirect('/auth/fail');
		}

		if (empty($tok_a['token_type'])) {
			Session::flash('fail', 'Invalid Access Token');
			Radix::redirect('/auth/fail');
		}

		// Using the access token, we may look up details about the
		// resource owner.
		try {

			$x = $p->getResourceOwner($tok);
			$x = $x->toArray();

			$x['scope'] = explode(' ', $x['scope']);

			$_SESSION['Contact'] = $x['Contact'];
			$_SESSION['Company'] = $x['Company'];

			Session::flash('info', sprintf('Signed in as: %s', $_SESSION['Contact']['username']));

			$_SESSION['uid'] = $x['Contact']['id'];
			$_SESSION['gid'] = $x['Company']['id'];
			$_SESSION['email'] = $x['Contact']['username'];

			if (!empty($x['Contact']['meta']['rce'])) {
				$_SESSION['rce'] = $x['Contact']['meta']['rce'];
				$_SESSION['rce-auth'] = $x['Contact']['meta']['rce-auth'];
			}

			if (!empty($_SESSION['rce'])) {

				// Authenticate via PIPE
				$rce = new \OpenTHC\RCE();
				$x = $rce->auth(array(
					'rce' => $_SESSION['rce'],
					'license' => $_SESSION['rce-auth']['license'],
					'client-key' => $_SESSION['rce-auth']['secret'],
				));

				if ('success' == $x['status']) {
					$_SESSION['pipe-token'] = $x['result'];
				} else {
					// var_dump($_SESSION);
					// var_dump($x);
					_exit_text('RCE Connection Failure. Please contact support [AOB#092]', 500);
				}

				$lic = '/config/license/' . $_SESSION['rce-auth']['license'];
				var_dump($lic);
				$res = $rce->get($lic);
				var_dump($res);

				if ('success' == $x['status']) {
					$L = \OpenTHC\License::findByGUID($res['result']['guid']);
					var_dump($L);
					$_SESSION['License'] = $L->toArray();
				} else {
					_exit_text('License Not Found. Please contact support [AOB#107]', 500);
				}

			}


			// Redirect
			$r = $_GET['r'];
			if (empty($r)) {
				$r = '/home';
			}

			Radix::redirect($r);

		} catch (\Exception $e) {
			Session::flash('fail', $e->getMessage());
			Radix::redirect('/auth/fail');
		}

	}

}
