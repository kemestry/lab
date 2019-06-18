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

		// Sync all Transfers if 'id' parameter is not set, or the single transfer
		$a->map(['GET','POST'], '/sync', 'App\Controller\Transfer\Sync');

		$a->get('/{id}', 'App\Controller\Transfer\View');
		$a->post('/{id}', 'App\Controller\Transfer\View');

		// Sync one transfer
		$a->map(['GET', 'POST'], '/{id}/sync', 'App\Controller\Transfer\Sync');

		$a->get('/{id}/accept', 'App\Controller\Transfer\Accept');
		$a->post('/{id}/accept', 'App\Controller\Transfer\Accept:accept');

	}
}
