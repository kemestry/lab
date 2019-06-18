<?php
/**
 * View a Single Transfer
 */

namespace App\Controller\Transfer;

class View extends \OpenTHC\Controller\Base
{
	function __invoke($REQ, $RES, $ARG)
	{
		$cre = new \OpenTHC\RCE($_SESSION['pipe-token']);

		$res = $cre->get('/transfer/incoming/' . $ARG['id']);
		if ('success' != $res['status']) {
			print_r($res);
			die("Cannot Load Transfer");
		}

		$data = array(
			'Page' => array('title' => sprintf('Transfer %s', $ARG['id'])),
			'Transfer' => $res['result'],
		);
		// _exit_text($data);

		return $this->_container->view->render($RES, 'page/transfer/view.html', $data);

	}
}
