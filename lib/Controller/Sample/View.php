<?php
/**
	Only works if you OWN the Sample
*/

namespace App\Controller\Sample;

use Edoceo\Radix\DB\SQL;

class View extends \OpenTHC\Controller\Base
{
	function __invoke($REQ, $RES, $ARG)
	{
		$id = $ARG['id'];
		if (empty($id)) {
			_exit_text('Invalid Request', 400);
		}


		switch ($_POST['a']) {
		case 'drop':
			// need to return the $RES object from these methods to do anything
			$this->_dropSample($RES, $ARG, $cre);
			break;
		case 'void':
			$this->_voidSample($RES, $ARG, $cre);
			break;
		}

		$Lab_Sample = new \App\Lab_Sample($id);
		if (empty($Lab_Sample)) {
			_exit_text('Invalid Lab Sample [CSV#032]', 400);
		}

		$LSm = json_decode($Lab_Sample['meta'], true);

		$cre = new \OpenTHC\RCE($_SESSION['pipe-token']);
		$res = $cre->get('/lot/' . $id);

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
		$res = $cre->get('/config/license/' . $LSm['Lot']['global_mme_id']);
		$L_Own = $res['result'];

		$data = array(
			'Page' => array('title' => 'Sample :: View'),
			'Sample' => $LSm['Lot'],
			'Product' => $LSm['Product'],
			'Strain' => $LSm['Strain'],
			'Result' => $LSm['Result'],
			'License_Owner' => $L_Own,
			'License_Lab' => $L_Lab,
		);

		//_exit_text($data);

		return $this->_container->view->render($RES, 'page/sample/view.html', $data);

	}

	function _dropSample($RES, $ARG, $cre)
	{
		$cre = new \OpenTHC\RCE($_SESSION['pipe-token']);
		$res = $cre->get('/lot?source=true');
		var_dump($res);

		$res = $cre->delete('/lot/' . $ARG['id']);
		var_dump($res);

		$sql = 'UPDATE lab_sample SET flag = flag | ? WHERE company_id = ? AND id = ?';
		$arg = array(\App\Lab_Sample::FLAG_DEAD, $_SESSION['gid'], $ARG['id']);
		SQL::query($sql, $arg);

		return $RES->withRedirect('/sample');
	}
}
