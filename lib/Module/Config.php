<?php
/**
 * Wraps all the Routing for the Config Module
 */

namespace App\Module;

class Config extends \OpenTHC\Module\Base
{
	function __invoke($a)
	{
		$a->map([ 'GET', 'POST'], '/metric', 'App\Controller\Config\Metric');
	}
}
