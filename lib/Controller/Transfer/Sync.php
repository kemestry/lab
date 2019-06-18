<?php
/**
 * Transfer Sync
*/

namespace App\Controller\Transfer;

use Edoceo\Radix\DB\SQL;
use DateInterval;
use DateTime;
use DateTimeZone;


class Sync extends \OpenTHC\Controller\Base
{
	private $_cre;

	function __invoke($REQ, $RES,$ARG)
	{
		\session_write_close();

		$this->_cre = new \OpenTHC\RCE($_SESSION['pipe-token']);
		if (empty($this->_cre)) {
			// Check State, give 500 error
		}

		if (!empty($ARG['id'])) {
			return $this->syncOne($REQ, $RES, $ARG);
		}

		return $this->syncAll($REQ, $RES, $ARG);

	}

	/**
	 * Sync All the Transfer Objects
	 * @param [type] $REQ [description]
	 * @param [type] $RES [description]
	 * @param [type] $ARG [description]
	 * @return [type] [description]
	 */
	function syncAll($REQ, $RES, $ARG)
	{
		$res = $this->_cre->transfer();

		$transfer_list = array();
		if (empty($res['result']) || !is_array($res['result'])) {
			var_dump($_SESSION);
			var_dump($res);
			die("Cannot Load Transfers\n");
		}

		$resOut = [];
		foreach ($res['result'] as $rec) {

			var_dump($_SESSION);
			$rec = array_merge($rec, $rec['_source']);
			unset($rec['_source']);

			var_dump($rec);
			$arg = array(':l' => $_SESSION['License']['id'], ':g' => $rec['guid']);
			// Select a incoming transfer by the Lab User's lic ID, and it's unique guid.
			$chk = SQL::fetch_one('SELECT id,hash FROM transfer_incoming WHERE license_id_target = :l AND id = :g', $arg);
			if (empty($chk)) {

				$LOrigin = \OpenTHC\License::findByGUID($rec['global_from_mme_id']);
				if (empty($LOrigin['id'])) {
					_exit_text("Cannot find: '{$rec['global_from_mme_id']}'", 404);
				}
				var_dump($LOrigin);

				$LTarget = \OpenTHC\License::findByGUID($rec['global_to_mme_id']);
				if (empty($LTarget['id'])) {
					_exit_text("Cannot find: '{$rec['global_to_mme_id']}'", 404);
				}
				if ($LTarget['id'] != $_SESSION['License']['id']) {
					_exit_text('License Mis-Match', 409);
				}
				var_dump($LTarget);

				$rec = array(
					'id' => $rec['guid'],
					'license_id_origin' => $LOrigin['id'],
					'license_id_target' => $LTarget['id'],
					'created_at' => $rec['created_at'],
					'hash' => $rec['hash'],
					'meta' => json_encode($rec)
				);
				SQL::insert('transfer_incoming', $rec);
				$resOut[] = $rec;

			} else {

				$upd = array(
					':id' => $rec['guid'],
					':h' => $rec['hash'],
					':m' => json_encode($rec)
				);

				$sql = 'UPDATE transfer_incoming SET hash = :h, meta = :m WHERE id = :id';
				var_dump($upd);

				$resOut[] = $upd;
				SQL::query($sql, $upd);
			}

		}

		// Only Open
		//$transfer_list = array_filter($transfer_list, function($x) {
		//	return (('open' == $x['status']) || ('in-transit' == $x['status']))
		//		&& (empty($x['deleted_at']) && empty($x['void']));
		//});

		// Remove Voided
		//$transfer_list = array_filter($transfer_list, function($x) {
		//	//echo "return ('1' != '{$x['void']}');\n";
		//	return (1 != $x['status_void']);
		//});

		//if (empty($transfer_list)) {
		//	$data = array();
		//	return $this->_container->view->render($RES, 'page/transfer/empty.html', $data);
		//}
		// if ($_SERVER['REQUEST_METHOD'] == "GET") {
		// 	_exit_text($resOut);
		// }


		return $RES->withRedirect('/transfer');

	}

	/**
	 * Sync One of the Transfer Objects
	 * @param [type] $REQ [description]
	 * @param [type] $RES [description]
	 * @param [type] $ARG [description]
	 * @return [type] [description]
	 */
	function syncOne($REQ, $RES, $ARG)
	{
		// Detect Transfer License vs Source License (mine)
		// So, we pull the right license when connected (may have to re-auth to switch license?)

		$RC = new \Redis();
		$RC->connect('127.0.0.1');

		// Load Transfer
		$sql = 'SELECT transfer_incoming.*,';
		$sql.= ' license.code AS license_code,';
		$sql.= ' license.name AS license_name';
		$sql.= ' FROM transfer_incoming';
		$sql.= ' JOIN license ON transfer_incoming.license_id_origin = license.id';
		$sql.= ' WHERE transfer_incoming.id = :g';
		$sql.= ' AND transfer_incoming.license_id_target = :l';
		$arg = array(':l' => $_SESSION['License']['id'], ':g' => $ARG['id']);
		$data['transfer'] = SQL::fetch_row($sql, $arg);

		$res = $this->_cre->get('/transfer/incoming/' . $data['transfer']['id']);
		// Load Transfer Items
		if ('success' != $res['status']) {
			//_exit_text($res);
			_exit_text('Failed to Load Transfer, Please Try Again', 500);
		}

		var_dump($res);
		exit;

		$T = $res['result'];

		if (!empty($T['void'])) {
			$data['transfer']['stat'] = 410;
		} else {
			switch ($T['status']) {
			case 'open':
				$data['transfer']['stat'] = 100;
				break;
			case 'ready-for-pickup':
				$data['transfer']['stat'] = 200;
				break;
			case 'in-transit':
				$data['transfer']['stat'] = 301;
				break;
			case 'received':
				$data['transfer']['stat'] = 307;
				break;
			}
		}

		// Cleanup for re-add
		$sql = 'DELETE FROM transfer_incoming_item WHERE transfer_id = :t';
		$arg = array($data['transfer']['id']);
		SQL::query($sql, $arg);

		$full_price = 0;
		foreach ($res['result']['inventory_transfer_items'] as $rec) {

			// Lookup Product
			$res = $this->_cre->get('/lot/' . $rec['global_inventory_id']);
			$Lot = $res['result'];

			// Product+Cache
			$Product = array();
			$x = $RC->get('/cache/' . $rec['global_inventory_type_id']);
			if (!empty($x)) {
				$Product = json_decode($x, true);
			}
			if (empty($Product['global_id'])) {
				$res = $this->_cre->get('/config/product/' . $rec['global_inventory_type_id']);
				$Product = $res['result'];
				$RC->set('/cache/' . $rec['global_inventory_type_id'], json_encode($Product), 3600);
			}

			$Strain = array();
			if (empty($rec['global_strain_id']) && empty($rec['strain_name'])) {

				$Strain = array(
					'name' => '- None -'
				);

				// Guess
				$x = $rec['description'];
				$x = str_replace($rec['inventory_name'], null, $x);
				$x = preg_replace('/ WA[\w\. ]+$/', null, $x);

				$Strain['name'] = trim($x);

			} else {
				$x = $RC->get('/cache/' . $rec['global_strain_id']);
				if (!empty($x)) {
					$Strain = \json_decode($x, true);
				}
				if (empty($Strain['global_id'])) {
					$res = $this->_cre->get('/config/strain/' . $rec['global_strain_id']);
					$Strain = $res['result'];
					$RC->set('/cache/' . $rec['global_strain_id'], json_encode($Strain), 3600);
				}

				if (empty($Strain['name'])) {
					$Strain['name'] = $rec['strain_name'];
				}
			}

			//$rec['product_type'] = _leafdata_product_type_nice($Product['type'], $Product['intermediate_type']);

			$add = array(
				'id' => $rec['global_id'],
				'transfer_id' => $data['transfer']['id'],
				'product' => $Product['name'],
				'strain' => $Strain['name'],
				'package_qty' => (200 == $data['transfer']['stat'] ? $rec['received_qty'] : $rec['qty']),
				'package_qom' => $Product['net_weight'],
				'package_uom' => $Product['uom'],
				'full_price' => $rec['price'],
				'meta' => array(
					'Item' => $rec,
					'Lot' => $Lot,
					'Product' => $Product,
					'Strain' => $Strain,
				)
			);
			// _exit_text($add);


			$full_price += floatval($rec['price']);

			$add['meta'] = json_encode($add['meta']);

			SQL::insert('transfer_incoming_item', $add);

		}

		$sql = 'SELECT count(id) FROM transfer_incoming_item WHERE transfer_id = :t';
		$arg = array(':t' => $data['transfer']['id']);
		$c0 = SQL::fetch_one($sql, $arg);

		$sql = "SELECT count(id) FROM transfer_incoming_item WHERE transfer_id = :t AND meta->'Item'->>'is_sample' = '1'";
		$arg = [':t' => $data['transfer']['id']];
		$c1 = SQL::fetch_one($sql, $arg);

		if (($c0 > 0) && ($c0 == $c1)) {
			$data['transfer']['flag'] = $data['transfer']['flag'] | \App\Transfer::FLAG_SAMPLE;
		}

		SQL::query('UPDATE transfer_incoming SET flag = flag | :f1,  stat = :s WHERE id = :t', array(
			':t' => $data['transfer']['id'],
			':f1' => ($data['transfer']['flag'] | \App\Transfer::FLAG_SYNC),
			':s' => $data['transfer']['stat'],
		));

		//_exit_text($data['transfer']);

		if (strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
			return $RES->withStatus(204);
		}

		return $RES->withRedirect('/transfer/' . $ARG['id']);
	}
}
