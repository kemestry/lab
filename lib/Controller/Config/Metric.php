<?php
/**
 * Configure Metrics
 */

namespace App\Controller\Config;

class Metric extends \OpenTHC\Controller\Base
{
	function __invoke($REQ, $RES, $ARG)
	{
		$data = [];
		$data['Page'] = [ 'title' => 'Config :: Metrics' ];

		$data['metric_list'] = $this->_container->DB->fetchAll('SELECT * FROM lab_metric ORDER BY type, sort, name');

		$file = 'page/config/metric.html';

		return $this->_container->view->render($RES, $file, $data);

	}
}
