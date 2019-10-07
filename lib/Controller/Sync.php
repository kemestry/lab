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
		header('content-type: text/plain');

		session_write_close();

		$dbc = $this->_container->DB;

		// Lab Results
		// var_dump($_SESSION); exit;
		$cre = new \OpenTHC\CRE($_SESSION['pipe-token']);
		$res = $cre->get('/lab?source=true');
		// var_dump($res); exit;
		if ('success' != $res['status']) {

			return $RES->withJSON(array(
				'status' => 'failure',
				'result' => $cre->formatError($res),
			), 500);

		}

		foreach ($res['result'] as $rec) {

			// _exit_text($rec);
			echo $rec['guid'];
			echo '<br>';

			$chk = $dbc->fetchOne('SELECT id FROM lab_result WHERE id = ?', array($rec['guid']));

			if (empty($chk)) {

				// Add with current company as owner
				$dbc->insert('lab_result', array(
					'id' => $rec['guid'],
					'license_id' => $_SESSION['License']['id'], // Should be Lab License?
					'created_at' => $rec['_source']['created_at'],
					'name' => $rec['guid'],
					'type' => '-',
					'meta' => json_encode(array('Result' => $rec['_source'])),
				));

				// Sample Item
				// $LR = new \App\Lab_Result($rec['guid']);
				// $LR->tryCOAImport();

			}

			// Labs get this additional attribute, which is a big-data object
			// if (!empty($rec['_source']['for_inventory'])) {
			//
			// 	$S0 = $rec['_source']['for_inventory'];
			//
			// 	// If the Current License "Owns" the sample do one thing
			// 	if ($S0['global_mme_id'] == $_SESSION['License']['guid']) {
			//
			// 		$chk = $dbc->fetchOne('SELECT id FROM lab_sample WHERE license_id = :l0 AND id = :ls0', array(
			// 			':l0' => $_SESSION['License']['id'],
			// 			':ls0' => $S0['global_id']
			// 		));
			//
			// 		// Insert if not found
			// 		if (empty($chk['id'])) {
			// 			// Add to Table, With Me
			// 			$arg = [
			// 				'id' => $S0['global_id'],
			// 				// 'company_id' => $_SESSION['Company']['id'],
			// 				'license_id' => $_SESSION['License']['id'],
			// 				'name' => $S0['inventory_type_name'],
			// 				'meta' => json_encode($S0),
			// 			];
			// 			try {
			// 				$dbc->insert('lab_sample', $arg);
			// 			} catch (Exception $e) {
			// 				var_dump($arg);
			// 				echo $e->getMessage();
			// 				exit;
			// 			}
			// 		}
			// 	} else {
			//
			// 		// What Now?
			// 		$L1 = \OpenTHC\License::findByGUID($S0['global_mme_id']);
			// 		if (empty($L1['id'])) {
			// 			throw new Exception('Invalid L1');
			// 		}
			// 		$chk = $dbc->fetchOne('SELECT id FROM lab_sample WHERE license_id = :l0 AND id = :ls0', array(
			// 			':l0' => $L1['id'],
			// 			':ls0' => $S0['global_id']
			// 		));
			//
			// 		// Insert if not found
			// 		if (empty($chk['id'])) {
			// 			// Add to Table, With Me
			// 			$arg = [
			// 				'id' => $S0['global_id'],
			// 				// 'company_id' => $L1['company_id'],
			// 				'license_id' => $L1['id'],
			// 				'name' => $S0['inventory_type_name'],
			// 				'meta' => json_encode($S0),
			// 			];
			//
			// 			try {
			// 				$dbc->insert('lab_sample', $arg);
			// 			} catch (\Exception $e) {
			// 				//var_dump($arg);
			// 				//echo $e->getMessage();
			// 				//exit;
			// 			}
			// 		}
			// 	}
			// }

			// Link Lab Sample to this License
			$arg = array(
				':l0' => $_SESSION['License']['id'],
				':lr' => $rec['guid']
			);
			$sql = 'SELECT * FROM lab_result_license WHERE lab_result_id = :lr AND license_id = :l0';
			$chk = $dbc->fetchRow($sql, $arg);
			if (empty($chk)) {
				$sql = 'INSERT INTO lab_result_license (lab_result_id, license_id) values (:lr, :l0)';
				$dbc->query($sql, $arg);
			}

		}

		$C = new \OpenTHC\Company($_SESSION['Company']);
		$C->setOption('sync-qa-time', $_SERVER['REQUEST_TIME']);

		$RES = $RES->withJSON(array(
			'status' => 'success',
		));

		return $RES;

	}
}
