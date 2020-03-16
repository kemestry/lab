<?php
/**
 * Wraps all the Routing for the Result Module
 */

namespace App\Module;

use Edoceo\Radix\DB\SQL;

class Result extends \OpenTHC\Module\Base
{
	function __invoke($a)
	{
		$a->get('', 'App\Controller\Result\Home');
		$a->map(['GET','POST'], '/sync', 'App\Controller\Result\Sync');

		$a->get('/create', 'App\Controller\Result\Create');
		$a->post('/create/save', 'App\Controller\Result\Create:save');

		$a->get('/download', 'App\Controller\Result\Download');
		$a->map(['GET','POST'], '/upload', 'App\Controller\Result\Upload');
		$a->get('/upload/preview', 'App\Controller\Result\Upload:preview');
		$a->map(['GET','POST'], '/upload/queue', 'App\Controller\Result\Queue');

		$a->get('/edit', function($REQ, $RES, $ARG) {
			$data = array();
			$data['Page'] = array('title' => 'Result :: Edit');

			// Get Result
			$id = $_GET['result_id'];
			$LR = new \App\Lab_Result($id);
			if (empty($LR['id'])) {
				_exit_html(sprintf("Invalid Lab Result, <a href='/result/%s/sync'>maybe try to sync?</a> [LRE#035]", $id), 404);
				return $RES->withStatus(404);
			}

			$meta = json_decode($LR['meta'], true);
			$sample_id = $meta['Result']['global_for_inventory_id'];

			// @todo should be License ID
			$sql = 'SELECT * FROM lab_sample WHERE license_id = :l0 AND id = :g0';
			$arg = array(':l0' => $_SESSION['License']['id'], ':g0' => $sample_id);
			$Sample = SQL::fetch_row($sql, $arg);
			echo '<pre>'; _exit_text($Sample);
			if (empty($Sample['id'])) {
				_exit_text('Invalid Sample [LRE#048]', 400);
			}
			$sample_meta = json_decode($Sample['meta'], true);

			// meta ref Sample['meta']
			$data['Sample']  = $sample_meta['Lot'];
			$data['Product']  = $sample_meta['Product'];
			$data['Product']['type_nice'] = sprintf('%s/%s', $data['Product']['type'], $data['Product']['intermediate_type']);
			$data['Strain']  = $sample_meta['Strain'];

			// Get authoriative lab metrics
			$sql = 'SELECT * FROM lab_metric ORDER BY type,stat,name';
			$metricTab = \Edoceo\Radix\DB\SQL::fetch_all($sql);
			// _exit_text($metricTab);
			$MetricList = array(); // This list is organized by the metric's type. I need it to make render the view eaiser.
			// I could have made it type-flat and made the view branch on the incorrect type. I think this would have made
			// it more difficult to refactor this for other RCEs.
			foreach ($metricTab as $index => $metric) {

				$type = $metric['type'];
				$key = $metric['id'];
				$metric_meta = json_decode($metric['meta'], true);

				// If the last character of CRE path is a deprecation symbol (null, '', '~', ...), then filter out
				// $creEngine = $_SESSION['rbe']['engine'];
				$creEngine = 'leafdata';
				$metricPath = $metric_meta['cre'][$creEngine]['path'];
				if (empty($metricPath) || substr($metricPath, -1) === '~') {
					continue;
				}

				// Promote the user's RCE metric path to the stub
				$metric_meta['stub'] = $metricPath;

				// Add metric to it's type list, in the Metric List
				if (empty($MetricList[$type])) $MetricList[$type] = array();

				$metric['meta'] = $metric_meta;

				$metric['result'] = $meta['Result'][$metricPath]; // Load the value from LD into our Metric Table
				$MetricList[$type][$key] = $metric;
			}

			$data['MetricList'] = $MetricList;
			return $this->_container->view->render($RES, $file = 'page/result/create.html', $data);

		});

		$a->map([ 'GET', 'POST'], '/{id}', 'App\Controller\Result\View');
		$a->get('/{id}/sync', 'App\Controller\Result\Sync'); // @deprecated, post to /sync w/ID

	}
}
