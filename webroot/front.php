<?php
/**
 * Front Controller
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
	$this->get('/open', 'App\Controller\Auth\oAuth2\Open');
	$this->get('/connect', 'App\Controller\Auth\Connect'); // would like to merge with Open or Back
	$this->get('/back', 'App\Controller\Auth\oAuth2\Back');
	$this->get('/init', 'App\Controller\Auth\Init')->setName('auth/init');
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
// $app->group('/client', 'App\Module\Client')
// 	->add('App\Middleware\Menu')
// 	->add('App\Middleware\Auth')
// 	->add('App\Middleware\Session');


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


// Execute Slim
$res = $app->run();

exit(0);
