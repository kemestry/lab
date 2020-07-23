<?php
/**
 * Only works if you OWN the Sample
 */

namespace App\Controller\Sample;

// use Edoceo\Radix\DB\SQL;

use App\Lab_Sample;

class View extends \App\Controller\Base
{
	private $cre;

	function __invoke($REQ, $RES, $ARG)
	{
		if (empty($ARG['id'])) {
			_exit_text('Invalid Request', 400);
		}

		switch ($_POST['a']) {
			case 'drop':
				// need to return the $RES object from these methods to do anything
				return $this->_dropSample($RES, $ARG);
			break;
			case 'void':
				$this->_voidSample($RES, $ARG);
			break;
			case 'done':
				return $this->_finishSample($RES, $ARG);
			break;
		}

		$dbc = $this->_container->DB;

		// $this->cre = new \OpenTHC\CRE($_SESSION['pipe-token']);

		$Lab_Sample = new \App\Lab_Sample($dbc, $ARG['id']);
		if (empty($Lab_Sample['id'])) {
			_exit_text('Invalid Lab Sample [CSV#032]', 400);
		}
		// var_dump($Lab_Sample);

		$Product = $dbc->fetchRow('SELECT * FROM product WHERE id = ?', [ $Lab_Sample['product_id'] ]);
		$ProductType = $dbc->fetchRow('SELECT * FROM product_type WHERE id = ?', [ $Product['product_type_id'] ]);
		$Strain = $dbc->fetchRow('SELECT * FROM strain WHERE id = ?', [ $Lab_Sample['strain_id'] ]);

		$LSm = json_decode($Lab_Sample['meta'], true);

		// Get Fresh Lot Data?
		// $res = $this->cre->get('/lot/' . $Lab_Sample['id']);

		//$res = $this->cre->get('/config/product/' . $QAS['global_inventory_type_id']);
		//$P = $res['result'];
		//var_dump($P);

		// Find Laboratory License
		//$res = $this->cre->get('/config/license/' . $QAS['global_created_by_mme_id']);
		//$L_Lab = $res['result'];

		// Find Owner License
		// $res = $this->cre->get('/config/license/' . $LSm['Lot']['global_created_by_mme_id']);
		// $L_Own = new \OpenTHC\License($dbc);
		// $L_Own->loadBy('id', $LSm['Lot']['global_created_by_mme_id']);

		$data = $data = $this->loadSiteData([
			'Page' => array('title' => 'Sample :: View'),
			// 'Lab_Sample' => $Lab_Sample->toArray(), // @deprecated
			// 'Sample' => $LSm['Lot'], // @deprecated
			'Lot' => $Lab_Sample->toArray(),
			'Product' => $Product,
			'ProductType' => $ProductType,
			'Strain' => $Strain,
			// 'Result' => $LSm['Result'],
			// 'License_Source' => $L_Own->toArray(),
		]);
		$data['Sample']['id'] = $ARG['id'];
		if (empty($data['Product']['uom'])) {
			$data['Product']['uom'] = 'g';
		}

		// cause LeafData makes this one need MEDIACAL stuff, we fake-it in
		if ('flower' == $data['Product']['intermediate_type']) {
			$data['Sample']['medically_compliant'] = true;
		}

		return $this->_container->view->render($RES, 'page/sample/view.html', $data);

	}

	function _finishSample($RES, $ARG)
	{
		$dbc = $this->_container->DB;

		// $sql = 'SELECT * from lab_sample where id = :pk';
		// $res = $dbc->fetchAll($sql, [
		// 	':pk' => $ARG['id'],
		// ]);

		$sql = 'UPDATE lab_sample SET stat = :s1, flag = flag | :f1 WHERE license_id = :l0 AND id = :pk';
		$arg = array(
			':pk' => $ARG['id'],
			':l0' => $_SESSION['License']['id'],
			':s1' => \App\Lab_Sample::STAT_DONE,
			':f1' => \App\Lab_Sample::FLAG_DONE,
		);
		$res = $dbc->query($sql, $arg);

		return $RES->withRedirect('/sample');
	}

	function _dropSample($RES, $ARG)
	{
		\session_write_close();

		// $res = $this->cre->get('/lot?source=true');
		// $res = $this->cre->delete('/lot/' . $ARG['id']);

		$sql = 'SELECT * from lab_sample where id = :pk';
		$res = $dbc->fetchAll($sql, [
			':pk' => $ARG['id'],
		]);

		$sql = 'UPDATE lab_sample SET stat = :s1, flag = flag | :FLAG_DEAD WHERE license_id = :l0 AND id = :pk';
		$arg = array(
			':pk' => $ARG['id'],
			':l0' => $_SESSION['License']['id'],
			':s1' => \App\Lab_Sample::STAT_VOID,
			':f1' => \App\Lab_Sample::FLAG_DEAD,
		);
		$res = $dbc->query($sql, $arg);

		return $RES->withRedirect('/sample');
	}

	function _voidSample($RES, $ARG)
	{
		$dbc = $this->_container->DB;

		$sql = 'UPDATE lab_sample SET stat = :s1, flag = flag | :f1 WHERE license_id = :l0 AND id = :pk';
		$arg = array(
			':pk' => $ARG['id'],
			':l0' => $_SESSION['License']['id'],
			':s1' => \App\Lab_Sample::STAT_VOID,
			':f1' => \App\Lab_Sample::FLAG_VOID,
		);
		$res = $dbc->query($sql, $arg);

		return $RES->withRedirect('/sample');
	}

}
