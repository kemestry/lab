<?php
/**
 * Front Controller for lab.openthc
 *
 * This file is part of OpenTHC Laboratory Portal
 *
 * OpenTHC Laboratory Portal is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 3 as published by
 * the Free Software Foundation.
 *
 * OpenTHC Laboratory Portal is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with OpenTHC Laboratory Portal.  If not, see <https://www.gnu.org/licenses/>.
 */

require_once(dirname(dirname(__FILE__)) . '/boot.php');

// Slim Application
$cfg = [];
$cfg['debug'] = true;
$app = new \OpenTHC\App($cfg);

// Database Connection
$con = $app->getContainer();
$con['DB'] = function($c) {

	$dbc = null;

	if (!empty($_SESSION['dsn'])) {
		$dbc = new \Edoceo\Radix\DB\SQL($_SESSION['dsn']);
	} else {
		$cfg = \OpenTHC\Config::get('database_main');
		$c = sprintf('pgsql:host=%s;dbname=%s', $cfg['hostname'], $cfg['database']);
		$u = $cfg['username'];
		$p = $cfg['password'];
		$dbc = new \Edoceo\Radix\DB\SQL($c, $u, $p);
		return $dbc;
	}

	return $dbc;

};

// Pub Home
$app->get('/', function($REQ, $RES, $ARG) {
	$data = array(
		'Page' => array('title' => 'Laboratory Data Portal - OpenTHC'),
	);
	return $this->view->render($RES, 'page/home-pub.html', $data);
})->add('App\Middleware\Menu');


// App Home
$app->get('/home', 'App\Controller\Home')
	->add('App\Middleware\Menu')
	->add('App\Middleware\Auth')
	->add('App\Middleware\Session');

$app->map(['GET','POST'], '/intent', 'App\Controller\Intent')
	->add('App\Middleware\Session');


// Authentication
$app->group('/auth', function() {

	// Unique Auth/Open for this site
	// $this->map(['GET', 'POST'], '/open', 'App\Controller\Auth\Open');

	//$this->map(['GET', 'POST'], '/connect', 'OpenTHC\Controller\Auth\Connect');
	// $this->map(['GET', 'POST'], '/connect', 'App\Controller\Auth\Connect');

	// oAuth Stuff
	// $this->get('/oauth/open', 'App\Controller\Auth\oAuth2\Open');
	// $this->get('/oauth/back', 'App\Controller\Auth\oAuth2\Back');

	$this->get('/open', 'App\Controller\Auth\oAuth2\Open');
	$this->get('/back', 'App\Controller\Auth\oAuth2\Back');
	$this->get('/fail', 'OpenTHC\Controller\Auth\Fail');
	$this->get('/ping', 'OpenTHC\Controller\Auth\Ping');
	$this->get('/shut', 'App\Controller\Auth\Shut');

})
->add('App\Middleware\Menu')
->add('App\Middleware\Session');


// Sample Submit
// 'App\Module\API'
$app->group('/api', 'App\Module\API');


// Sample Group
$app->group('/sample', 'App\Module\Sample')
	->add('App\Middleware\Menu')
	->add('App\Middleware\Auth')
	->add('App\Middleware\Session');


// Result Group
$app->group('/result', 'App\Module\Result')
	->add('App\Middleware\Menu')
	->add('App\Middleware\Auth')
	->add('App\Middleware\Session');


// Client Group
$app->group('/client', 'App\Module\Client')
	->add('App\Middleware\Menu')
	->add('App\Middleware\Auth')
	->add('App\Middleware\Session');


// Search
$app->get('/search', 'App\Controller\Search')
	->add('App\Middleware\Menu')
	->add('App\Middleware\Auth')
	->add('App\Middleware\Session');


// No Session Here
$app->get('/share', 'App\Controller\Share')
	->add('App\Middleware\Menu')
	->add('App\Middleware\Auth')
	->add('App\Middleware\Session');

$app->get('/share/{id}', 'App\Controller\Result\Share')
	->add('App\Middleware\Menu')
	->add('App\Middleware\Session');


// Config Group
$app->group('/config', 'App\Module\Config')
	->add('App\Middleware\Menu')
	->add('App\Middleware\Auth')
	->add('App\Middleware\Session');


// Sync
$app->get('/sync', 'App\Controller\Sync')
	->add('App\Middleware\Menu')
	->add('App\Middleware\Session');

$app->post('/sync', 'App\Controller\Sync:exec')
	->add('App\Middleware\Menu')
	->add('App\Middleware\Session');


// Dump Routes?
if ('routes' == $_GET['_dump']) {
	$app->dumpRoutes();
}


// Execute Slim
$res = $app->run();

exit(0);

if ('create' == $_GET['a']) {

	$dbc = $con['DB'];

	$C0 = new \OpenTHC\Company($dbc);
	if (!$C0->loadBy('id', $_SESSION['Company']['id'])) {
		$C0['id'] = $_SESSION['Company']['id'];
		$C0['name'] = $_SESSION['Company']['name'];
		$C0['hash'] = $C0->getHash();
		$C0->save();
		var_dump($C0);
	}
	var_dump($C0->getHash());

	$L0 = new \OpenTHC\License($dbc);
	$arg = [
		':c0' => $C0['id'],
		':l0' => $_SESSION['License']['id'],
	];
	$chk = $dbc->fetchOne('SELECT id FROM license WHERE company_id = :c0 AND id = :l0', $arg);
	if (!$L0->loadBy('id', $chk)) {
		$L0['id'] = $_SESSION['License']['id'];
		$L0['company_id'] = $C0['id'];
		$L0['name'] = $_SESSION['License']['name'];
		$L0['hash'] = $L0->getHash();
		$L0->save();
		var_dump($L0);
	}
	var_dump($L0->getHash());

}
