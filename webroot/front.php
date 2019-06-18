<?php
/**
 * Front Controller for lab.openthc.org
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
//$cfg = array();
$cfg = array('debug' => true);
$app = new \OpenTHC\App($cfg);

// Database Connection
$con = $app->getContainer();
$con['DB'] = function($c) {
	$cfg = \OpenTHC\Config::get('database_main');
	$c = sprintf('pgsql:host=%s;dbname=%s', $cfg['hostname'], $cfg['database']);
	$u = $cfg['username'];
	$p = $cfg['password'];
	$dbc = new \Edoceo\Radix\DB\SQL($c, $u, $p);
	return $dbc;
};


// Pub Home
$app->get('/', function($REQ, $RES, $ARG) {
	$data = array(
		'Page' => array('title' => 'QA Laboratory Portal - OpenTHC'),
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
	$this->map(['GET', 'POST'], '/open', 'App\Controller\Auth\Open');

	//$this->map(['GET', 'POST'], '/connect', 'OpenTHC\Controller\Auth\Connect');
	$this->map(['GET', 'POST'], '/connect', 'App\Controller\Auth\Connect');

	// oAuth Stuff
	$this->get('/oauth/open', 'App\Controller\Auth\oAuth2\Open');
	$this->get('/oauth/back', 'App\Controller\Auth\oAuth2\Back');

	$this->get('/fail', 'OpenTHC\Controller\Auth\Fail');
	$this->get('/ping', 'OpenTHC\Controller\Auth\Ping');
	$this->get('/shut', 'OpenTHC\Controller\Auth\Shut');

})
->add('App\Middleware\Menu')
->add('App\Middleware\Session');


// Sample Submit
// 'App\Module\API'
$app->group('/api', function() {

	$this->get('', function($REQ, $RES) {
		$data = array('Page' => array('title' => 'API'));
		return $this->view->render($RES, 'page/home-api.html', $data);
	});

	$this->group('/v1', function() {

		// Return List of Samples
		$this->get('/qa', function($REQ, $RES, $ARG) {
			return require_once(APP_ROOT . '/api/qa/search.php');
		})->add('App\Middleware\Auth');

		//$this->post('/qa', function($REQ, $RES, $ARG) {
		//	die('Create QA Sample');
		//})->add($MWA);

		//$this->get('/qa/sample', function($REQ, $RES, $ARG) {
		//	die('List QA Samples');
		//})->add($MWA);
		$this->get('/result/{id}.pdf', function($REQ, $RES, $ARG) {
			return require_once(APP_ROOT . '/api/qa/result.pdf.php');
		});

		// Create a Sample
		//$this->post('/qa/sample', function($REQ, $RES, $ARG) {
		//	require_once(APP_ROOT . '/api/qa/sample-create.php');
		//})->add('Middleware_Auth');

		// Select Specific QA Sample
		$this->get('/qa/{code}', function($REQ, $RES, $ARG) {
			return require_once(APP_ROOT . '/api/qa/sample.php');
		});

		// Select QA Sample+Result
		$this->get('/qa/{code}/result', function($REQ, $RES, $ARG) {
			require_once(APP_ROOT . '/api/qa/sample.php');
		});

		// Update QA Result
		//$this->post('/qa/{code}/result', function($REQ, $RES, $ARG) {
		//	require_once(APP_ROOT . '/api/qa/result.php');
		//})->add('Middleware_Auth');

	})
	->add('MiddlewareJSON')
	->add('MiddlewareLogHTTP')
	;

});


// Transfer Group
$app->group('/transfer', 'App\Module\Transfer')
	->add('App\Middleware\Menu')
	->add('App\Middleware\Auth')
	->add('App\Middleware\Session');


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

// Sync
$app->get('/sync', 'App\Controller\Sync')
	->add('App\Middleware\Menu')
	->add('App\Middleware\Session');

$app->post('/sync', 'App\Controller\Sync:exec')
	->add('App\Middleware\Menu')
	->add('App\Middleware\Session');


// No Session Here
$app->get('/about', function($REQ, $RES, $ARG) {
	$data = array('Page' => array('title' => 'About'));
	return $this->view->render($RES, 'page/about.html', $data);
})->add('App\Middleware\Menu');


// Dump Routes?
if ('routes' == $_GET['_dump']) {
	$app->dumpRoutes();
}


// Execute Slim
$res = $app->run();
