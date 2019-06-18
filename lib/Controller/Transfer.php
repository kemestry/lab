<?php
/**
 * Search and Import Transfers
 */

namespace App\Controller;

use Edoceo\Radix\DB\SQL;

class Transfer extends \OpenTHC\Controller\Base
{
	function __invoke($REQ, $RES, $ARG)
	{
		// Load Transfer Data
		$sql = 'SELECT * FROM transfer_incoming WHERE license_id_target = :l ORDER BY created_at DESC';
		$arg = array(':l' => $_SESSION['License']['id']);
		$res = SQL::fetch_all($sql, $arg);
		foreach ($res as $rec) {
			$rec['meta'] = json_decode($rec['meta'], true);
			$rec['date'] = strftime('%m/%d', strtotime($rec['meta']['created_at']));

			$rec['origin_license'] = new \OpenTHC\License($rec['license_id_origin']);
			$rec['target_license'] = new \OpenTHC\License($rec['license_id_target']);

			$transfer_list[] = $rec;
		}
		// phpinfo();die;
		$data = array(
			'Page' => array('title' => 'Transfers'),
			'transfer_list' => $transfer_list,
		);

		// _exit_text($_SESSION);
		return $this->_container->view->render($RES, 'page/transfer/index.html', $data);

	}

}
