<?php
/**
 * View a Single Transfer
 */

namespace App\Controller\Transfer;

class View extends \OpenTHC\Controller\Base
{
	function __invoke($REQ, $RES, $ARG)
	{
		$dbc = $this->_container->DB;

		$sql = 'SELECT * FROM b2b_incoming WHERE license_id = :l0 AND id = :id';
		$arg = array(
			':l0' => $_SESSION['License']['id'],
			':id' => $ARG['id'],
		);
		$T = $dbc->fetchRow($sql, $arg);

		// Fetch from CRE
		if (!empty($_SESSION['pipe-token'])) {
			$cre = new \OpenTHC\CRE($_SESSION['pipe-token']);
			// $res = $cre->get('/transfer/incoming/' . $ARG['id']);
			// if ('success' != $res['status']) {
			// 	print_r($res);
			// 	die("Cannot Load Transfer");
			// }	
		}

		$til = $dbc->fetchAll('SELECT * FROM b2b_incoming_item WHERE transfer_id = ?', $T['id']);
		$T['inventory_transfer_items'] = $til;


		$data = array(
			'Page' => array('title' => sprintf('Transfer %s', $T['id'])),
			'Transfer' => $T,
			'Transfer_Item_List' => $til,
			'Target_License' => new \OpenTHC\License($dbc, $T['license_id']),
			'Origin_License' => new \OpenTHC\License($dbc, $T['license_id_origin']),
		);

		return $this->_container->view->render($RES, 'page/transfer/view.html', $data);

	}
}
