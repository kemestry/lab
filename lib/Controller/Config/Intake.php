<?php
/**
 * Configure Metrics
 */

namespace App\Controller\Config;

use App\Lab_Metric;

class Intake extends \App\Controller\Base
{
	function __invoke($REQ, $RES, $ARG)
	{
		$data = $this->loadSiteData();

		// $this->insertTwigFunctions();

		// // $file = 'page/html.html';
		// $data['html'] = $this->render('config/metric.php');
		$file = 'page/config/intake.html';
		return $this->_container->view->render($RES, $file, $data);

	}
}
