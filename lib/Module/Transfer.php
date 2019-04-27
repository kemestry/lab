<?php
/**
 * Wraps all the Routing for the Transfer Module
 */

namespace App\Module;

class Transfer extends \OpenTHC\Module\Base
{
	function __invoke($a)
	{
		$a->get('', 'App\Controller\Transfer');

		$a->get('/accept', 'App\Controller\Transfer\Accept');
		$a->post('/accept', 'App\Controller\Transfer\Accept');

		$a->get('/{guid}', 'App\Controller\Transfer\View');

		//$app->get('/transfer/import/{guid}', 'App\Controller\Transfer\Import')
		//	->add('Middleware_Auth')
		//	;
	}
}
