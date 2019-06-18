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

		$a->map([ 'GET', 'POST'], '/{id}', 'App\Controller\Result\View');

		$a->get('/{id}/sync', 'App\Controller\Result\Sync');

	}
}
