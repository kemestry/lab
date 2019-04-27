<?php
/**
 * Wraps all the Routing for the Result Module
 */

namespace App\Module;

class Result extends \OpenTHC\Module\Base
{
	function __invoke($a)
	{
		$a->get('', 'App\Controller\Result');

		$a->get('/create', 'App\Controller\Result\Create');
		$a->post('/create', 'App\Controller\Result\Create');

		$a->get('/download', 'App\Controller\Result\Download');

		$a->map([ 'GET', 'POST'], '/view', 'App\Controller\Result\View');
		$a->map([ 'GET', 'POST'], '/{guid}', 'App\Controller\Result\View');

	}
}
