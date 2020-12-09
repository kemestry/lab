<?php
/**
 * Accept an Incoming Transfer
 */

namespace App\Controller\Transfer;

use Edoceo\Radix\Session;
//use Edoceo\Radix\DB\SQL;

class Accept extends \OpenTHC\Controller\Base
{
	function __invoke($REQ, $RES, $ARG)
	{
		$data = array(
			'Page' => array('title' => 'Transfer :: Accept'),
		);
		// if ('POST' == $_SERVER['REQUEST_METHOD']) {
		// 	return $this->accept($RES, $ARG);
		// }

		$arg = array($_SESSION['License']['id'], $ARG['id']);
		$T0 = $this->_container->DBC_User->fetchRow('SELECT * FROM b2b_incoming WHERE license_id = ? AND id = ?', $arg);


		// Fresh data from CRE
		$cre = new \OpenTHC\CRE($_SESSION['pipe-token']);
		$res = $cre->get('/transfer/incoming/' . $ARG['id']);
		if ('success' != $res['status']) {
			Session::flash('fail', 'Cannot Load Transfer [CTA#027]');
			Session::flash('fail', 'You may need to <a href="/auth/open">sign in again</a>');
			return $this->_container->view->render($RES, 'page/fail.html', $data);
		}
		//var_dump($res);
		$T1 = $res['result'];

		$res = $cre->get('/config/zone');
		if ('success' != $res['status']) {
			_exit_text('Cannot load Zone list from CRE [CTA#033]', 501);
		}
		$zone_list = $res['result'];

		$Target = new \OpenTHC\License($T0['license_id']);
		$Origin = new \OpenTHC\License($T0['license_id_origin']);

		$data = array(
			'Page' => array('title' => 'Transfer :: Accept'),
			'Transfer' => $T1,
			'License_Source' => $Origin->toArray(),
			'License_Target' => $Target->toArray(),
			'Zone_list' => $zone_list,
		);

		return $this->_container->view->render($RES, 'page/transfer/accept.html', $data);

	}

	/**
	 * Actually Accept the Inventory
	 */
	function accept($REQ, $RES, $ARG)
	{
		$args = array(
			'global_id' => $ARG['id'],
			'inventory_transfer_items' => array(),
		);

		foreach ($_POST as $k => $v) {

			// Find Post keys matching pattern "lot-receive-guid-<Transfer_Incoming_Item ID>"
			if (preg_match('/lot-receive-guid-(\w+)/', $k, $m)) {

				$id = $m[1];
				$rx = floatval($_POST[sprintf('lot-receive-count-%s', $id)]);

				$iti = array(
					'global_id' => $v,
					'received_qty' => $rx,
					'global_received_area_id' => $_POST['zone-id'],
				);

				$args['inventory_transfer_items'][] = $iti;
			}
		}


		$cre = new \OpenTHC\CRE($_SESSION['pipe-token']);
		$url = sprintf('/transfer/incoming/%s/accept', $ARG['id']);
		$res = $cre->post($url, array('json' => $args));

		if ('success' != $res['status']) {
			Session::flash('fail', $cre->formatError($res));
			return $RES->withRedirect('/transfer/' . $ARG['id']);
			_exit_text($res);
		}

		$dbc = $this->_container->DBC_User;

		// Add Lots to my Samples
		$lot_list = $res['result']['inventory_transfer_items'];
		foreach ($lot_list as $lot) {

			$dbc->insert('lab_sample', array(
				'id' => $lot['global_received_inventory_id'],
				'license_id' => $_SESSION['License']['id'],
				'name' => $lot['description'],
				'meta' => \json_encode($lot),
			));

		}

		Session::flash('info', 'Transfer Accepted, Inventory changes in LeafData generally take up to 10 minutes to synchronize.');

		return $RES->withRedirect('/transfer/' . $ARG['id']);

	}
}
