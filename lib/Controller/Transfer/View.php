<?php
/**
 * View a Single Transfer
 */

namespace App\Controller\Transfer;

class View extends \OpenTHC\Controller\Base
{
	function __invoke($REQ, $RES, $ARG)
	{
		$sql = 'SELECT * FROM transfer_incoming WHERE license_id = :l0 AND id = :id';
		$arg = array(
			':l0' => $_SESSION['License']['id'],
			':id' => $ARG['id'],
		);
		$T = $this->_container->DB->fetchRow($sql, $arg);

		// Fetch from CRE
		$cre = new \OpenTHC\CRE($_SESSION['pipe-token']);
		$res = $cre->get('/transfer/incoming/' . $ARG['id']);
		if ('success' != $res['status']) {
			print_r($res);
			die("Cannot Load Transfer");
		}

		$data = array(
			'Page' => array('title' => sprintf('Transfer %s', $ARG['id'])),
			'Transfer' => $res['result'],
			'Target_License' => new \OpenTHC\License($T['license_id']),
			'Origin_License' => new \OpenTHC\License($T['license_id_origin']),
		);

		// _exit_text($data);

		return $this->_container->view->render($RES, 'page/transfer/view.html', $data);

	}
}
