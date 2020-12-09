<?php
/**
 * Create a Result
 */

namespace App\Controller\Result;

use Edoceo\Radix\Session;

use App\Lab_Result;
use App\Lab_Sample;

class Create extends \OpenTHC\Controller\Base
{
	function __invoke($REQ, $RES, $ARG)
	{
		$data = array();
		$data['Page'] = array('title' => 'Result :: Create');

		$dbc = $this->_container->DBC_User;

		// @todo should be License ID
		$sql = 'SELECT * FROM lab_sample WHERE license_id = :l0 AND id = :g0';
		$arg = [
			':l0' => $_SESSION['License']['id'],
			':g0' => $_GET['sample_id'],
		];
		$chk = $dbc->fetchRow($sql, $arg);
		if (empty($chk['id'])) {
			_exit_text('Invalid Sample [CRC#022]', 400);
		}

		$meta = \json_decode($chk['meta'], true);

		$data['Sample'] = $chk; //  $meta['Lot'];
		$data['Product'] = $meta['Product'];
		$data['Product']['type_nice'] = sprintf('%s/%s', $data['Product']['type'], $data['Product']['intermediate_type']);
		$data['Strain'] = $meta['Strain'];

		// Get authoriative lab metrics
		$sql = 'SELECT * FROM lab_metric ORDER BY type,stat,name';
		$metricTab = $dbc->fetchAll($sql);
		// _exit_text($metricTab);
		$MetricList = array(); // This list is organized by the metric's type. I need it to make render the view eaiser.
		// I could have made it type-flat and made the view branch on the incorrect type. I think this would have made
		// it more difficult to refactor this for other RCEs.
		foreach ($metricTab as $index => $metric) {

			$type = $metric['type'];
			$key = $metric['id'];
			$meta = json_decode($metric['meta'], true);
			if (empty($meta['uom'])) {
				$meta['uom'] = 'pct';
			}

			// Filter out read-only or RBE-calculated fields
			$calculated = $meta['cre'][$creEngine]['calculated'] ?: false;
			$readOnly = $meta['cre'][$creEngine]['readonly'] ?: false;
			if ($calculated || $readOnly) {
				continue;
			}

			// Promote the user's RCE metric path to the stub
			$meta['stub'] = $metricPath;

			// Add metric to it's type list, in the Metric List
			if (empty($MetricList[$type])) $MetricList[$type] = array();

			$metric['meta'] = $meta;
			$MetricList[$type][$key] = $metric;
		}

		$data['MetricList'] = $MetricList;

		$file = 'page/result/create.html';

		return $this->_container->view->render($RES, $file, $data);

	}

	/**
	 * [save description]
	 * @param [type] $REQ [description]
	 * @param [type] $RES [description]
	 * @param [type] $ARG [description]
	 * @return [type] [description]
	 */
	function save($REQ, $RES, $ARG)
	{
		switch ($_POST['a']) {
		case 'commit':
			return $this->_commit($REQ, $RES, $ARG);
		case 'save':
			return $this->_save($REQ, $RES, $ARG);
		default:
			return $RES->withStatus(400);
		}
	}

	private function _save($REQ, $RES, $ARG)
	{
		$dbc = $this->_container->DBC_User;

		// Get and validate the QA Sample
		$sampleId = $_POST['sample_id'];
		$sql = 'SELECT * from lab_sample WHERE id = :id AND license_id = :lic';
		$args = [
			':id' => $sampleId,
			':lic' => $_SESSION['License']['id'],
		];
		$Sample = $dbc->fetchRow($sql, $args);
		if (empty($Sample['id'])) {
			_exit_text(sprintf('Could not find Sample Lot: %s [LPC#128]', $sampleId), 409);
		}

		// Get the authorative lab metrics
		// This list is type-flat, and it's IDs the row ULID
		$sql = "SELECT *, meta->>'uom' AS uom FROM lab_metric"; //  ORDER BY type,stat,name';
		$res_lab_metric = $dbc->fetchAll($sql);

		$dbc->query('BEGIN');

		$LR = new Lab_Result($dbc);
		$LR['id'] = _ulid();
		$LR['guid'] = $LR['id'];
		$LR['license_id'] = $_SESSION['License']['id'];
		$LR['lab_sample_id'] = $Sample['id'];
		$LR['stat'] = 200;
		$LR['flag'] = 0;
		$LR['type'] = 'unknown';
		$LR['name'] = sprintf('Lab Result for Sample Lot: %s', $Sample['id']);
		$LR['uom'] = 'g';
		$LR['hash'] = $LR->getHash();
		$LR->save();

		// Save Metrics
		foreach ($res_lab_metric as $m) {
			$k = $m['id'];
			$dbc->insert('lab_result_metric', [
				'id' => _ulid(),
				'lab_result_id' => $LR['id'],
				'lab_metric_id' => $k,
				// 'flag' => 0,
				'qom' => floatval($_POST[$k]),
				'uom' => $m['uom'],
				// 'lod' => $m['meta']['lod'],
				// 'loq' => $m['meta']['loq'],
			]);
		}


		// Link Sample to this, Most Recent Result
		$sql = 'UPDATE lab_sample SET stat = :s1, lab_result_id = :lr1 WHERE id = :ls0';
		$arg = [
			':ls0' => $Sample['id'],
			':lr1' => $LR['id'],
			':s1' => Lab_Sample::STAT_DONE,
		];
		$dbc->query($sql, $arg);

		$dbc->query('COMMIT');

		return $RES->withRedirect('/result/' . $LR['id']);

	}

}
