<?php
/**
 * Wraps all the Routing for the Client Module
 */

namespace App\Module;

class Client extends \OpenTHC\Module\Base
{
	function __invoke($a)
	{
		$a->map([ 'GET', 'POST'], '/{id}', 'App\Controller\Client\View');
	}
}
