<?php
/**
 * Sync All the Things
 */

namespace App\Controller;

class Sync extends \OpenTHC\Controller\Base
{
	function __invoke($REQ, $RES, $ARG)
	{
		if ($_GET['a'] == 'sync') {
			return $this->exec($REQ, $RES, $ARG);
		}

		$data = array(
			'Page' => array('title' => 'Sync'),
			'r' => $_GET['r'],
		);

		return $this->_container->view->render($RES, 'page/sync.html', $data);

	}

	function exec($REQ, $RES, $ARG)
	{
		session_write_close();

		$dbc = $this->_container->DB;

		// Lab Results

		$cre = new \OpenTHC\RCE($_SESSION['pipe-token']);
		$res = $cre->get('/lab?source=true');

		if ('success' != $res['status']) {

			$RES->withJSON(array(
				'status' => 'failure',
				'result' => $cre->formatError($res),
			), 500);
			//var_dump($_SESSION);
			//die(var_dump($res));
		}

		foreach ($res['result'] as $rec) {

			$chk = $dbc->fetchOne('SELECT id FROM lab_result WHERE id = ?', array($rec['guid']));
			if (empty($chk)) {
				echo '+';
				// Add with current company as owner
				$dbc->insert('lab_result', array(
					'id' => $rec['guid'],
					'license_id' => $_SESSION['License']['id'], //   $L_Lab['id'],
					'created_at' => $rec['_source']['created_at'],
					'name' => $rec['guid'],
					'type' => '-',
					'meta' => json_encode(array('Result' => $rec['_source'])),
				));
			}

			// Link QA to this Company
			$sql = 'SELECT * FROM lab_result_company WHERE lab_result_id = ? AND company_id = ?';
			$arg = array($rec['guid'], $_SESSION['Company']['id']);
			$chk = $dbc->fetchRow($sql, $arg);
			if (empty($chk)) {
				echo '|';
				$sql = 'INSERT INTO lab_result_company (lab_result_id, company_id) values (?, ?)';
				$arg = array($rec['guid'], $_SESSION['Company']['id']);
				$dbc->query($sql, $arg);
			}

		}

		$C = new \OpenTHC\Company($_SESSION['Company']);
		$C->setOption('sync-qa-time', $_SERVER['REQUEST_TIME']);

		$RES = $RES->withJSON(array(
			'status' => 'success',
			'result' => $res,
		));

		return $RES;

	}
}
