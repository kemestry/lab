<?php
/**
 * Show the Active Inventory Samples
 */

namespace App\Controller\Sample;

use Edoceo\Radix\DB\SQL;

class Sync extends \OpenTHC\Controller\Base
{
	/**
	 * Do the SYNC
	 * @return [type] [description]
	 */
	function __invoke($REQ, $RES, $ARG)
	{
		if (!empty($_GET['a'])) {
			if ('force' == $_GET['a']) {
				unset($_SESSION['sync-sample-time']);
			}
		}
		if (!empty($_SESSION['sync-sample-time'])) {
			$span = $_SERVER['REQUEST_TIME'] - $_SESSION['sync-sample-time'];
			if ($span <= 900) {
				return $RES->withRedirect('/sample');
				return $RES->withJSON(array(
					'status' => 'success',
					'result' => 0,
				));
			}
		}

		$_SESSION['sync-sample-time'] = $_SERVER['REQUEST_TIME'];

		session_write_close();

		// Only want to get the QA Sample Lots -- so maybe, only Lots when we are a Laboratory?
		$cre = new \OpenTHC\RCE($_SESSION['pipe-token']);
		$res = $cre->get('/lot?source=true');

		if ('success' != $res['status']) {
			return $RES->withJSON(array(
				'status' => 'failure',
				'detail' => $cre->formatError($res)
			));

		}

		// Import Lots
		foreach ($res['result'] as $rec) {

			$Lot = $rec['_source'];

			$chk = SQL::fetch_one('SELECT id FROM qa_sample WHERE license_ulid = ? AND guid = ?', array($_SESSION['License']['ulid'], $rec['guid']));
			if (empty($chk)) {

				$x = $cre->get('/config/product/' . $Lot['global_inventory_type_id']);
				$Product = $x['result'];

				$x = $cre->get('/config/strain/' . $Lot['global_strain_id']);
				$Strain = $x['result'];

				SQL::insert('qa_sample', array(
					'company_id' => $_SESSION['gid'],
					'company_ulid' => $_SESSION['Company']['ulid'],
					'license_ulid' => $_SESSION['License']['ulid'],
					'guid' => $rec['guid'],
					'meta' => json_encode(array(
						'Lot' => $Lot,
						'Product' => $Product,
						'Strain' => $Strain,
					)),
				));
//			} else {

				// $meta = json_encode(array(
				// 	'Lot' => $Lot,
				// 	'Product' => $Product,
				// 	'Strain' => $Strain,
				// ));
				// $sql = 'UPDATE qa_sample SET meta = :m WHERE id = :pk';
				// $arg = array(':pk' => $chk, ':m' => $meta);
				// SQL::query($sql, $arg);

			}
		}

		return $RES->withRedirect('/sample');

		return $RES->withJSON(array(
			'status' => 'success',
		));

	}

}
