<?php
/**
 * Initialize The Session
 */

namespace App\Controller\Auth;

use Edoceo\Radix\DB\SQL;

class Init extends \App\Controller\Auth\oAuth2
{
	function __invoke($REQ, $RES, $ARG)
	{
		// Lookup the DSN
		$dbc = $this->_container->DBC_Auth;
		$C1 = $dbc->fetchRow('SELECT * FROM auth_company WHERE id_ulid = ?', $_SESSION['Company']['id']);
		if (empty($C1['dsn'])) {
			return $RES->withJSON([
				'data' => [],
				'meta' => [ 'detail' => 'Fatal Database Error [CAC#043]'],
			], 500);
		}

		$_SESSION['dsn'] = $C1['dsn'];

		unset($_SESSION['Company']['dsn']);
		unset($_SESSION['cre']);
		unset($_SESSION['cre-auth']);
		unset($_SESSION['cre-base']);
		unset($_SESSION['gid']);
		unset($_SESSION['uid']);
		unset($_SESSION['sql-hash']);

		$r = $_GET['r'];
		if (empty($r)) {
			$r = '/home';
		}

		return $RES->withRedirect($r);

	}
}
