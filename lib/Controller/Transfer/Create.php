<?php
/**
 * Create a Result
 */

namespace App\Controller\Transfer;

use App\B2B_Incoming;
use App\B2B_Incoming_Item;


class Create extends \App\Controller\Base
{
	function __invoke($REQ, $RES, $ARG)
	{
		$data = $this->loadSiteData();

		return $this->_container->view->render($RES, 'page/transfer/create.html', $data);
	}

	function post($REQ, $RES, $ARG)
	{
		// var_dump($_SESSION);

		// var_dump($_POST);

		// exit;

		$dir = new \OpenTHC\Service\OpenTHC('dir');
		$L1 = $dir->get('/api/license/' . $_POST['license']);
		// var_dump($L1);
		// exit;

		$dbc = $this->_container->DB;
		$dbc->query('BEGIN');

		$b2b = new B2B_Incoming($dbc);
		$b2b['id'] = _ulid();
		$b2b['stat'] = 200;
		$b2b['license_id'] = $_SESSION['License']['id'];
		$b2b['license_id_origin'] = $L1['id'];
		$b2b['name'] = sprintf('Incoming Material from %s %s', $L1['code'], $L1['name']);
		$b2b['hash'] = md5(json_encode($b2b));
		$b2b->save();

		$max = count($_POST['lot-type']);

		for ($idx = 0; $idx < $max; $idx++) {

			$ls = new \App\Lab_Sample($dbc);
			$ls['license_id'] = $_SESSION['License']['id'];
			$ls['id'] = $_POST['lot-guid'][$idx];
			if (empty($ls['id'])) {
				$ls['id'] = _ulid();
			}
			$ls['product_type'] = $_POST['lot-type'][$idx];
			$ls['stat'] = \App\Lab_Sample::STAT_OPEN;
			$ls['qty'] = $_POST['lot-qty'][$idx];
			$ls['meta'] = json_encode([
				'Strain' => [
					'name' => $_POST['lot-strain'][$idx],
				]
			]);
			$ls->save();

			$b2b_item = new B2B_Incoming_Item($dbc);
			$b2b_item['id'] = $ls['id'];
			$b2b_item['package_qty'] = $_POST['lot-qty'][$idx];
			$b2b_item['strain'] = $_POST['lot-strain'][$idx];
	
			$b2b->addItem($b2b_item);

		}
		
		$b2b->save();

		$dbc->query('COMMIT');

		var_dump($b2b);

		return $RES->withRedirect('/transfer/' . $b2b['id']);
	}

}
