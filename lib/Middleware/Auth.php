<?php
/**
 * Authentication Middleware
 */

namespace App\Middleware;

use Edoceo\Radix\DB\SQL;

class Auth extends \OpenTHC\Middleware\Base
{
	public function __invoke($REQ, $RES, $NMW)
	{
		// If we have a valid session, use that
		if (!empty($_SESSION['Contact']['id'])) {
			return $NMW($REQ, $RES);
		}

		$auth = trim($_SERVER['HTTP_AUTHORIZATION']);

		$chk = preg_match('/^basic (.+)$/i', $auth, $m) ? $m[1] : null;
		if (!empty($chk)) {
			$RES = $this->_basic($REQ, $RES, $chk);
			if (200 == $RES->getStatusCode()) {
				return $NMW($REQ, $RES);
			}
		}

		$chk = preg_match('/^bearer (.+)$/i', $auth, $m) ? $m[1] : null;
		if (!empty($chk)) {
			$RES = $this->_bearer($REQ, $RES, $chk);
			if (200 == $RES->getStatusCode()) {
				return $NMW($REQ, $RES);
			}
		}

		_exit_html('<h1>Invalid Authentication [AMA#084]</h1><p>Please <a href="/auth/open">sign in</a> again.</p>', 403);

		return $RES->withJSON(array(
			'data' => [],
			'meta' => [ 'detail' => '' ],
		), 403);

	}

	/**
		@param $RES Response
		@param $tok The Basic Token
	*/
	protected function _basic($REQ, $RES, $tok)
	{
		$tok = base64_decode($tok, true);

		if (empty($tok)) {
			return $RES->withJSON(array(
				'status' => 'failure',
				'detail' => 'Invalid Authentication [AMA#104]'
			), 403);
		}

		// Basic Token should be Two Parts, which may also be in these PHP vars
		$service_key = trim($_SERVER['PHP_AUTH_USER']);
		$company_key = trim($_SERVER['PHP_AUTH_PW']);

		// Should be a Software Vendor
		$sql = 'SELECT * FROM auth_hash WHERE hash = ?';
		$arg = array($service_key);
		$res = SQL::fetch_row($sql, $arg);
		if (empty($res['id'])) {
			return $RES->withJSON(array(
				'status' => 'failure',
				'detail' => 'Invalid Authentication [AMA#119]'
			), 403);
		}
		$data = json_decode($res['json'], true);
		$Company = new Company($data['company_id']);
		if (empty($Company['id'])) {
			return $RES->withJSON(array(
				'status' => 'failure',
				'detail' => 'Invalid Authentication [AMA#127]'
			), 403);
		}

		$REQ = $REQ->withAttribute('Company_Vendor', $Company);

		// Should be a Licensed Operator
		$sql = 'SELECT * FROM auth_hash WHERE hash = ?';
		$arg = array($company_key);
		$res = SQL::fetch_row($sql, $arg);
		if (empty($res['id'])) {
			return $RES->withJSON(array(
				'status' => 'failure',
				'detail' => 'Invalid Authentication [AMA#140]'
			), 403);
		}
		$data = json_decode($res['json'], true);
		$Company = new Company($data['company_id']);

		if (empty($Company['id'])) {
			return $RES->withJSON(array(
				'status' => 'failure',
				'detail' => 'Invalid Authentication [AMA#149]'
			), 403);
		}

		$REQ = $REQ->withAttribute('Company_Client', $Company);

	}

	/**
		@param $RES Response
	*/
	protected function _bearer($REQ, $RES, $tok)
	{
		// Find Directly Supplied Hash
		$res = SQL::fetch_row('SELECT * FROM auth_hash WHERE hash = :hash', array($tok));
		if (!empty($res)) {

			$data = json_decode($res['json'], true);
			$Company = new Company($res['company_id']);
			$Contact = new Contact($res['uid']);

			if (empty($Company['id'])) {
				return $RES->withJSON(array(
					'status' => 'failure',
					'detail' => 'MWA#068: Invalid Auth',
					// '_res' => $res,
				), 403);
			}

			if (empty($Contact['id'])) {
				return $RES->withJSON(array(
					'status' => 'failure',
					'detail' => 'MWA#036: Invalid Auth',
					// '_res' => $res,
				), 403);
			}

			$REQ = $REQ->withAttribute('Company', $Company);
			$REQ = $REQ->withAttribute('Contact', $Contact);

			return $RES;

		}

		// Then Try the Machine Token
		// Only WeedTraQR is allow this, and it's very stupid /djb 20171111
		$hash = sprintf('machine-%s', $tok);
		$res = SQL::fetch_row('SELECT * FROM auth_hash WHERE hash = :hash', array($hash));
		if (!empty($res)) {
			$RES = $NMW($REQ, $RES);
			return $RES;
		}

		return $RES->withJSON(array(
			'status' => 'failure',
			'detail' => 'MWA#034: Token Authorization Failed',
			'_tok' => $tok,
			'_hash' => $hash,
		), 403);

	}

}
