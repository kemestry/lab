<?php
/**
 * Sync One Lab Result
 */

namespace App\Controller\Result;

use Edoceo\Radix\DB\SQL;
use Edoceo\Radix\Net\HTTP;

class Sync extends \OpenTHC\Controller\Base
{
	function __invoke($REQ, $RES, $ARG)
	{
		session_write_close();

		if (!empty($ARG['id'])) {
			return $this->syncOne($REQ, $RES, $ARG);
		}

		return $this->syncAll($REQ, $RES, $ARG);
	}

	/**
	 * Sync All Lab Results
	 */
	function syncAll($REQ, $RES, $ARG)
	{
		// $dbc = $this->_container->DB;

		// Lab Results
		$cre = new \OpenTHC\CRE($_SESSION['pipe-token']);
		$res = $cre->get('/lab?source=true');
		if ('success' != $res['status']) {
			return $RES->withJSON(array(
				'status' => 'failure',
				'result' => $cre->formatError($res),
			), 500);
		}

		foreach ($res['result'] as $rec) {
			$this->_syncOne($rec);
		}

		$C = new \OpenTHC\Company($_SESSION['Company']);
		$C->setOption('sync-qa-time', $_SERVER['REQUEST_TIME']);

		$RES = $RES->withJSON(array(
			'status' => 'success',
		));

	}

	/**
	 *
	 */
	function syncOne($REQ, $RES, $ARG)
	{

		// Filter Shit
		if (preg_match('/^WAATTEST.+/', $ARG['id'])) {
			$arg = [
				':lr' => $ARG['id'],
				':f1' => \App\Lab_Result::FLAG_SYNC
			];
			$this->_container->DB->query('UPDATE lab_result SET flag = flag | :f1 WHERE id = :lr', $arg);
			return $RES->withJSON([
				'meta' => [ 'detail' => 'Placeholder Result' ],
			]);
		}

		if (empty($_SESSION['pipe-token'])) {
			return $RES->withStatus(403);
		}

		$cre = new \OpenTHC\CRE($_SESSION['pipe-token']);
		$res = $cre->get('/lab/' . $ARG['id']);
		if (empty($res)) {
			_exit_text('Cannot Load QA from CRE [CRS#029]', 500);
		}
		if ('success' != $res['status']) {
			_exit_text($cre->formatError($res), 200);
		}

		$Result = $res['result'];
		$Result['id'] = $Result['global_id'];
		$Result['type_nice'] = $this->_result_type($Result);
		// _exit_text($Result);

		if (!empty($Result['global_inventory_id'])) {
			// 	die("I'M A LAB MAYBE?");
			throw new \Exception('What does this one do gain?');
		}

		// Find Sample
		$Sample = $Result['for_inventory'];
		$Sample['id'] = $Sample['global_id'];
		// 	'name' => '- Not Found -',
		// 	'type_nice' => '-None-',
		// _exit_text($Result);

		// Is the Sample one of my own ?
		if ($Sample['global_mme_id'] == $_SESSION['License']['guid']) {

			$chk = $cre->get('/lot/' . $Sample['id']);
			// var_dump($chk); exit;
			if ('success' == $chk['status']) {
				$Sample = array_merge($chk['result'], $Sample);
			}
			// 		echo "Canot Fetch Lab Sample, use \$Result Blob?\n";
			// 		var_dump($Result['for_inventory']);
			// 		var_dump($res);

			// Do We Have a Sample Record?
			$sql = 'SELECT * FROM lab_sample WHERE license_id = :c AND id = :g';
			$arg = array(
				':c' => $_SESSION['License']['id'],
				':g' => $Sample['id'],
			);
			$chk = $this->_container->DB->fetchRow($sql, $arg);
			if (empty($chk['id'])) {
				// $L1 = SQL::fetch_row('SELECT id, company_id FROM license WHERE guid = ?', [ $Sample['global_mme_id'] ]);
				$add = array(
					'id' => $Sample['id'],
					'license_id' => $_SESSION['License']['id'],
					// 'company_id' => $L1['company_id'],
					// 'company_ulid' => $_SESSION['Company']['ulid'],
					'meta' => json_encode(array(
						'Lot' => $Sample,
						// 'Product' => $Product,
						// 'Strain' => $Strain,
					)),
				);
				SQL::insert('lab_sample', $add);
			}
		} else {
			// This Sample belongs to someone else, we only care about the results then
		}

		// 	// Find License by Global ID?
		// 	$chk = SQL::fetch_row('SELECT lab_sample.id, lab_sample.license_id, lab_sample.meta FROM lab_sample JOIN license ON lab_sample.license_id = license.id WHERE license.guid = :l1 AND lab_sample.id = :s0', array(
		// 		':l1' => $Sample['global_mme_id'],
		// 		':s0' => $Sample['id']
		// 	));
		// 	var_dump($chk);
		//
		// }

		// Find License
		$License_Lab = array();
		if (preg_match('/^WAATTESTED\./', $Result['id'])) {
			// Fake it
			$License_Lab = \OpenTHC\License::findByGUID('WAWA1.MM1'); // WA State
		} elseif ('Laboratory' == $_SESSION['License']['type']) {
			// Am I a Lab?
			$License_Lab = $_SESSION['License'];
		} else {

			// Find the Lab that Owns It
			if (empty($Result['global_mme_id'])) {
				//var_dump($res);
				//die("EMPTYLAB ADA");
				//_exit_text('Empty Lab Data [CRS#051]', 500);
			}

			// Real!
			$License_Lab = \OpenTHC\License::findByGUID($Result['global_mme_id']);
			if (empty($License_Lab['id'])) {
				// var_dump($Result);
				// exit;
				//_exit_text("Cannot find Laboratory: '{$Result['global_mme_id']}'", 404);
			}
		}

		// @todo Need to Verify that it's a LAB type license.

		//This is the Lot at the Origin
		// Product Details
		$Product = array(
			'id' => $Sample['global_inventory_type_id'],
			'name' => '- Not Found -',
			'type_nice' => '-None-',
		);

		$Strain = array(
			'id' => $Sample['global_strain_id'],
			'name' => '- Not Found -',
			'type_nice' => '-None-',
		);

		if ($Sample['global_inventory_type_id']) {

			$res = $cre->get('/config/product/' . $Sample['global_inventory_type_id']);
			$Product = $res['result'];
			$Product['id'] = $Product['global_id'];
			$Product['type_nice'] = $this->_product_type($Product);

			$res = $cre->get('/config/strain/' . $Sample['global_strain_id']);
			$Strain = $res['result'];
			if (!empty($Strain['global_id'])) {
				$Strain['id'] = $Strain['global_id'];
			} else {
				$Strain = array(
					'id' => $Sample['global_strain_id'],
					'name' => '- Not Found -',
				);
			}
		}

		// Switch Based on Type
		// First Look at Result Data types
		// Prefer Product, if we can find it.
		// $pt = sprintf('%s/%s/%s', $Result['batch_type'], $Result['type'], $Result['intermediate_type']);
		// if (!empty($Product['type']) && !empty($Product['intermediate_type'])) {
		// 	$pt = sprintf('%s/%s', $Product['type'], $Product['intermediate_type']);
		// }
		// $pt = trim($pt, ' /');
		// $pt = preg_replace('/^intermediate\/ end product/', null, $pt);
		// $pt = trim($pt,'/ ');

		$pt = $this->_result_type($Result);

		switch ($pt) {
		case 'Flower':

			// PCT
			$Result['uom'] = 'pct';
			$Result['thc'] = $Result['cannabinoid_d9_thc_percent'] + ($Result['cannabinoid_d9_thca_percent'] * 0.877);
			$Result['cbd'] = $Result['cannabinoid_cbd_percent'] + ($Result['cannabinoid_cbda_percent'] * 0.877);
			$Result['sum'] = sprintf('%0.2f%%', $Result['thc'] + $Result['cbd']);
			$Result['thc'] = sprintf('%0.2f%%', $Result['thc']);
			$Result['cbd'] = sprintf('%0.2f%%', $Result['cbd']);

			break;

		case 'Concentrate':
		case 'Edible':
		case 'Tincture':
		case 'Topical':

			// The State says to enter these as mg/g values but some labs enter them as percent :(
			$Result['uom'] = 'mgg';
			$Result['thc'] = $Result['cannabinoid_d9_thc_mg_g'] + ($Result['cannabinoid_d9_thca_mg_g'] * 0.877);
			$Result['cbd'] = $Result['cannabinoid_cbd_mg_g'] + ($Result['cannabinoid_cbda_mg_g'] * 0.877);
			$Result['sum'] = sprintf('%0.2f mg/g', $Result['thc'] + $Result['cbd']);
			$Result['thc'] = sprintf('%0.2f mg/g', $Result['thc']);
			$Result['cbd'] = sprintf('%0.2f mg/g', $Result['cbd']);

			break;

		case 'waste/waste':

			break;

		default:
			_exit_text("Not Handled: '$pt' {$Result['cannabinoid_d9_thc_percent']} / {$Result['cannabinoid_d9_thc_mg_g']} [CRS#187]", 500);
		}


		$ret = array(
			'Sample' => $Sample,
			'Result' => $Result,
			'Product' => $Product,
			'Strain' => $Strain,
		);
		//var_dump($ret);

		$QAR = new \App\Lab_Result($Result['id']);
		if (empty($QAR['id'])) {
			$rec = [];
			$rec['id'] = $Result['id'];
			$rec['license_id'] = $License_Lab['id'];
			$rec['created_at'] = $Result['created_at'];
			$rec['name'] = $Result['id'];
			$rec['flag'] = \App\Lab_Result::FLAG_SYNC;
			$rec['type'] = $Product['type_nice'];
			$rec['meta'] = json_encode($ret);
			$this->_container->DB->insert('lab_result', $rec);
			$QAR = new \App\Lab_Result($rec['id']);
		} else {
			//$QAR['license_id'] = $License_Lab['id'];
			$QAR['flag'] = intval($QAR['flag'] | \App\Lab_Result::FLAG_SYNC);
			$QAR['meta'] = json_encode($ret);
			$QAR['created_at'] = $Result['created_at'];
			$QAR->save();
		}

		$QAR->tryCOAImport();

		// _ksort_r($ret);
		// _exit_text($ret);

		if (strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
			return $RES->withStatus(204);
		}

		return $RES->withRedirect('/result/' . $QAR['id']);

	}

	protected function _sync_one($obj)
	{
		$dbc = $this->_container->DB;

		$chk = $dbc->fetchOne('SELECT id FROM lab_result WHERE id = ?', array($obj['guid']));
		if (empty($chk)) {
			// Add with current company as owner
			$dbc->insert('lab_result', array(
				'id' => $obj['guid'],
				'license_id' => $_SESSION['License']['id'], // Should be Lab License?
				'created_at' => $obj['_source']['created_at'],
				'name' => $obj['guid'],
				'type' => '-',
				'meta' => json_encode(array('Result' => $obj['_source'])),
			));

			// Sample Item
			// $LR = new \App\Lab_Result($rec['guid']);
			// $LR->tryCOAImport();

		}

		// Labs get this additional attribute, which is a big-data object
		// if (!empty($rec['_source']['for_inventory'])) {
		//
		// 	$S0 = $rec['_source']['for_inventory'];
		//
		// 	// If the Current License "Owns" the sample do one thing
		// 	if ($S0['global_mme_id'] == $_SESSION['License']['guid']) {
		//
		// 		$chk = $dbc->fetchOne('SELECT id FROM lab_sample WHERE license_id = :l0 AND id = :ls0', array(
		// 			':l0' => $_SESSION['License']['id'],
		// 			':ls0' => $S0['global_id']
		// 		));
		//
		// 		// Insert if not found
		// 		if (empty($chk['id'])) {
		// 			// Add to Table, With Me
		// 			$arg = [
		// 				'id' => $S0['global_id'],
		// 				// 'company_id' => $_SESSION['Company']['id'],
		// 				'license_id' => $_SESSION['License']['id'],
		// 				'name' => $S0['inventory_type_name'],
		// 				'meta' => json_encode($S0),
		// 			];
		// 			try {
		// 				$dbc->insert('lab_sample', $arg);
		// 			} catch (Exception $e) {
		// 				var_dump($arg);
		// 				echo $e->getMessage();
		// 				exit;
		// 			}
		// 		}
		// 	} else {
		//
		// 		// What Now?
		// 		$L1 = \OpenTHC\License::findByGUID($S0['global_mme_id']);
		// 		if (empty($L1['id'])) {
		// 			throw new Exception('Invalid L1');
		// 		}
		// 		$chk = $dbc->fetchOne('SELECT id FROM lab_sample WHERE license_id = :l0 AND id = :ls0', array(
		// 			':l0' => $L1['id'],
		// 			':ls0' => $S0['global_id']
		// 		));
		//
		// 		// Insert if not found
		// 		if (empty($chk['id'])) {
		// 			// Add to Table, With Me
		// 			$arg = [
		// 				'id' => $S0['global_id'],
		// 				// 'company_id' => $L1['company_id'],
		// 				'license_id' => $L1['id'],
		// 				'name' => $S0['inventory_type_name'],
		// 				'meta' => json_encode($S0),
		// 			];
		//
		// 			try {
		// 				$dbc->insert('lab_sample', $arg);
		// 			} catch (\Exception $e) {
		// 				//var_dump($arg);
		// 				//echo $e->getMessage();
		// 				//exit;
		// 			}
		// 		}
		// 	}
		// }

		// Link Lab Sample to this License
		$arg = array(
			':l0' => $_SESSION['License']['id'],
			':lr' => $obj['guid']
		);
		$sql = 'SELECT * FROM lab_result_license WHERE lab_result_id = :lr AND license_id = :l0';
		$chk = $dbc->fetchRow($sql, $arg);
		if (empty($chk)) {
			$sql = 'INSERT INTO lab_result_license (lab_result_id, license_id) values (:lr, :l0)';
			$dbc->query($sql, $arg);
		}

	}

	/**
	 * Determine Product Type
	 */
	protected function _product_type($P)
	{
		if (empty($P['type'])) {
			return '- Unknown -';
		}

		$pt = sprintf('%s/%s', $P['type'], $P['intermediate_type']);

		$PT = new \App\Product_Type();
		return $PT->_map($pt);
		// if (!empty($PT['name'])) {
		// 	return $PT['name'];
		// }
		//
		// throw new Exception('Invalid Product Type');
	}

	/**
	 * Determine Result Type
	 */
	protected function _result_type($R)
	{
		if (preg_match('/^WAATTESTED/', $R['id'])) {
			return '-leafdata-fix-';
		}

		$rt = sprintf('%s/%s/%s', $R['batch_type'], $R['type'], $R['intermediate_type']);
		$rt = trim($rt,'/ ');
		$rt = preg_replace('/^intermediate\/ end product/', null, $rt);
		$rt = trim($rt,'/ ');

		switch ($rt) {
		case 'extraction/end_product/usable_marijuana':
		case 'extraction/harvest_materials/flower_lots':
		case 'extraction/intermediate_product/flower':
		case 'extraction/marijuana':
		case 'harvest/end_product':
		case 'harvest/harvest_materials':
		case 'harvest/harvest_materials/flower':
		case 'harvest/intermediate_product':
		case 'harvest/intermediate_product/flower':
		case 'harvest/marijuana':
		case 'harvest_materials/flower_lots':
		case 'harvest/harvest_materials/flower_lots':
		case 'end_product':
		case 'end_product/usable_marijuana':
		case 'harvest_materials/flower_lots':
		case 'harvest_materials':
		case 'intermediate_product':
		case 'intermediate_product/flower':
		case 'marijuana':
		case 'plant/marijuana':
		case 'propagation material/marijuana':
		case 'propagation material/intermediate_product/flower':
		case 'marijuana':

////////
		// // Product Based Type
		// case 'end_product/concentrate_for_inhalation':
		// 	case 'end_product/infused_mix':
		// 	case 'end_product/packaged_marijuana_mix':
		// 	case 'end_product/usable_marijuana':
		// 	case 'harvest_materials/flower':
		// 	case 'harvest_materials/flower_lots':
		// 	case 'harvest_materials/other_material':
		// 	case 'harvest_materials/other_material_lots':
		// 	case 'intermediate_product/hydrocarbon_concentrate':
		// 	case 'intermediate_product/infused_cooking_medium':
		// 	case 'intermediate_product/ethanol_concentrate':
		// 	case 'intermediate_product/marijuana_mix':
		// 	// Result Based Type, these are all kinds of fucked up data from LD
		// 	case 'harvest/harvest_materials':
		// 	case 'harvest/harvest_materials/flower':
		// 	case 'harvest/intermediate_product/flower':
		// 	case 'harvest/marijuana':
		// 	case 'extraction/marijuana':
		// 	case 'extraction/harvest_materials/flower_lots':
		// 	case 'extraction/end_product/usable_marijuana':
		// 	case 'extraction/intermediate_product/flower':
		// 	case 'extraction/intermediate_product/marijuana_mix':
		// 	// Wacky New Shit from v1.37.5
		// 	case 'harvest/intermediate_product/marijuana_mix':
		// 	case 'end_product':
		// 	case 'end_product/infused_mix':
		// 	case 'harvest_materials':
		// 	case 'harvest_materials/flower_lots':
		// 	case 'intermediate_product':
		// 	case 'intermediate_product/ethanol_concentrate':
		// 	case 'intermediate_product/flower':
		// 	case 'marijuana':
		// 	case 'plant/marijuana':
		// 	case 'propagation material/intermediate_product/flower':
////////
			return 'Flower';
			break;
		case 'extraction/intermediate_product/marijuana_mix':
		case 'harvest/intermediate_product/marijuana_mix':
		case 'intermediate_product/marijuana_mix':
		case 'end_product/infused_mix':
			return 'Mix';
		 // Attested Stuff
		// case 'end_product/':
		// case 'harvest_materials/':
		// 	return '-leafdata-fix-';
		case 'end_product/concentrate_for_inhalation':
		case 'intermediate_product/ethanol_concentrate':
		case 'intermediate_product/food_grade_solvent_concentrate':
		case 'intermediate_product/hydrocarbon_concentrate':
		case 'intermediate_product/ethanol_concentrate':
			return 'Concentrate';
		case 'end_product/liquid_edible':
		case 'end_product/solid_edible':
			return 'Edible';
		case 'end_product/tinctures':
			return 'Tincture';
		case 'end_product/topical':
			return 'Topical';
		// case 'plant/end_product':
		// 	return 'Plant?';
		default:
			_exit_text("Invalid Result Type: '$rt' [CRS#282]", 500);
		}
	}

	protected function _laboratory()
	{
//		$meta = $this->_Inventory['meta'];
//		if (is_string($meta)) {
//			$meta = json_decode($meta, true);
//		}
//
//		// FOIA based
//		$x = $meta['lab_license'];
//		if (!empty($x)) {
//			$this->_Laboratory['id'] = $x;
//			$this->_Laboratory['name'] = '';
//		}
//
//		// Internal Based
//		$x = $meta['lab'];
//		if (is_array($x)) {
//			$this->_Laboratory['id'] = $x['id'];
//			$this->_Laboratory['name'] = $x['name'];
//		}
//
//		// Load Lab Data
//		//$res = HTTP::get('https://directory.openthc.com/api/search?type=Laboratory&license=' . $this->Inventory['lab_license']);
//		//var_dump($res);
//		//$res = json_decode($res['body'], true);
//		//$this->Laboratory = $res['result'];
//		//$this->Laboratory['id'] = $Inv['lab_license'];
	}

}
