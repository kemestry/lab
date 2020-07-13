<?php
/**
 * Lab Metric
 */

namespace App;

class Lab_Metric extends \OpenTHC\SQL\Record
{
	protected $_table = 'lab_metric';

	function findAll()
	{
		$sql = sprintf('SELECT * FROM "%s" ORDER BY code', $this->_table);
		$res = $this->_dbc->fetchAll($sql);
		return $res;
	}

}
