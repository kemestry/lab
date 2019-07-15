<?php
/**
 * Create a Result
 */

namespace App\Controller\Result;

use Edoceo\Radix\DB\SQL;

class Create extends \OpenTHC\Controller\Base
{
	function __invoke($REQ, $RES, $ARG)
	{
		$data = array();
		$data['Page'] = array('title' => 'Result :: Create');

		// @todo should be License ID
		$sql = 'SELECT * FROM lab_sample WHERE company_id = :c0 AND id = :g0';
		$arg = array(':c0' => $_SESSION['gid'], ':g0' => $_GET['sample_id']);
		$chk = SQL::fetch_row($sql, $arg);
		if (empty($chk['id'])) {
			_exit_text('Invalid Sample [CRC#022]', 400);
		}

		// $Sample = new \App\Lab_Sample($chk);
		$meta = \json_decode($chk['meta'], true);

		$data['Sample']  = $meta['Lot'];
		$data['Product']  = $meta['Product'];
		$data['Product']['type_nice'] = sprintf('%s/%s', $data['Product']['type'], $data['Product']['intermediate_type']);
		$data['Strain']  = $meta['Strain'];

		//$data['Result']  = $res['Result'];
		//$data['Product'] = $QAR['Product'];


		// Get authoriative lab metrics
		$sql = 'SELECT * FROM lab_metric ORDER BY type,stat,name';
		$metricTab = \Edoceo\Radix\DB\SQL::fetch_all($sql);
		$MetricList = array(); // This list is organized by the metric's type. I need it to make render the view eaiser.
		// I could have made it type-flat and made the view branch on the incorrect type. I think this would have made
		// it more difficult to refactor this for other RCEs.
		foreach ($metricTab as $index => $metric) {

			$type = $metric['type'];
			$key = $metric['id'];
			$meta = json_decode($metric['meta'], true);


			// Resolve the user's CRE fields
			// $user_cre = $_SESSION['cre']['name']; // @todo
			$user_cre = 'leafdata';

			// If the last character of CRE path is a deprecation symbol (null, '', '~', ...), then filter out
			$metricPath = $meta['cre']["$user_cre" . "_path"];
			if (empty($metricPath) || substr($metricPath, -1) === '~') {
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

		//_exit_text($data);

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
			return $this->_save($REQ, $RES, $ARG);
		case 'save':
			return $this->_save($REQ, $RES, $ARG);
		default:
			return $RES->withStatus(400);
		}
	}

	private function _save($REQ, $RES, $ARG)
	{
		header('content-type: text/plain');

		var_dump($_POST);

		// Resolve the user's CRE fields
		// $user_cre = $_SESSION['cre']['name']; // @todo
		$user_cre = 'leafdata';

		// Get the authorative lab metrics
		$sql = 'SELECT * FROM lab_metric ORDER BY type,stat,name';
		$metricTab = \Edoceo\Radix\DB\SQL::fetch_all($sql);
		// This list is type-flat, and it's IDs the row ULID
		$MetricList = array();
		foreach ($metricTab as $index => $metric) {
			$key = $metric['id'];
			$meta = json_decode($metric['meta'], true);

			$metric['meta'] = $meta;

			// If the last character of CRE path is a deprecation symbol (null, '', '~', ...), then filter out
			$metricPath = $metric['meta']['cre']["$user_cre" . "_path"];
			if (empty($metricPath) || substr($metricPath, -1) === '~') {
				continue;
			}

			$MetricList[$key] = $metric;
		}

		/**
		 * Metric Results filter:
		 * 		This loop grabs the metrics that the app knows about.
		 * 		Other lab result items, like status, will be handled below in it's own filter of the post body.
		 *
		 * Results of Lab Metrics data structure:
		 * [Metric Type] => [			# As it appears in row.type
		 * 		[ulid PK] => [
		 * 			...lab_metric.row,
		 * 			'result': string (on Metric Results filter pass)
		 * 		],
		 * 		...,
		 * 		'status': enum['not_started', 'in_progress', 'completed'] (on Testing Status filter pass, does not exist when status=General)
		 * ],
		 * ...,
		 * 'status': enum['not_started', 'in_progress', 'completed'] (on Test Progress filter pass)
		 */
		$post = $REQ->getParsedBody();
		// echo '<pre>'; var_dump($post);die;
		$ResultTable = array();
		foreach ($post as $key => $postValue) {
			// Original plan was only iterate over Metric form values,
			// don't consider empty() values
			// https://www.php.net/manual/en/types.comparisons.php
			// String literal "0" is considered an empty value in PHP.
			// Abstracted with decotator func to determine if post is valid value
			// based on the metric's unit of measure.
			if ($this->isValidResultValue($postValue, $MetricList[$key])
					&& (
					// Fix this to check the length on the left and right side of LM
					preg_match('/(([A-Za-z0-9]+LM[A-Za-z0-9]+))/', $key, $match)
			)) {
				// stop here, find about about why general isnt making it in
				// If input metric doesn't exist, throw away entry.
				$metricId = $key;
				$metric = $MetricList[$metricId];
				if (empty($metric)) continue;

				$metricType = $metric['type'];
				// Is there already an entry in Results table of this type?
				if (empty($ResultTable[$metricType])) $ResultTable[$metricType] = array();

				$ResultTable[$metricType][$metricId] = $metric;

				// Promote post value to the Result value of the metric
				$ResultTable[$metricType][$metricId]['result'] = $postValue;
			}
		}

		/**
		 * Testing Status filter
		 * 		Leaf Data Possible test Statues:
		 * 		'metal_status' 'microbial_status' 'mycotoxin_status'
		 * 		'pesticide_status' 'solvent_status' 'testing_status'
		 *
		 * 		From LeafData Manual: 'testing_status' Denotes the
		 * 		stage of completion of the entirety of the lab result
		 * 		record; optional upon creation of lab result record,
		 * 		but required to be "completed" for lab result record
		 * 		to be finalized
		 *
		 * 		THIS LOOP WILL NOT FILTER 'testing_status'
		 * 		I kept testing_status from being included because I
		 * 		didn't want it accidentally getting put in the type
		 * 		list that. It gets filtered in the conditional below.
		 *
		 * This loop takes the list of results grouped by their type
		 * from the results table, and tries to find a cooresponding
		 * field with the name <type>_status, ie cannadinoid_status,
		 * solvent_status, etc. It adds the 'status' field to each
		 * Results Type list.
		 *
		 */
		foreach ($ResultTable as $resultType => $typeList) {

			// General type doesn't have a status associated with it / is a bunch of statueses
			// General type gets captured in the previous filter, we dont need to operate on it here
			if ('general' === strtolower($resultType)) continue;

			$statusKey = sprintf("%s_%s", strtolower($resultType), "status");
			// If post value for <type>_status is empty
			if (empty($post[$statusKey]) && count($typeList) === 0) {
				_exit_text("LRC#115: $resultType must have a status associated with the results.");
			}

			$Status_Answers = ['not_started', 'in_progress', 'completed'];
			if (!in_array($post[$statusKey], $Status_Answers)) {
				_exit_text("LRC#120: Status response not valid.");
			}
			$ResultTable[$resultType]['status'] = $post[$statusKey];
		}

		/**
		 * Test Progress filter
		 * 		Filter the pass/fail results of the 'testing_status'
		 * 		value.
		 * 		'testing_status' is a required field, but its modifiable
		 * 		In order to 'finalize' the lab result we must set it to
		 * 		'completed'. If the field is null, empty, or not_started,
		 *
		 *
		 */
		$Status_Answers = ['not_started', 'in_progress', 'completed'];
		$ld_testingStatusKey = 'testing_status';
		$testingStatus = $_POST[$ld_testingStatusKey];
		if (!empty($testingStatus) && in_array($testingStatus, $Status_Answers)) {
			$ResultTable['status'] = $testingStatus;
		}

		// Persist the result to the Sample's meta field with md5 hash
		$resultsCache = json_encode($ResultTable);
		$hash = md5($resultsCache);

		// Get and validate the QA Sample
		$sampleId = $_POST['sample_id'];
		$sql = 'SELECT * from lab_sample WHERE id = :id AND license_id = :lic';
		$args = [
			':id' => $sampleId,
			':lic' => $_SESSION['License']['id'],
		];
		$Sample = SQL::fetch_row($sql, $args);
		if (empty($Sample['id'])) {
			_exit_text(sprintf('Could not find: %s [LPC#128]', $sampleId), 409);
		}

		$meta = json_decode($Sample['meta'], true);
		//_exit_text($meta);
		$Sample = $meta['Lot'];
		$Product = $meta['Product'];
		$Strain = $meta['Strain'];

		// $Sample['meta'] = $meta;

		// $cre->lab()->result()->create();
		$c = new \GuzzleHttp\Client(array(
			'base_uri' => 'https://watest.leafdatazone.com',
			'headers' => [
				'x-mjf-mme-code' => $_SESSION['cre-auth']['license'],
				'x-mjf-key' => $_SESSION['cre-auth']['license-key'],
			],
			'allow_redirects' => false,
			'debug' => $_ENV['debug-http'],
			'request.options' => array(
				'exceptions' => false,
			),
			'http_errors' => false,
			'cookies' => true,

		));

		$unprocessableEntity = [
			/**
				 ""required params""
			 */
			/**
			 * Format: WAX123456.IN1Z2Y3
			 * Global id, Relative to Lab, of inv lot being tested.
			 */
			'global_inventory_id' => $Sample['global_id'],
			"global_for_mme_id" => $Sample['global_mme_id'], // Required
			"global_for_inventory_id" => $Sample['global_original_id'],

			// Medical testing requirements
			// Non-medical testing requirements

			// 'cannabinoid_status' => 'completed', // [not_started,in_progress,completed]
			// 'metal_status' 		=> 'completed', // $this->metricToLeafMetric($post['metal_status']),
			// 'microbial_status' 	=> 'completed', // $this->metricToLeafMetric($post['microbial_status']),
			// 'mycotoxin_status' 	=> 'completed', // $this->metricToLeafMetric($post['mycotoxin_status']),
			// 'pesticide_status' 	=> 'completed', // $this->metricToLeafMetric($post['pesticide_status']),
			// 'solvent_status' 	=> 'completed', // $this->metricToLeafMetric($post['solvent_status']),
			// 'testing_status' 	=> 'completed', // $this->metricToLeafMetric($post['testing_status']),

			//"notes" => "test notes",
			'testing_status' => $_POST['testing_status'],
			'tested_at' => date('m/d/Y g:i:s a'),

			// Take these from the Sample's Inventory data
			// "type" => "harvest_materials",
			// "intermediate_type" => 'flower_lots',

			/**
			 * Format: boolean
			 * The results of the foreign matter
			 * screening for stems ("0"=passing, "1"=failing)
			 */
			// "foreign_matter_stems" => "0", // $this->metricToLeafMetric(),
			// "foreign_matter_seeds" => "0", // $this->metricToLeafMetric(''),
			// "test_for_terpenes" => null,

			/**
			 * Format: WAX123456.BA1Z2Y3
			 * global ID of the batch associated
			 * with the inventory lot that the sample came from
			 * Documentation says auto generated
			 */
			// "global_batch_id" => $Sample['global_batch_id'],
			// "global_batch_id" => $Sample['meta']['global_received_batch_id'],

		];

		// Add the entered Result values to our LD Lab Result list
		foreach ($ResultTable as $type => $resultList) {

			if ('status' === $type) {
				$testingStatus = $resultList;
				$unprocessableEntity['testing_status'] = $testingStatus;
				continue;
			}
			// Append the metric key and the result to the RCE data object
			foreach ($resultList as $metricId => $Result) {

				if ('status' === $metricId) {

					// Special cases for Microbial results
					if ('microbe' === strtolower($type)) {
						$typeStatus = 'microbial_status';
					} else {
						$typeStatus = sprintf("%s_status", strtolower($type));
					}

					$unprocessableEntity[$typeStatus] = $Result;
					continue;
				}
				$creKey = sprintf("%s_path", $user_cre);
				$unprocessableEntity[$Result['meta']['cre'][$creKey]] = $Result['result'];
			}
		}

		echo json_encode($unprocessableEntity, JSON_PRETTY_PRINT);

		$res = $c->post('/api/v1/lab_results', [
			'json' => [
				'lab_result' => $unprocessableEntity
			]
		]);

		$buf = $res->getBody()->getContents();

		if ($res->getStatusCode() === 200) {
			echo "<h2>Results Accepted!</h2>";
		} else {
			echo "<h2>Results Rejected!</h2>";
		}

		$res = json_decode($buf, true);
		_ksort_r($res);

		echo json_encode($res, JSON_PRETTY_PRINT);

		// How to Link Together?
		// Mark Sample Lot as Tested/OK/Done

		exit(0);
	}

	function commit($REQ, $RES, $ARG)
	{

	}

	private function isValidResultValue($resultValue, $metric)
	{
		$metric = $metric ?: [];
		if (!array_key_exists('meta', $metric)) return false;

		switch (strtolower($metric['meta']['uom'])) {

			case 'ppb':
			case 'ppm':
			case 'pct':
			case 'cfu/g':
			case 'aw':
				return is_numeric($resultValue);

			case 'bool':
				return ('true' === strtolower($resultValue) || 'false' === strtolower($resultValue));

			default:
				return false;
		}
	}


}
