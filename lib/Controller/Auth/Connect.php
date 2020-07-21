<?php
/**
 * Inbound Connection from Registered Application
 */

namespace App\Controller\Auth;

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
			// _exit_text(sprintf('Invalid Session State "%d" [CAC#028]', $x), 500);
			return $RES;
		}

		$_SESSION['auth-action'] = [];

		// Sync?
		$do_sync = false;
		// $C = new \OpenTHC\Company($dbc, $_SESSION['Company']);
		// $chk = $C->getOption('sync-time-lab');
		// $chk = intval($chk);
		// $age = $_SERVER['REQUEST_TIME'] - $chk;
		// if ($age >= 3600) {
		// 	$do_sync = true;
		// }
		if ($do_sync) {
			$_SESSION['auth-action'][] = '/sync';
		}

		// Action
		switch ($_GET['action']) {
		case 'share-transfer':

			throw new \Exception('Invalid Request');
			// @todo should this be _connect_info ?
			$out_link = '/transfer/import/' . $tmp_auth['transfer']['guid'];

			break;

		case 'share':
		case 'share-all': // @deprecated
		case 'share-one': // @deprecated

			$_SESSION['auth-action'][] = '/intent';
			$_SESSION['intent'] = 'share-all';

			if (!empty($this->_connect_info['lab-result'])) {
				$x = $this->_connect_info['lab-result']['guid'];
				if (!empty($x)) {
					$_SESSION['intent'] = 'share-one';
					$_SESSION['intent-data'] = $x;
				}

			}
			break;

		}

		return $RES->withRedirect('/auth/init');

	}

}
