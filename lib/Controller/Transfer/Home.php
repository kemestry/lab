<?php
/**
 * Search and Import Transfers
 */

namespace App\Controller\Transfer;

use Edoceo\Radix\DB\SQL;

class Home extends \OpenTHC\Controller\Base
{
	function __invoke($REQ, $RES, $ARG)
	{
		$dbc = $this->_container->DB;

		// Load Transfer Stats
		$transfer_stat = [
			'100' => 0,
			'301' => 0,
			'307' => 0,
			'410' => 0,
		];
		$sql = 'SELECT count(id) AS c, stat FROM b2b_incoming WHERE license_id_target = :l GROUP BY stat ORDER BY stat';
		$arg = array(':l' => $_SESSION['License']['id']);
		$res = $dbc->fetchAll($sql, $arg);
		foreach ($res as $rec) {
			$transfer_stat[ $rec['stat'] ] = $rec['c'];
		}

		// Filter
		if (empty($_GET['stat'])) {
			$_GET['stat'] = 301;
		} elseif ('*' == $_GET['stat']) {
			unset($_GET['stat']);
		}

		// Query
		$sql = 'SELECT * FROM b2b_incoming WHERE license_id_target = :l';
		$arg = array(':l' => $_SESSION['License']['id']);

		if (!empty($_GET['stat'])) {
			$sql .= ' AND stat = :s0';
			$arg[':s0'] = $_GET['stat'];
		}

		$sql.= ' ORDER BY created_at DESC';

		$res = $dbc->fetchAll($sql, $arg);
		foreach ($res as $rec) {

			$rec['meta'] = json_decode($rec['meta'], true);
			$rec['date'] = strftime('%m/%d', strtotime($rec['meta']['created_at']));

			$rec['license_target'] = new \OpenTHC\License($dbc, $rec['license_id_target']);
			$rec['license_source'] = new \OpenTHC\License($dbc, $rec['license_id_source']);

			$transfer_list[] = $rec;
		}

		$data = array(
			'Page' => array('title' => 'Transfers'),
			'GET' => $_GET,
			'transfer_stat' => $transfer_stat,
			'transfer_list' => $transfer_list,
		);

		return $this->_container->view->render($RES, 'page/transfer/home.html', $data);

	}

}
