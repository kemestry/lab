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
		$_GET['a'] = 'force';
		if (!empty($_GET['a'])) {
			if ('force' == $_GET['a']) {
				unset($_SESSION['sync-sample-time']);
			}
		}
		if (!empty($_SESSION['sync-sample-time'])) {
			$span = $_SERVER['REQUEST_TIME'] - $_SESSION['sync-sample-time'];
			if ($span <= 900) {
				return $RES->withJSON(array(
					'status' => 'success',
					'detail' => 'Cached',
					'result' => 0,
				));
			}
		}

		$_SESSION['sync-sample-time'] = $_SERVER['REQUEST_TIME'];

		session_write_close();

		$c_ins = 0;
		$c_upd = 0;

		// Only want to get the QA Sample Lots -- so maybe, only Lots when we are a Laboratory?
		$cre = new \OpenTHC\RCE($_SESSION['pipe-token']);
		$res = $cre->get('/lot?source=true');

		if ('success' != $res['status']) {
			return $RES->withJSON(array(
				'status' => 'failure',
				'detail' => $cre->formatError($res)
			));

		}

		// Import Lots (only once!)
		foreach ($res['result'] as $rec) {

			$Lot = $rec['_source'];

			$chk = SQL::fetch_row('SELECT id, meta FROM lab_sample WHERE license_id = ? AND id = ?', array($_SESSION['License']['id'], $rec['guid']));
			if (empty($chk['id'])) {

				$x = $cre->get('/config/product/' . $Lot['global_inventory_type_id']);
				$Product = $x['result'];

				$Strain = null;
				if (!empty($Lot['global_strain_id'])) {
					$x = $cre->get('/config/strain/' . $Lot['global_strain_id']);
					$Strain = $x['result'];
				}

				$add = array(
					'id' => $rec['guid'],
					'company_id' => $_SESSION['gid'],
					'company_ulid' => $_SESSION['Company']['ulid'],
					'license_id' => $_SESSION['License']['id'],
					'meta' => json_encode(array(
						'Lot' => $Lot,
						'Product' => $Product,
						'Strain' => $Strain,
					)),
				);

				SQL::insert('lab_sample', $add);
				$c_ins++;

			} else {


				// Update the Meta
				$m = json_decode($chk['meta'], true);
				$m['Lot'] = $Lot;

				SQL::query('UPDATE lab_sample SET meta = :m0 WHERE id = :pk', array(
					':pk' => $chk['id'],
					':m0' => \json_encode($m)
				));
				$c_upd++;

			}
		}

		//return $RES->withRedirect('/sample');

		return $RES->withJSON(array(
			'status' => 'success',
			'detail' => sprintf('%d Insert, %d Update', $c_ins, $c_upd),
		));

	}

}
