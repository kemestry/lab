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

	/**
	 * POST Handler
	 */
	function post($REQ, $RES, $ARG)
	{
		$dbc = $this->_container->DB;

		$L0 = $_SESSION['License'];

		$dir = new \OpenTHC\Service\OpenTHC('dir');
		$LX = $dir->get('/api/license/' . $_POST['license']);
		$CX = $LX['company'];

		$C1 = new \OpenTHC\Company($dbc);
		if (!$C1->loadBy('id', $CX['id'])) {
			$C1['id'] = $CX['id'];
			$C1['name'] = $LX['name'];
			$C1['hash'] = $C1->getHash();
			$C1->save();
		}

		// var_dump($L1); exit;
		$chk = $dbc->fetchOne('SELECT id FROM license WHERE company_id = :c0 AND id = :l0', [
			':c0' => $C1['id'],
			':l0' => $L1['id'],
		]);
		$L1 = new \OpenTHC\License($dbc);
		if (!$L1->loadBy('id', $LX['id'])) {
			$L1['id'] = $LX['id'];
			$L1['company_id'] = $C1['id'];
			$L1['name'] = $LX['name'];
			$L1['hash'] = $L1->getHash();
			$L1->save();
		}

		$dbc->query('BEGIN');

		$b2b = new B2B_Incoming($dbc);
		$b2b['id'] = _ulid();
		$b2b['stat'] = 200;
		$b2b['license_id_target'] = $_SESSION['License']['id'];
		$b2b['license_id_source'] = $L1['id'];
		$b2b['name'] = sprintf('Incoming Material from %s %s', $L1['code'], $L1['name']);
		$b2b['hash'] = md5(json_encode($b2b));
		$b2b->save();

		$max = count($_POST['lot-type']);

		for ($idx = 0; $idx < $max; $idx++) {

			$ls = new \App\Lab_Sample($dbc);
			$ls['id'] = $_POST['lot-guid'][$idx];
			if (empty($ls['id'])) {
				$ls['id'] = _ulid();
			}
			$ls['license_id'] = $_SESSION['License']['id'];
			$ls['product_id'] = '00000000000000000000000000';
			$ls['strain_id'] = $_POST['lot-strain'][$idx];
			if (empty($ls['strain_id'])) {
				$ls['strain_id'] = '00000000000000000000000000';
			}
			$ls['stat'] = \App\Lab_Sample::STAT_OPEN;
			$ls['qty'] = floatval($_POST['lot-qty'][$idx]);
			if ($ls['qty'] <= 0) {
				continue;
			}

			$ls['meta'] = json_encode([
				'Strain' => [
					'name' => $_POST['lot-strain'][$idx],
				],
				'Product' => [],
				'Product_Type' => $_POST['lot-type'][$idx],
			]);
			$ls['hash'] = $ls->getHash();
			$ls->save();

			// Insert Lot Too?



			$b2b_item = new B2B_Incoming_Item($dbc);
			$b2b_item['id'] = $ls['id'];
			$b2b_item['lot_id'] = $ls['id'];
			$b2b_item['qty'] = $_POST['lot-qty'][$idx];
			$b2b_item['name'] = sprintf('Incoming Item %f of %s', $_POST['lot-strain'][$idx], $_POST['lot-qty'][$idx]);
			$b2b_item['meta'] = json_encode([
				'strain' => $_POST['lot-strain'][$idx]
			]);
			$b2b->addItem($b2b_item);

		}

		$b2b->save();

		$dbc->query('COMMIT');

		var_dump($b2b);

		return $RES->withRedirect('/transfer/' . $b2b['id']);
	}

}
