<?php
/**
 * Return One Lab Result, Inflated
 */

namespace App\Controller\API\Result;

class Create extends \OpenTHC\Controller\Base
{
	function __invoke($REQ, $RES, $ARG)
	{
		$data = $this->_inputData();
		if (empty($data)) {
			return $RES->withJSON([
				'data' => null,
				'meta' => [ 'detail' => 'Request Error [ARC#016]' ],
			], 400);
		}

		if (empty($data['id'])) {
			return $RES->withJSON([
				'data' => null,
				'meta' => [ 'detail' => 'Request Error [ARC#023]' ],
			], 400);
		}
		if (empty($data['license_id'])) {
			return $RES->withJSON([
				'type' => '',
				'data' => null,
				'meta' => [ 'detail' => 'Request Error [ARC#029]' ],
			], 400);
		}

		$dbc = $this->_container->DB;

		// Check Data
		$chk = $dbc->fetchRow('SELECT id, license_id FROM lab_result WHERE id = :pk', [
			':pk' => $data['id'],
		]);
		if (!empty($chk['id'])) {
			return $RES->withJSON([
				'type' => 'https://api.openthc.org/e/409-duplicate-record',
				'data' => null,
				'meta' => [ 'detail' => 'Request Error [ARC#040]' ],
			], 409);
		}
		if ($chk['license_id'] != $data['license_id']) {
			return $RES->withJSON([
				'type' => 'https://api.openthc.org/e/409-license-not-matched',
				'data' => null,
				'meta' => [ 'detail' => 'Request Error [ARC#046]' ],
			], 409);
		}

		// Now Add the Result
		$rec = $dbc->insert('lab_result', [
			'id' => $data['id'],
			'license_id' => $data['license_id'],
			'type' => $data['type'],
			'name' => '',
		]);


	}

	function _inputData()
	{
		$data = null;

		$type = strtok($_SERVER['CONTENT_TYPE'], ';');
		$type = strtolower($type);

		switch ($type) {
			case 'application/json':
				$json = stream_get_contents('php://input');
				$data = json_decode($json, true);
				return $RES->withJSON([
					'data' => $json,
				]);
			break;
			case 'application/x-www-form-urlencoded':
				$data = $_POST;
			break;
		}

		return $data;
	}
}
