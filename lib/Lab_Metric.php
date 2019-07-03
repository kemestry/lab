<?php
/**
 * Lab Metric
*/

namespace App;

use Edoceo\Radix\DB\SQL;

class Lab_Metric extends \OpenTHC\SQL\Record
{
	protected $_table = 'lab_metric';

	static function find()
	{
		$metric_list = SQL::fetch_all('SELECT * FROM qa_metric ORDER BY type, name');
		return $metric_list;
	}
}
