<?php
/**
 * Sync All the Things
 */

namespace App\Controller;

class Sync extends \OpenTHC\Controller\Base
{
	function __invoke($REQ, $RES, $ARG)
	{
		if ($_GET['a'] == 'sync') {
			return $this->exec($REQ, $RES, $ARG);
		}

		$data = array(
			'Page' => array('title' => 'Sync'),
			'r' => $_GET['r'],
		);

		return $this->_container->view->render($RES, 'page/sync.html', $data);

	}

}
