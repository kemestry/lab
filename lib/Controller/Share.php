<?php
/**
 * Details about Sharing
*/

namespace App\Controller;

class Share extends \App\Controller\Base
{
	function __invoke($REQ, $RES, $ARG)
	{
		$file = 'page/share.html';
		$data = array(
			'Page' => array('title' => 'Sharing')
		);
		$data = $this->loadSiteData($data);
		return $this->_container->view->render($RES, $file, $data);
	}
}
