<?php
/**
 * Configure Instrument
 */

namespace App\Controller\Config;

class Instrument extends \App\Controller\Base
{
	function __invoke($REQ, $RES, $ARG)
	{
		$data = $this->loadSiteData();
		$data['Page']['title'] = 'Config :: Instruments';

		$file = 'page/config/instrument.html';
		return $this->_container->view->render($RES, $file, $data);

	}
}
