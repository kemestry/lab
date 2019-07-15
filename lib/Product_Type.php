<?php
/**
 * Product Type Helper
 */

namespace App;

class Product_Type
{


	function _map($s)
	{
		$pt = trim($pt);

		$ret = array(
			'uom' => '',
			'name' => '',
		);

		case 'end_product/usable_marijuana':
		case 'extraction/marijuana':
		case 'harvest_materials/flower':
		case 'harvest_materials/flower_lots':
		case 'plant/marijuana':
			$ret['name'] = 'Flower';
			break;
		case 'harvest_materials/other_material':
		case 'harvest_materials/other_material_lots':
			$ret['name'] = 'Trim';
			break;
		case 'end_product/capsules':
			$ret['name'] = 'Capsules';
			break;
		case 'intermediate_product/marijuana_mix':
		case 'end_product/packaged_marijuana_mix':
			$ret['name'] = 'Flower/Mix';
			break;
		case 'end_product/infused_mix':
			$ret['name'] = 'Mix/Infused';
			break;
		case 'intermediate_product/co2_concentrate':
		case 'intermediate_product/hydrocarbon_concentrate':
		case 'intermediate_product/ethanol_concentrate':
		case 'intermediate_product/food_grade_solvent_concentrate':
		case 'intermediate_product/infused_cooking_medium':
		case 'intermediate_product/non-solvent_based_concentrate':
		case 'end_product/concentrate_for_inhalation':
			$ret['name'] = 'Concentrate';
			break;
		case 'end_product/solid_edible':
			$ret['name'] = 'Edible';
			break;
		case 'end_product/tinctures':
			$ret['name'] = 'Tincture';
			break;
		case 'end_product/topical':
			$ret['name'] = 'Topical';
			break;
		default:
			_exit_text("Product Type Unknown: '$pt' [LPT#046]", 500);
		}

	}

}
