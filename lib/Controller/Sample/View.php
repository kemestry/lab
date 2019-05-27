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

		$cre = new \OpenTHC\RCE($_SESSION['pipe-token']);

		switch ($_POST['a']) {
		case 'drop':
			$this->_dropSample($RES, $ARG, $cre);
			break;
		case 'void':
			$this->_voidSample($RES, $ARG, $cre);
			break;
		}

		$res = $cre->get('/lot/' . $id);
		//var_dump($res);

		$QAS = $res['result'];

		// This might be BS
		if (empty($QAS['global_inventory_id']) || empty($QAS['global_for_inventory_id'])) {
			$QAS['_lost'] = true;
			//if (empty($S['global_inventory_id'])) {
			//	$S['global_inventory_id'] = '-lost-';
			//}
			//if (empty($S['global_for_inventory_id'])) {
			//	$S['global_for_inventory_id'] = '-lost-';
			//}
		}
		//var_dump($S);

		//$res = $cre->get('/config/product/' . $QAS['global_inventory_type_id']);
		//$P = $res['result'];
		//var_dump($P);

		//$res = $cre->get('/config/strain/' . $QAS['global_strain_id']);
		//$St = $res['result'];
		//var_dump($St);
		// $St = array('name' => $QAS['global_strain_id']);

		// Find Owner Company
		//$res = $cre->get('/config/license/' . $QAS['global_created_by_mme_id']);
		//$L = $res['result'];
		$res = $cre->get('/config/license/' . $QAS['global_mme_id']);
		$L = $res['result'];
		//var_dump($L);

		$data = array(
			'Page' => array('title' => 'Sample :: View'),
			'Sample' => $QAS,
			'Product' => $P,
			'Strain' => $St,
			'License_Owner' => $L,
			//'License_Labor' => $L0,
		);

		return $this->_container->view->render($RES, 'page/sample/view.html', $data);

	}

	function _dropSample($RES, $ARG, $cre)
	{
		$cre = new \OpenTHC\RCE($_SESSION['pipe-token']);
		$res = $cre->get('/lot?source=true');
		var_dump($res);

		$res = $cre->delete('/lot/' . $ARG['id']);
		var_dump($res);

		$sql = 'UPDATE qa_sample SET flag = flag | ? WHERE company_id = ? AND guid = ?';
		$arg = array(\App\QA_Sample::FLAG_DEAD, $_SESSION['gid'], $ARG['id']);
		SQL::query($sql, $arg);

		return $RES->withRedirect('/sample');
	}
}
