<?php
/**
 * Return One Lab Result, Inflated
 */

namespace App\Controller\API\Result;

class Update extends \OpenTHC\Controller\Base
{
	function __invoke($REQ, $RES, $ARG)
	{
		$data = $this->_inputData();
		if (empty($data)) {
			return $RES->withJSON([
				'type' => 'https://api.openthc.org/e/400-invalid-content',
				'data' => null,
				'meta' => [ 'detail' => 'Request Error [ARC#016]' ],
			], 400);
		}

		if (empty($data['id'])) {
			return $RES->withJSON([
				'type' => 'https://api.openthc.org/e/400-missing-parameter',
				'data' => null,
				'meta' => [ 'detail' => 'Request Error [ARC#023]' ],
			], 400);
		}
		if (empty($data['license_id'])) {
			return $RES->withJSON([
				'type' => 'https://api.openthc.org/e/400-missing-parameter',
				'data' => null,
				'meta' => [ 'detail' => 'Request Error [ARC#029]' ],
			], 400);
		}


		$dbc = $this->_container->DB;

		// Check Data
		$chk = $dbc->fetchRow('SELECT id, license_id FROM lab_result WHERE id = :pk AND license_id = :l0', [
			':pk' => $data['id'],
			':l0' => $data['license_id'],
		]);
		if (empty($chk['id'])) {
			return $RES->withJSON([
				'type' => 'https://api.openthc.org/e/404',
				'data' => null,
				'meta' => [ 'detail' => 'Request Error [ARC#048]' ],
			], 404);
		}


		// Update Record
		$mod = [

		];
		$where = [
			'id' => $data['id'],
			'license_id' => $data['license_id'],
		];
		$dbc->update('lab_result', $mod, $where)

		// Update METRICs


		return $RES->withJSON([
			'type' => 'https://api.openthc.org/e/400',
			'data' => null,
			'meta' => null,
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
