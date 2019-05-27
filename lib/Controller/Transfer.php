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
		$arg = array(':l' => $_SESSION['License']['ulid']);
		$res = SQL::fetch_all($sql, $arg);
		foreach ($res as $rec) {
			$rec['meta'] = json_decode($rec['meta'], true);
			$rec['date'] = strftime('%m/%d', strtotime($rec['meta']['created_at']));
			$rec['origin_license'] = SQL::fetch_row('SELECT * FROM license WHERE ulid = ?', array($rec['license_id_origin'])); // \OpenTHC\License::findBy(array('ulid' => $rec['license_id_origin']));
			$rec['target_license'] = SQL::fetch_row('SELECT * FROM license WHERE ulid = ?', array($rec['license_id_target'])); // new \OpenTHC\License($rec['license_id_target']);
			$transfer_list[] = $rec;
		}

		$data = array(
			'Page' => array('title' => 'Transfers'),
			'transfer_list' => $transfer_list,
		);

		return $this->_container->view->render($RES, 'page/transfer/index.html', $data);

	}

	function sync($REQ, $RES, $ARG)
	{
		session_write_close();

		$cre = new \OpenTHC\RCE($_SESSION['pipe-token']);
		$res = $cre->get('/transfer/incoming?source=true'); // transfer();

		$transfer_list = array();
		if (empty($res['result']) || !is_array($res['result'])) {
			var_dump($_SESSION);
			var_dump($res);
			die("Cannot Load Transfers\n");
		}

		foreach ($res['result'] as $rec) {

			$rec = array_merge($rec, $rec['_source']);
			unset($rec['_source']);

			$arg = array(':l' => $_SESSION['License']['ulid'], ':g' => $rec['guid']);
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
				if ($LTarget['ulid'] != $_SESSION['License']['ulid']) {
					_exit_text('License Mis-Match', 409);
				}
				var_dump($LTarget);

				$rec = array(
					'id' => $rec['guid'],
					'license_id_origin' => $LOrigin['ulid'],
					'license_id_target' => $LTarget['ulid'],
					'created_at' => $rec['created_at'],
					'hash' => $rec['hash'],
					'meta' => json_encode($rec)
				);
				SQL::insert('transfer_incoming', $rec);

			} else {

				$upd = array(
					':id' => $rec['guid'],
					':h' => $rec['hash'],
					':m' => json_encode($rec)
				);

				$sql = 'UPDATE transfer_incoming SET hash = :h, meta = :m WHERE id = :id';
				var_dump($upd);

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

		return $RES->withRedirect('/transfer');

	}

}
