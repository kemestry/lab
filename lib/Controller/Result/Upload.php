<?php
/**
 * Accept Upload of Sampole Files
 */

namespace App\Controller\Result;

class Upload extends \OpenTHC\Controller\Base
{
	function __invoke($REQ, $RES, $ARG)
	{
		$data = array();
		$data['Page'] = array('title' => 'Result :: COA :: Upload');

		return $this->_container->view->render($RES, 'page/result/upload.html', $data);
	}
}
