<?php
/**
 * Create a Sample
 */

namespace App\Controller\Sample;

use Edoceo\Radix\Session;

class Create extends \App\Controller\Base
{
	function __invoke($REQ, $RES, $ARG)
	{
		$data = $this->loadSiteData();
		return $this->_container->view->render($RES, 'page/sample/create.html', $data);
	}
}
