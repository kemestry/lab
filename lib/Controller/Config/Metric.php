<?php
/**
 * Configure Metrics
 */

namespace App\Controller\Config;

class Metric extends \App\Controller\Base
{
	function __invoke($REQ, $RES, $ARG)
	{
		$data = [];
		$data['Page'] = [ 'title' => 'Config :: Metrics' ];
		$this->data = $this->loadSiteData($data);

		$this->data['metric_list'] = $this->_container->DB->fetchAll('SELECT * FROM lab_metric ORDER BY type, sort, name');
		// $this->data['product_type'] = $this->_container->DB->fetchMix('SELECT id, name FROM product_type');
		$this->data['html'] = $this->render('config/metric.php');

		$file = 'page/html.html';
		return $this->_container->view->render($RES, $file, $this->data);

	}
}
