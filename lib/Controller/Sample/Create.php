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
		$this->data = $this->loadSiteData();
		$this->data['product_type'] = $this->_container->DB->fetchMix('SELECT id, name FROM product_type');
		$this->data['html'] = $this->render('sample/create.php');

		var_dump($_SESSION);
		var_dump($_POST);

		switch ($_POST['a']) {
			case 'save':

				$_POST['product'] = trim($_POST['product']);
				$_POST['strain'] = trim($_POST['strain']);

				$dbc = $this->_container->DB;

				$ls = new \App\Lab_Sample($dbc);
				$ls['id'] = _ulid(); // $_POST[''];
				if (empty($ls['id'])) {
					$ls['id'] = _ulid();
				}
				$ls['license_id'] = $_SESSION['License']['id'];
				$ls['license_id_source'] = $_POST['license_id'];

				// $P1 = new Product()
				$P1 = $dbc->fetchRow('SELECT * FROM product WHERE license_id = :l0 AND name = :n0', [
					':l0' => $_SESSION['License']['id'],
					':n0' => $_POST['product'],
				]);
				if (empty($P1['id'])) {
					$P1['id'] = $dbc->insert('product', [
						'license_id' => $_SESSION['License']['id'],
						'product_type_id' => $_POST['product-type'],
						'guid' => _ulid(),
						'name' => $_POST['product'],
						'stub' => _text_stub($_POST['product'])
					]);
				}
				$ls['product_id'] = $P1['guid'];

				$S1 = $dbc->fetchRow('SELECT * FROM strain WHERE license_id = :l0 AND name = :n0', [
					':l0' => $_SESSION['License']['id'],
					':n0' => $_POST['strain'],
				]);
				if (empty($S1['id'])) {
					$S1['id'] = $dbc->insert('strain', [
						'license_id' => $_SESSION['License']['id'],
						'guid' => _ulid(),
						'name' => $_POST['strain'],
					]);
				}
				$ls['strain_id'] = $S1['guid'];
				$ls['qty'] = floatval($_POST['qty']);
				$ls['meta'] = json_encode([
					'Lot_Source' => [
						'id' => $_POST['lot_id_origin']
					],
					'Product' => [
						'name' => $_POST['product'],
					],
					'Strain' => $_POST['strain'],
				]);
				$ls['hash'] = $ls->getHash();
				$ls->save();

				return $RES->withRedirect('/sample/' . $ls['id']);

			break;
		}



		return $this->_container->view->render($RES, 'page/html.html', $this->data);
	}
}
