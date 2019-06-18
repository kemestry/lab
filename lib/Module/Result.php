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

		$a->get('/create/{sample_id}', 'App\Controller\Result\Create');
		$a->post('/create/{sample_id}/save', 'App\Controller\Result\Create:save');

		$a->get('/download', 'App\Controller\Result\Download');

		$a->map([ 'GET', 'POST'], '/{id}', 'App\Controller\Result\View');

		$a->get('/{id}/sync', 'App\Controller\Result\Sync');

	}
}
