<?php
/**
	QA Sample
*/

namespace App;

use Edoceo\Radix;
use Edoceo\Radix\DB\SQL;
use Edoceo\Radix\Net\HTTP;

use OpenTHC\Company;

class Lab_Sample extends \OpenTHC\SQL\Record
{
	const FLAG_ACTIVE = 0x00000001;
	const FLAG_RESULT = 0x00000002;

	const FLAG_PASSED = 0x00000010;
	const FLAG_FAILED = 0x00000020;
	const FLAG_REJECT = 0x00000040;

	const FLAG_FILE_IMAGE = 0x00000100;
	const FLAG_FILE_CERT = 0x00000200;
	const FLAG_FILE_DATA = 0x00000400;

	const FLAG_DEAD = 0x08000000;

	const STAT_OPEN = 100;
	const STAT_DONE = 200;
	const STAT_VOID = 410;

	protected $_table = 'lab_sample';

	public $_Inventory;
	public $_Company;
	public $_License;

	function __construct($x=null)
	{
		parent::__construct($x);

		// $sql = 'SELECT * FROM lab_sample WHERE guid = ?';
		// $arg = array($oid);
		// //Radix::dump($sql);
		// //Radix::dump($arg);
		// $res = SQL::fetch_row($sql, $arg);
        //
		// $this->_data = $res;

		$this->_Inventory = $this->_data;
		//$this->_Inventory['guid'] = $oid;

		// Radix::dump($this->_Inventory);

		// $this->_Inventory['meta'] = json_decode($res['meta'], true);

		// if (!empty($this->_Inventory['id'])) {
		// 	//$this->_Company = // From Main
		// 	//$this->_License = // From Main
		// }

		$this->_Company = array();
		$this->_License = array();

		//Radix::dump($arg);
		// if (!empty($this->_Inventory['id'])) {
		// 	$this->_inflate_inventory();
		// }
		// _find_lab_sample_in_biotrack_wa($this->_Inventory['guid']);

		if (!empty($this->_data['company_id'])) {
			$this->_Company = new Company($this->_data['company_id']);
		}

	}

	/**

	*/
	function getCompany()
	{
//		if (!empty($this->_Inventory['company_id'])) {
//			$x = $this->_Inventory['company_id'];
//			$res = HTTP::get('https://directory.openthc.com/api/search?id=' . $x);
//			switch ($res['info']['http_code']) {
//			case 200:
//				$res = json_decode($res['body'], true);
//				// print_r($res['result']);
//				$this->_Company['id'] = $res['result']['id'];
//				$this->_Company['guid'] = $res['result']['guid'];
//				$this->_Company['name'] = $res['result']['name'];
//				$this->_Company['link_profile'] = 'https://directory.openthc.com/profile?company=' . rawurlencode($this->_Company['guid']);
//				break;
//			}
//		}

		return $this->_Company;
	}

}
