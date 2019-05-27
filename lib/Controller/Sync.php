<?php
/**
 * Sync All the Things
 */

namespace App\Controller;

use Edoceo\Radix\DB\SQL;

class Sync extends \OpenTHC\Controller\Base
{
	function __invoke($REQ, $RES, $ARG)
	{
		$data = array(
			'Page' => array('title' => 'QA :: Sync'),
			'r' => $_GET['r'],
		);

		return $this->_container->view->render($RES, 'page/sync.html', $data);

	}


	function exec($REQ, $RES, $ARG)
	{
		session_write_close();

		// Lab Results

		$cre = new \OpenTHC\RCE($_SESSION['pipe-token']);
		$res = $cre->get('/qa?source=true');

		if ('success' != $res['status']) {

			$RES->withJSON(array(
				'status' => 'failure',
				'result' => $cre->formatError($res),
			), 500);
			//var_dump($_SESSION);
			//die(var_dump($res));
		}

		foreach ($res['result'] as $rec) {

			$chk = SQL::fetch_one('SELECT id FROM qa_result WHERE id = ?', array($rec['guid']));
			if (empty($chk)) {
				// Add with current company as owner
				SQL::insert('qa_result', array(
					'id' => $rec['guid'],
					'company_id' => $_SESSION['gid'],
					// 'license_id' =>
					'created_at' => $rec['_source']['created_at'],
					'guid' => $rec['guid'],
					'name' => $rec['guid'],
					'meta_result_rbe' => json_encode($rec['_source'])
				));
			}

			// Link QA to this Company
			$sql = 'SELECT * FROM qa_result_company WHERE lab_result_id = ? AND company_id = ?';
			$arg = array($rec['guid'], $_SESSION['gid']);
			$chk = SQL::fetch_row($sql, $arg);
			if (empty($chk)) {
				$sql = 'INSERT INTO qa_result_company (lab_result_id, company_id) values (?, ?)';
				$arg = array($rec['guid'], $_SESSION['gid']);
				SQL::query($sql, $arg);
			}

		}

		// $r = $_GET['r'];
		// if (empty($r)) {
		// 	$r = '/home';
		// }

		$C = new \OpenTHC\Company($_SESSION['Company']);
		$C->setOption('sync-qa-time', $_SERVER['REQUEST_TIME']);

		$RES = $RES->withJSON(array(
			'status' => 'success',
			'result' => $r,
		));

		return $RES;

	}
}
