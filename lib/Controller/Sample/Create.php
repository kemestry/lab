<?php
/**
 * Create a Sample
 */

namespace App\Controller\Sample;

use Edoceo\Radix\Session;

class Create extends \App\Controller\Base
{
	function __invoke($REQ, $RES, $ARG)
	{
		$data = $this->loadSiteData();

		switch ($_POST['a']) {
			case 'create-sample':

				$_POST['product'] = trim($_POST['product']);
				$_POST['strain'] = trim($_POST['strain']);

				$dbc = $this->_container->DB;

				$ls = new \App\Lab_Sample($dbc);
				$ls['id'] = _ulid();
				$ls['license_id'] = $_SESSION['License']['id'];
				$ls['license_id_source'] = $_POST['license-id'];

				// $P1 = new Product()
				$P1 = $dbc->fetchRow('SELECT * FROM product WHERE license_id = :l0 AND name = :n0', [
					':l0' => $_SESSION['License']['id'],
					':n0' => $_POST['product'],
				]);
				if (empty($P1['id'])) {
					$P1 = [
						'id' => _ulid(),
						'license_id' => $_SESSION['License']['id'],
						'product_type_id' => $_POST['product-type'],
						'name' => $_POST['product'],
						'stub' => _text_stub($_POST['product'])
					];
					$P1['guid'] = $P1['id'];
					$dbc->insert('product', $P1);
				}
				$ls['product_id'] = $P1['id'];

				$S1 = $dbc->fetchRow('SELECT * FROM strain WHERE license_id = :l0 AND name = :n0', [
					':l0' => $_SESSION['License']['id'],
					':n0' => $_POST['strain'],
				]);
				if (empty($S1['id'])) {
					$S1 = [
						'id' => _ulid(),
						'license_id' => $_SESSION['License']['id'],
						'name' => $_POST['strain'],
					];
					$S1['guid'] = $S1['id'];
					$dbc->insert('strain', $S1);
				}
				$ls['strain_id'] = $S1['id'];
				$ls['qty'] = floatval($_POST['qty']);
				$ls['meta'] = json_encode([
					'Lot_Source' => [
						'id' => $_POST['lot-id-source']
					],
				]);
				$ls['hash'] = $ls->getHash();
				$ls->save();

				return $RES->withRedirect('/sample/' . $ls['id']);

			break;
		}

		$data['product_type'] = $this->_container->DB->fetchMix('SELECT id, name FROM product_type WHERE stat = 200 ORDER BY name');

		return $this->_container->view->render($RES, 'page/sample/create.html', $data);
	}
}
