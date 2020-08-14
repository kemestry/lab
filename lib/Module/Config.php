<?php
/**
 * Wraps all the Routing for the Config Module
 */

namespace App\Module;

class Config extends \OpenTHC\Module\Base
{
	function __invoke($a)
	{
		$a->get('', 'App\Controller\Config');
		$a->map([ 'GET', 'POST'], '/metric', 'App\Controller\Config\Metric');
		$a->map([ 'GET', 'POST'], '/coa-layout', 'App\Controller\Config\COA');
		$a->map([ 'GET', 'POST'], '/intake', 'App\Controller\Config\Intake');
		$a->map([ 'GET', 'POST'], '/sample', 'App\Controller\Config\Sample');
	}
}
