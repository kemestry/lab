<?php
/**
 * Sync One QA Result
*/

namespace App\Controller\Result;

use Edoceo\Radix\DB\SQL;
use Edoceo\Radix\Net\HTTP;

class Sync extends \OpenTHC\Controller\Base
{
	function __invoke($REQ, $RES, $ARG)
	{
		session_write_close();

		if (empty($_SESSION['pipe-token'])) {
			return $RES->withStatus(403);
		}

		if (empty($ARG['id'])) {
			return $RES->withStatus(400);
		}

		$cre = new \OpenTHC\RCE($_SESSION['pipe-token']);

		$res = $cre->get('/qa/' . $ARG['id']);
		if (empty($res)) {
			_exit_text('Cannot Load QA from CRE [CRS#029]', 500);
		}
		if ('success' != $res['status']) {
			_exit_text($cre->formatError($res), 200);
		}

		$Result = $res['result'];
		$Result['id'] = $Result['global_id'];
		$Result['guid'] = $Result['global_id'];
		$Result['type_nice'] = $this->_result_type($Result);

		// Find the Lab that Owns It
		$License_Lab = \OpenTHC\License::findByGUID($Result['global_mme_id']);
		if (empty($License_Lab['id'])) {
			_exit_text("Cannot find: '{$Result['global_from_mme_id']}'", 404);
		}
		// @todo Need to Verify that it's a LAB type license.

		//_exit_text(print_r($Result, true));

		// This is the ID as the Lab Inventory Lot
		$sql = 'SELECT * FROM qa_sample WHERE company_id = :c AND id = :g';
		$arg = array(
			':c' => $_SESSION['id'],
			':g' => $Result['global_inventory_id'],
		);
		//$res = $cre->get('/lot/' . $Result['global_inventory_id']);

		// Sample Details
		$Sample = array(
			'id' => $Result['global_for_inventory_id'],
			'name' => '- Not Found -',
			// 'type' => '',
			'type_nice' => '-None-',
		);
		$res = $cre->get('/lot/' . $Sample['id']);
		if ('success' == $res['status']) {
			$Sample = array_merge($res['result'], $Sample);
			// if (!empty($Sample['global_id'])) {
			// 	$Sample['guid'] = $Sample['global_id'];
			// }
		}

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
		$pt = sprintf('%s/%s/%s', $Result['batch_type'], $Result['type'], $Result['intermediate_type']);
		if (!empty($Product['type']) && !empty($Product['intermediate_type'])) {
			$pt = sprintf('%s/%s', $Product['type'], $Product['intermediate_type']);
		}
		switch ($pt) {
		// Product Based Type
		case 'end_product/concentrate_for_inhalation':
		case 'end_product/infused_mix':
		case 'end_product/packaged_marijuana_mix':
		case 'end_product/usable_marijuana':
		case 'harvest_materials/flower':
		case 'harvest_materials/flower_lots':
		case 'harvest_materials/other_material':
		case 'harvest_materials/other_material_lots':
		case 'intermediate_product/hydrocarbon_concentrate':
		case 'intermediate_product/infused_cooking_medium':
		case 'intermediate_product/ethanol_concentrate':
		case 'intermediate_product/marijuana_mix':
		// Result Based Type, these are all kinds of fucked up data from LD
		case 'harvest/marijuana/':
		case 'extraction/marijuana/':
		case 'extraction/harvest_materials/flower_lots':
		case 'extraction/end_product/usable_marijuana':
		case 'extraction/intermediate_product/flower':
		case 'extraction/intermediate_product/marijuana_mix':

			// PCT
			$Result['uom'] = 'pct';
			$Result['thc'] = $Result['cannabinoid_d9_thc_percent'] + ($Result['cannabinoid_d9_thca_percent'] * 0.877);
			$Result['cbd'] = $Result['cannabinoid_cbd_percent'] + ($Result['cannabinoid_cbda_percent'] * 0.877);
			$Result['sum'] = sprintf('%0.2f%%', $Result['thc'] + $Result['cbd']);
			$Result['thc'] = sprintf('%0.2f%%', $Result['thc']);
			$Result['cbd'] = sprintf('%0.2f%%', $Result['cbd']);

			break;

		case 'intermediate_product/co2_concentrate':
		case 'intermediate_product/food_grade_solvent_concentrate':
		case 'intermediate_product/infused_cooking_medium':
		case 'intermediate_product/non-solvent_based_concentrate':
		case 'end_product/capsules':
		case 'end_product/liquid_edible':
		case 'end_product/solid_edible':
		case 'end_product/suppository':
		case 'end_product/tinctures':
		case 'end_product/topical':
		case 'end_product/transdermal_patches':

			// The State says to enter these as mg/g values but some labs enter them as percent :(
			$Result['uom'] = 'mgg';
			$Result['thc'] = $Result['cannabinoid_d9_thc_mg_g'] + ($Result['cannabinoid_d9_thca_mg_g'] * 0.877);
			$Result['cbd'] = $Result['cannabinoid_cbd_mg_g'] + ($Result['cannabinoid_cbda_mg_g'] * 0.877);
			$Result['sum'] = sprintf('%0.2f mg/g', $Result['thc'] + $Result['cbd']);
			$Result['thc'] = sprintf('%0.2f mg/g', $Result['thc']);
			$Result['cbd'] = sprintf('%0.2f mg/g', $Result['cbd']);

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
		var_dump($ret);

		$QAR = new \App\QA_Result($Result['id']);
		$QAR['license_id'] = $License_Lab['id'];
		$QAR['license_id_lab'] = $License_Lab['id'];
		$QAR['flag'] = intval($QAR['flag'] | \App\QA_Result::FLAG_SYNC);
		$QAR['meta'] = json_encode($ret);
		$QAR['created_at'] = $Result['created_at'];
		$QAR->save();

		$this->tryCOAImport($QAR);

		//_ksort_r($ret);
		//_exit_text($ret);

		if (strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
			return $RES->withStatus(204);
		}

		var_dump($QAR);
		exit;

		return $RES->withRedirect('/result/' . $QAR['guid']);

	}

	/**
	 * Try to Import the COA from LeafData
	 * @param [type] $QAR [description]
	 * @return [type] [description]
	 */
	protected function tryCOAImport($QAR)
	{
		$tmp_file = _tmp_file();

		if (!empty($Result['pdf_path'])) {
			$res = HTTP::get($Result['pdf_path']);
			switch ($res['info']['http_code']) {
			case 200:
				file_put_contents($tmp_file, $res['body']);
				$QAR->setCOAFile($tmp_file);
				return true;
				break;
			default:
				_exit_text($res);
			}
		}

		return false;

	}

	protected function _product_type($P)
	{
		if (empty($P['type'])) {
			return '- Unknown -';
		}

		$pt = sprintf('%s/%s', $P['type'], $P['intermediate_type']);
		$pt = trim($pt);
		switch ($pt) {
		case 'end_product/usable_marijuana':
		case 'extraction/marijuana':
		case 'harvest_materials/flower':
		case 'harvest_materials/flower_lots':
		case 'plant/marijuana':
			return 'Flower';
		case 'harvest_materials/other_material':
			return 'Trim';
		case 'end_product/capsules':
			return 'Capsules';
		case 'intermediate_product/marijuana_mix':
		case 'end_product/packaged_marijuana_mix':
			return 'Flower/Mix';
		case 'end_product/infused_mix':
			return 'Mix/Infused';
		case 'intermediate_product/co2_concentrate':
		case 'intermediate_product/hydrocarbon_concentrate':
		case 'intermediate_product/ethanol_concentrate':
		case 'intermediate_product/food_grade_solvent_concentrate':
		case 'intermediate_product/infused_cooking_medium':
		case 'intermediate_product/non-solvent_based_concentrate':
		case 'end_product/concentrate_for_inhalation':
			return 'Concentrate';
		case 'end_product/solid_edible':
			return 'Edible';
		case 'end_product/tinctures':
			return 'Tincture';
		case 'end_product/topical':
			return 'Topical';
		default:
			_exit_text("Product Type Unknown: '$pt' [CRS#260]", 500);
		}
	}

	protected function _result_type($R)
	{
		$rt = sprintf('%s/%s/%s', $R['batch_type'], $R['type'], $R['intermediate_type']);
		$rt = trim($rt);
		switch ($rt) {
		case 'extraction/end_product/usable_marijuana':
		case 'extraction/harvest_materials/flower_lots':
		case 'extraction/intermediate_product/flower':
		case 'extraction/marijuana/':
		case 'harvest/marijuana/':
		case 'plant/marijuana/':
		case 'propagation material/marijuana/':
		case 'marijuana/':
			return 'Flower';
			break;
		case 'extraction/intermediate_product/marijuana_mix':
			return 'Mix';
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
//			$this->_Laboratory['guid'] = $x;
//			$this->_Laboratory['name'] = '';
//		}
//
//		// Internal Based
//		$x = $meta['lab'];
//		if (is_array($x)) {
//			$this->_Laboratory['guid'] = $x['guid'];
//			$this->_Laboratory['name'] = $x['name'];
//		}
//
//		// Load Lab Data
//		//$res = HTTP::get('https://directory.openthc.com/api/search?kind=QA&license=' . $this->Inventory['lab_license']);
//		////Radix::dump($res);
//		//$res = json_decode($res['body'], true);
//		//$this->Laboratory = $res['result'];
//		//$this->Laboratory['guid'] = $Inv['lab_license'];
	}

}
