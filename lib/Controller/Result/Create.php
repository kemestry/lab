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

		$sql = 'SELECT * FROM qa_sample WHERE company_id = :c0 AND id = :g0';
		$arg = array(':c0' => $_SESSION['gid'], ':g0' => $ARG['sample_id']);
		$chk = SQL::fetch_row($sql, $arg);

		$Sample = new \App\QA_Sample($chk);
		$meta = $Sample['meta'];
		$meta = \json_decode($meta, true);

		$data['Sample']  = $Sample;
		$data['Sample_meta'] = $meta;
		$data['MetricList'] = $MetricList;
		$data['sample_id'] = $ARG['sample_id'];
		// $data['CannabinoidList'] = $CannabinoidMetrics;
		//$data['Result']  = $res['Result'];
		//$data['Product'] = $QAR['Product'];
		// echo '<pre>';
		// var_dump($MetricList);
		// exit(0);

		$file = 'page/result/create.html';
		return $this->_container->view->render($RES, $file, $data);

	}

	function save($REQ, $RES, $ARG)
	{
		$post = $REQ->getParsedBody();
		switch ($post['a'])
		{
			case 'commit':
				return $this->commit($REQ, $RES, $ARG);

			case 'save':
				return $this->_save($REQ, $RES, $ARG);

			default:
				return $RES->withStatus(400);
		}
	}

	private function _save($REQ, $RES, $ARG)
	{
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
		$testingStatus = $post[$ld_testingStatusKey];
		if (!empty($testingStatus) && in_array($testingStatus, $Status_Answers)) {
			$ResultTable['status'] = $testingStatus;
		}

		// Persist the result to the Sample's meta field with md5 hash
		$resultsCache = json_encode($ResultTable);
		$hash = md5($resultsCache);

		// Get and validate the QA Sample
		$sampleId = $ARG['sample_id'];
		$sql = 'SELECT * from qa_sample WHERE id = :id AND license_id = :lic';
		$args = [
			':id' => $sampleId,
			':lic' => $_SESSION['License']['id'],
		];
		$Sample = SQL::fetch_row($sql, $args);
		$meta = json_decode($Sample['meta'], true);

		if (empty($Sample['id'])) {
			print_r($Sample);
			_exit_text(sprintf("LPC#128: Could not find: %s", $Sample['id']), 409);
		}
		$Sample['meta'] = $meta;

		$c = new \GuzzleHttp\Client(array(
			'base_uri' => 'https://watest.leafdatazone.com',
			'headers' => [
				'x-mjf-mme-code' => 'LWTL', // $_ENV['leafdata-license'], // 'username',
				'x-mjf-key' => 'yLvwqLv2nMyx1orsHxJD',// $_ENV['leafdata-license-secret'], // 'password'
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
			// 'external_id' => '-', // required?
			// 'global_inventory_id' => '', // "WAX123456.IN1Z2Y3"

			// Medical testing requirements
			// Non-medical testing requirements

			// 'cannabinoid_status' => 'completed', // [not_started,in_progress,completed]
			// 'metal_status' 		=> 'completed', // $this->metricToLeafMetric($post['metal_status']),
			// 'microbial_status' 	=> 'completed', // $this->metricToLeafMetric($post['microbial_status']),
			// 'mycotoxin_status' 	=> 'completed', // $this->metricToLeafMetric($post['mycotoxin_status']),
			// 'pesticide_status' 	=> 'completed', // $this->metricToLeafMetric($post['pesticide_status']),
			// 'solvent_status' 	=> 'completed', // $this->metricToLeafMetric($post['solvent_status']),
			// 'testing_status' 	=> 'completed', // $this->metricToLeafMetric($post['testing_status']),

			"external_id" => 'test',
			"testing_status" => $post['testing_status'],
			"notes" => "test notes",

			"tested_at" => '06/10/2019 12:34pm',
			"received_at" => '06/10/2019 12:34pm',

			// Take these from the Sample's Inventory data
			"type" => "harvest_materials",
			"intermediate_type" => 'flower_lots',

			/**
			 * Format: boolean
			 * The results of the foreign matter
			 * screening for stems ("0"=passing, "1"=failing)
			 */
			// "foreign_matter_stems" => "0", // $this->metricToLeafMetric(),
			// "foreign_matter_seeds" => "0", // $this->metricToLeafMetric(''),
			// "test_for_terpenes" => null,
			"global_for_mme_id" => $meta['global_mme_id'],

			/**
			 * Format: WAX123456.IN1Z2Y3
			 * Global id, Relative to Lab, of inv lot being tested.
			 */
			// "global_inventory_id" => $sample_id,
			"global_inventory_id" => $Sample['meta']['global_received_inventory_id'],

			/**
			 * Format: WAX123456.BA1Z2Y3
			 * global ID of the batch associated
			 * with the inventory lot that the sample came from
			 * Documentation says auto generated
			 */
			// "global_batch_id" => $Sample['global_batch_id'],
			"global_batch_id" => $Sample['meta']['global_received_batch_id'],

			/**
			 *
			 */
			// "global_for_inventory_id" => $meta['global_inventory_id'],
			"global_for_inventory_id" => $meta['global_received_inventory_id'],
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

		$res = $c->post('/api/v1/lab_results', [
			'json' => [
				'lab_result' => $unprocessableEntity
			]
		]);

		if ($res->getStatusCode() === 200) {
			echo "<h2>Results Accepted!</h2>";
		} else {
			echo "<h2>Results Rejected!</h2>";
		}
		var_dump(json_decode($res->getBody(), true));
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

