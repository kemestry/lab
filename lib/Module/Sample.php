<?php
/**
 * Wraps all the Routing for the Sample Module
 */

namespace App\Module;

class Sample extends \OpenTHC\Module\Base
{
	function __invoke($a)
	{
		$a->get('', 'App\Controller\Sample\Index');
		$a->map(['GET','POST'], '/sync', 'App\Controller\Sample\Sync');

		$a->map([ 'GET', 'POST'], '/{id}', 'App\Controller\Sample\View');

	}
}
