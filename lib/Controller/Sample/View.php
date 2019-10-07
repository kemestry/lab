<?php
/**
 * Only works if you OWN the Sample
 */

namespace App\Controller\Sample;

use Edoceo\Radix\DB\SQL;

class View extends \OpenTHC\Controller\Base
{
	function __invoke($REQ, $RES, $ARG)
	{
		if (empty($ARG['id'])) {
			_exit_text('Invalid Request', 400);
		}

		$Lab_Sample = new \App\Lab_Sample($ARG['id']);
		if (empty($Lab_Sample)) {
			_exit_text('Invalid Lab Sample [CSV#032]', 400);
		}


		switch ($_POST['a']) {
		case 'drop':
			// need to return the $RES object from these methods to do anything
			return $this->_dropSample($RES, $ARG, $cre);
		case 'void':
			$this->_voidSample($RES, $ARG, $cre);
			break;
		case 'done':
			return $this->_finishSample($RES, $ARG, $cre);
		}

		$LSm = json_decode($Lab_Sample['meta'], true);

		// $cre = new \OpenTHC\CRE($_SESSION['pipe-token']);
		// $res = $cre->get('/lot/' . $Lab_Sample['id']);

		//$res = $cre->get('/config/product/' . $QAS['global_inventory_type_id']);
		//$P = $res['result'];
		//var_dump($P);

		//$res = $cre->get('/config/strain/' . $QAS['global_strain_id']);
		//$St = $res['result'];
		//var_dump($St);
		// $St = array('name' => $QAS['global_strain_id']);

		// Find Laboratory License
		//$res = $cre->get('/config/license/' . $QAS['global_created_by_mme_id']);
		//$L_Lab = $res['result'];

		// Find Owner License
		// $res = $cre->get('/config/license/' . $LSm['Lot']['global_created_by_mme_id']);
		$L_Own = new \OpenTHC\License($LSm['Lot']['global_created_by_mme_id']);

		$data = array(
			'Page' => array('title' => 'Sample :: View'),
			'Sample' => $LSm['Lot'],
			'Product' => $LSm['Product'],
			'Strain' => $LSm['Strain'],
			'Result' => $LSm['Result'],
			'License_Owner' => $L_Own->toArray(),
		//	'License_Lab' => //$L_Lab,
		);
		$data['Sample']['id'] = $ARG['id'];

		// _exit_text($data);

		return $this->_container->view->render($RES, 'page/sample/view.html', $data);

	}

	function _finishSample($RES, $ARG, $cre)
	{
		$cre = new \OpenTHC\CRE($_SESSION['pipe-token']);

		$sql = 'SELECT * from lab_sample where id = :pk';
		$res = SQL::fetch_all($sql, [
			':pk' => $ARG['id'],
		]);

		$sql = 'UPDATE lab_sample SET flag = flag | :FLAG_DEAD WHERE license_id = :l0 AND id = :pk';
		$arg = array(
			// ':FLAG_DEAD' => \App\Lab_Sample::FLAG_COMPLETE,
			// ':l0' => $_SESSION['License']['id'],
			// ':pk' => $ARG['id']
		);
		// $res = SQL::query($sql, $arg);

		return $RES->withRedirect('/sample');
	}

	function _dropSample($RES, $ARG, $cre)
	{
		\session_write_close();

		$cre = new \OpenTHC\CRE($_SESSION['pipe-token']);
		// $res = $cre->get('/lot?source=true');

		$res = $cre->delete('/lot/' . $ARG['id']);

		$sql = 'SELECT * from lab_sample where id = :pk';
		$res = SQL::fetch_all($sql, [
			':pk' => $ARG['id'],
		]);

		$sql = 'UPDATE lab_sample SET stat = 401, flag = flag | :FLAG_DEAD WHERE license_id = :l0 AND id = :pk';
		$arg = array(
			':FLAG_DEAD' => \App\Lab_Sample::FLAG_DEAD,
			':l0' => $_SESSION['License']['id'],
			':pk' => $ARG['id']
		);
		$res = SQL::query($sql, $arg);

		return $RES->withRedirect('/sample');
	}
}
