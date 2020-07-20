<?php
/**
 * Inbound Connection from Registered Application
 */

namespace App\Controller\Auth;

use Edoceo\Radix;
use Edoceo\Radix\Session;
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
			// _exit_text(sprintf('Invalid Session State "%d" [CAC#028]', $x), 500);
			return $RES;
		}

		// Sending Application may give us information this way?
		// And we provide the bearer token from our p2p configuration
		if (empty($this->_connect_info)) {
			return $RES->withJSON([
				'data' => null,
				'meta' => [ 'detail' => 'Invalid Connection Information [CAC#038]' ]
			], 400);
		}

		if (empty($this->_connect_info['pingback'])) {
			return $RES->withJSON([
				'data' => null,
				'meta' => [ 'detail' => 'Invalid Connection Information [CAC#044]' ]
			], 400);
		}

		$res = [];
		$cfg = \OpenTHC\Config::get('openthc_p2p');
		$res = HTTP::get($this->_connect_info['pingback'], array(
			sprintf('Authorization: Bearer %s', $cfg['public_key']),
		));
		if (200 != $res['info']['http_code']) {
			_exit_text('Invalid Response from connection pingback', 500);
		}
		$res = json_decode($res['body'], true);
		unset($res['result']);
		unset($res['status']);
		var_dump($res);

		$_SESSION['dsn'] = $res['data']['dsn'];

		// if (empty($_SESSION['cre-auth'])) {
		// 	_exit_text('Invalid Session State [CAC#025]', 400);
		// }

		// Action Action?
		$out_link = '/home';

		// Sync?
		$do_sync = false;
		// $C = new \OpenTHC\Company($dbc, $_SESSION['Company']);
		// $chk = $C->getOption('sync-time-lab');
		// $chk = intval($chk);
		// $age = $_SERVER['REQUEST_TIME'] - $chk;
		// if ($age >= 3600) {
		// 	$do_sync = true;
		// }

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

		//_exit_html("<a href='$out_link'>$out_link</a>");
		return $RES->withRedirect($out_link);

	}

}
