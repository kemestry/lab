<?php
/**
 * Only works if you OWN the Sample
 */

namespace App\Controller\Sample;

use Edoceo\Radix\DB\SQL;

class View extends \App\Controller\Base
{
	private $cre;

	function __invoke($REQ, $RES, $ARG)
	{
		if (empty($ARG['id'])) {
			_exit_text('Invalid Request', 400);
		}

		$dbc = $this->_container->DB;

		$this->cre = new \OpenTHC\CRE($_SESSION['pipe-token']);

		$Lab_Sample = new \App\Lab_Sample($dbc, $ARG['id']);
		if (empty($Lab_Sample['id'])) {
			_exit_text('Invalid Lab Sample [CSV#032]', 400);
		}

		switch ($_POST['a']) {
		case 'drop':
			// need to return the $RES object from these methods to do anything
			return $this->_dropSample($RES, $ARG);
		case 'void':
			$this->_voidSample($RES, $ARG);
			break;
		case 'done':
			return $this->_finishSample($RES, $ARG);
		}

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
		$L_Own = new \OpenTHC\License($dbc);
		$L_Own->loadBy('guid', $LSm['Lot']['global_created_by_mme_id']);

		$data = $data = $this->loadSiteData([
			'Page' => array('title' => 'Sample :: View'),
			'Lab_Sample' => $Lab_Sample->toArray(),
			'Sample' => $LSm['Lot'],
			'Product' => $LSm['Product'],
			'Strain' => $LSm['Strain'],
			'Result' => $LSm['Result'],
			'License_Owner' => $L_Own->toArray(),
		]);
		$data['Sample']['id'] = $ARG['id'];

		// cause LeafData makes this one need MEDIACAL stuff, we fake-it in
		if ('flower' == $data['Product']['intermediate_type']) {
			$data['Sample']['medically_compliant'] = true;
		}

		return $this->_container->view->render($RES, 'page/sample/view.html', $data);

	}

	function _finishSample($RES, $ARG)
	{
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

	function _dropSample($RES, $ARG)
	{
		\session_write_close();

		// $res = $this->cre->get('/lot?source=true');
		$res = $this->cre->delete('/lot/' . $ARG['id']);

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
