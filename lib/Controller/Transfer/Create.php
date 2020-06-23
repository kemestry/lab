<?php
/**
 * Create a Result
 */

namespace App\Controller\Transfer;

use Edoceo\Radix\Session;

class Create extends \App\Controller\Base
{
	function __invoke($REQ, $RES, $ARG)
	{
		$data = $this->loadSiteData();

		return $this->_container->view->render($RES, 'page/transfer/create.html', $data);
	}
}
