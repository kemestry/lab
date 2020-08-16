#!/usr/bin/php
<?php
/**
 */

require_once(__DIR__ . '/../boot.php');

$mode = $argv[2]; // Y, Q, M
switch ($mode) {
	case 'M':
	case 'Q':
	case 'Y':
		// OK
	break;
	default:
		die("\nInvalid Mode\n");
}

$cfg = \OpenTHC\Config::get('database/auth');
$c = sprintf('pgsql:host=%s;dbname=%s', $cfg['hostname'], $cfg['database']);
$u = $cfg['username'];
$p = $cfg['password'];
$dbc_auth = new \Edoceo\Radix\DB\SQL($c, $u, $p);

// $dbc_auth = _dbc('auth');

$sql = 'SELECT * FROM auth_company WHERE THEY_ARE_A_LAB';
$res_company = $dbc_auth->fetchAll('SELECT id, id_int8, id_ulid, dsn FROM auth_company WHERE id = :x0', [ ':x0' => $argv[1] ]);

foreach ($res_company as $c) {

	$s = strtolower(sprintf('seq_%s_%s', $c['id_ulid'], $mode));

	$dbc_user = new \Edoceo\Radix\DB\SQL($c['dsn']);

	$arg = [
		':s' => $s,
	];

	$min = $dbc_user->fetchOne('SELECT val FROM base_option WHERE key = :s', $arg);
	$min = max(1, $min);
	var_dump($min);

	$seq = $dbc_user->fetchOne("SELECT * FROM pg_class WHERE relname = :s AND relkind = 'S'", $arg);
	var_dump($seq);

	// Reset
	$res = $dbc_user->query(sprintf('DROP SEQUENCE IF EXISTS %s', $s), []);
	var_dump($res);

	$res = $dbc_user->query(sprintf('CREATE SEQUENCE %s MINVALUE %d START WITH %d', $s, $min, $min ), []);
	var_dump($res);

	// $res = $dbc_user->_sql_debug(sprintf('SELECT setval(%s, %d, false)', $s, $min), []);
	// var_dump($res);

}
