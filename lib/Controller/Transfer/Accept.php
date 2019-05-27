<?php
/**
 * Accept an Incoming Transfer
 */

namespace App\Controller\Transfer;

use Edoceo\Radix\DB\SQL;

class Accept extends \OpenTHC\Controller\Base
{
	function __invoke($REQ, $RES, $ARG)
	{
		if ('POST' == $_SERVER['REQUEST_METHOD']) {
			return $this->accept($RES, $ARG);
		}

		$arg = array($_SESSION['License']['ulid'], $ARG['id']);
		$T0 = SQL::fetch_row('SELECT * FROM transfer_incoming WHERE license_id_target = ? AND id = ?', $arg);

		$cre = new \OpenTHC\RCE($_SESSION['pipe-token']);

		// Fresh data from CRE
		$res = $cre->get('/transfer/incoming/' . $ARG['id']);
		if ('success' != $res['status']) {
			print_r($res);
			die("Cannot Load Transfer");
		}
		//var_dump($res);
		$T1 = $res['result'];

		$res = $cre->get('/config/zone');
		if ('success' != $res['status']) {
			_exit_text('Cannot load Zone list from CRE [CTA#033]', 501);
		}
		$zone_list = $res['result'];

		$Origin = SQL::fetch_row('SELECT * FROM license WHERE ulid = ?', array($T0['license_id_origin']));
		$Target = SQL::fetch_row('SELECT * FROM license WHERE ulid = ?', array($T0['license_id_target']));

		$data = array(
			'Page' => array('title' => 'Transfer :: Accept'),
			'Transfer' => $T1,
			'Origin_License' => $Origin,
			'Target_License' => $Target,
			'Zone_list' => $zone_list,
		);

		//_exit_text($data);

		return $this->_container->view->render($RES, 'page/transfer/accept.html', $data);

	}

	/**
		Actually Accept the Inventory
	*/
	private function accept($RES, $ARG)
	{
		$cre = new \OpenTHC\RCE($_SESSION['pipe-token']);

		$args = array(
			'global_id' => $ARG['id'],
			'inventory_transfer_items' => array(),
		);

		foreach ($_POST as $k => $v) {

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

		$path = sprintf('/transfer/incoming/%s/accept', $ARG['id']);
		$res = $cre->post($path, array('json' => $args));

		if ('success' != $res['status']) {
			_exit_text($res);
		}


		// Add Lots to my Samples
		$lot_list = $res['result']['inventory_transfer_items'];
		foreach ($lot_list as $lot) {

			SQL::insert('qa_sample', array(
				'id' => $lot['global_received_inventory_id'],
				'company_id' => $_SESSION['company']['id'],
				'company_ulid' => $_SESSION['company']['ulid'],
				'license_ulid' => $_SESSION['license']['ulid'],
				'name' => $lot['description'],
				'meta' => \json_encode($lot),
			));

		}

		return $RES->withRedirect('/transfer/' . $ARG['id']);

	}
}
