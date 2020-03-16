<?php
/**
 * Inbound Connection from Registered Application
 */

namespace App\Controller\Auth;

use Edoceo\Radix;
use Edoceo\Radix\Session;
use Edoceo\Radix\DB\SQL;
use Edoceo\Radix\Net\HTTP;

use OpenTHC\Contact;

class Connect extends \OpenTHC\Controller\Auth\Connect
{
	function __invoke($REQ, $RES, $ARG)
	{
		$RES = parent::__invoke($REQ, $RES, $ARG);

		$x = $RES->getStatusCode();
		switch ($x) {
		case 200:
		case 301:
		case 302:
			// OK
			break;
		default:
			_exit_text(sprintf('Invalid Session State "%d" [CAC#028]', $x), 500);
			return $RES;
		}

		if (empty($_SESSION['cre-auth'])) {
			_exit_text('Invalid Session State [CAC#025]', 400);
		}

		// No Authentication already?
		// Get one from the passed in credentials
		if (empty($_SESSION['pipe-token'])) {

			$auth_req = array(
				'cre' => $_SESSION['cre']['engine'],
				'license' => $_SESSION['cre-auth']['license'],
				'license-key' => $_SESSION['cre-auth']['license-key'],
			);
			$cre = new \OpenTHC\CRE();
			$res = $cre->auth($auth_req);
			if ('success' != $res['status']) {
				_exit_text($cre->formatError($res), 500);
			}

			$_SESSION['pipe-token'] = $res['result'];
		}

		// Action Action?
		$out_link = '/home';

		// Sync?
		$do_sync = false;
		$C = new \OpenTHC\Company($_SESSION['Company']);
		$chk = $C->getOption('sync-time-lab');
		$chk = intval($chk);
		$age = $_SERVER['REQUEST_TIME'] - $chk;
		if ($age >= 3600) {
			$do_sync = true;
		}

		// Action
		switch ($_GET['action']) {
		case 'share-transfer':

			$out_link = '/transfer/import/' . $tmp_auth['transfer']['guid'];

			break;

		case 'share':
		case 'share-all': // @deprecated
		case 'share-one': // @deprecated

			$out_link = '/intent';

			$_SESSION['intent'] = 'share-all';

			if (!empty($this->_connect_info['lab-result'])) {
				$x = $this->_connect_info['lab-result']['guid'];
				if (!empty($x)) {
					$_SESSION['intent'] = 'share-one';
					$_SESSION['intent-data'] = $x;
				}

			}

			if ($do_sync) {
				$out_link = '/sync?r=/intent';
			}

			break;

		default:
			// throw new \Exception(sprintf('Invalid Action "%s" [CAC#052]', $_GET['action']));
		}

		switch ($_SESSION['License']['type']) {
		case 'Laboratory':
			// var_dump($_SESSION);
			// die("\nYOU ARE A LAB!");
			// Lab!!
			break;
		default:
			// Viewer Only
			break;
		}

		// Sending Application may give us information this way?
		// And we provide the bearer token from our p2p configuration
		if (!empty($this->_connect_info)) {
			$res = [];
			if (!empty($this->_connect_info['pingback'])) {
				$cfg = \OpenTHC\Config::get('openthc_p2p');
				$res = HTTP::get($this->_connect_info['pingback'], array(
					sprintf('Authorization: Bearer %s', $cfg['public_key']),
				));
				if (200 != $res['info']['http_code']) {
					_exit_text('Invalid Response from connection pingback', 500);
				}
			}
		}

		//_exit_html("<a href='$out_link'>$out_link</a>");
		return $RES->withRedirect($out_link);

	}

}
