<?php
/**
 * View a Client
 */

namespace App\Controller\Client;

// use Edoceo\Radix\Session;
// use Edoceo\Radix\DB\SQL;
// use Edoceo\Radix\Net\HTTP;

class View extends \OpenTHC\Controller\Base
{
	function __invoke($REQ, $RES, $ARG)
	{
		$data = [];
		$data['Page'] = [ 'title' => 'Client' ];

		$dbc = $this->_container->DBC_User;

		// Get Result
		$Client = new \OpenTHC\License($dbc, $ARG['id']);
		if (empty($Client['id'])) {
			_exit_html('Client Not Found', 404);
		}
		$data['Client'] = $Client->toArray();

		$sql = <<<SQL
SELECT lab_result.*
FROM lab_result
JOIN lab_result_company ON lab_result.id = lab_result_company.lab_result_id
WHERE lab_result_company.company_id = :c0
AND lab_result.license_id = :l0
ORDER BY lab_result.created_at DESC, lab_result.id
SQL;

		$arg = [
			':c0' => $_SESSION['Company']['id'],
			':l0' => $Client['id'],
		];

		$data['lab_result_list'] = $dbc->fetchAll($sql, $arg);

		$file = 'page/client/view.html';

		return $this->_container->view->render($RES, $file, $data);
	}
}
