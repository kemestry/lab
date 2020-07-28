<?php
/**
 * Return One Lab Result, Inflated
 */

namespace App\Controller\API\Result;

use App\Lab_Result;

class Single extends \OpenTHC\Controller\Base
{
	function __invoke($REQ, $RES, $ARG)
	{
		// Get Result
		$QAR = new Lab_Result($ARG['id']);
		//_exit_json($QAR);

		$Result = $QAR->_Result['Result']; // wtf?
		$Result = $this->_map_metric($Result);
		//_exit_json($Result);

		$RET = array(
			'Company' => $QAR->_Company,
			'License' => array(
				'id' => $QAR->_License['licensenum'],
				'name' => $QAR->_License['name'],
			),
			'Inventory' => array(
				'id' => $QAR->_Inventory['guid'],
				'type' => array(
					'id' => $QAR->_Inventory['inventorytype'],
					'name' => $QAR->_Inventory['inventorytype_name'],
				),
				'strain' => $QAR->_Inventory['strain'],
				'product' => $QAR->_Inventory['product'],
				'created_at' => $QAR->_Inventory['created_at'],
			),
			'Laboratory' => ($Lab1 ? $Lab1 : $Lab0),
			//'_Laboratory0' => $Lab0,
			//'_Laboratory1' => $Lab1,
			'Sample' => $Sample,
			'Result' => $Result,
		);

		return $RES->withJSON($RET, 200, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);

	}

	/**
	 * Remap LeafData metric names to our metric table
	 * @param array $R Result data array
	 * @return array $R
	 */
	function _map_metric($R)
	{
		$tab = array();

		$res_metric = $this->_container->DB->fetchAll('SELECT * FROM lab_metric');
		foreach ($res_metric as $m) {

			$m = array_merge($m, json_decode($m['meta'], true));
			//var_dump($m);

			$p = $m['cre']['leafdata_path'];

			if (!empty($p)) {
				$tab[ $m['id'] ] = array(
					'type' => $m['type'],
					'name' => $m['name'],
					'uom' => $m['uom'],
					'qom' => $R[$p],
				);
				unset($R[$p]);
			}
		}

		$R['metric_list'] = $tab;

		return $R;

	}
}
