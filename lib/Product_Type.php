<?php
/**
 * Product Type Helper
 */

namespace App;

class Product_Type
{

	function _map($pt)
	{
		$pt = trim($pt);

		$ret = array(
			'uom' => '',
			'name' => '',
		);

		switch ($pt) {
		case 'end_product/usable_marijuana':
		case 'extraction/marijuana':
		case 'harvest_materials/flower':
		case 'harvest_materials/flower_lots':
		case 'plant/marijuana':
			$ret['uom'] = 'g';
			$ret['name'] = 'Flower';
			break;
		case 'harvest_materials/other_material':
		case 'harvest_materials/other_material_lots':
			$ret['uom'] = 'g';
			$ret['name'] = 'Trim';
			break;
		case 'end_product/capsules':
			$ret['uom'] = 'ea';
			$ret['name'] = 'Capsules';
			break;
		case 'intermediate_product/marijuana_mix':
		case 'end_product/packaged_marijuana_mix':
			$ret['uom'] = 'g';
			$ret['name'] = 'Flower/Mix';
			break;
		case 'end_product/infused_mix':
			$ret['uom'] = 'g';
			$ret['name'] = 'Mix/Infused';
			break;
		case 'intermediate_product/co2_concentrate':
		case 'intermediate_product/hydrocarbon_concentrate':
		case 'intermediate_product/ethanol_concentrate':
		case 'intermediate_product/food_grade_solvent_concentrate':
		case 'intermediate_product/infused_cooking_medium':
		case 'intermediate_product/non-solvent_based_concentrate':
			$ret['uom'] = 'g';
			$ret['name'] = 'Concentrate';
			break;
		case 'end_product/concentrate_for_inhalation':
			$ret['uom'] = 'ea';
			$ret['name'] = 'Concentrate';
			break;
		case 'end_product/liquid_edible':
		case 'end_product/solid_edible':
			$ret['uom'] = 'ea';
			$ret['name'] = 'Edible';
			break;
		case 'end_product/tinctures':
			$ret['uom'] = 'ea';
			$ret['name'] = 'Tincture';
			break;
		case 'end_product/topical':
			$ret['uom'] = 'ea';
			$ret['name'] = 'Topical';
			break;
		case 'waste/waste':
			$ret['name'] = 'Waste';
			break;
		default:
			_exit_text("Product Type Unknown: '$pt' [LPT#046]", 500);
		}

		return $ret['name'];

	}

}
