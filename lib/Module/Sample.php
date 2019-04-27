<?php
/**
 * Wraps all the Routing for the Result Module
 */

namespace App\Module;

class Sample extends \OpenTHC\Module\Base
{
	function __invoke($a)
	{
		$a->get('', 'App\Controller\Sample');
		$a->get('/sync', 'App\Controller\Sample:sync');

		$a->map([ 'GET', 'POST'], '/view', 'App\Controller\Sample\View');
		$a->map([ 'GET', 'POST'], '/{guid}', 'App\Controller\Sample\View');

	}
}
