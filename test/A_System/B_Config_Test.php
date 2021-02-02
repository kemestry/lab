<?php
/**
 *
 */

namespace Test\System;

class B_Config_Test extends \Test\Base
{
	function test_oauth()
	{
		$cfg = \OpenTHC\Config::get('oauth');

		$key_list = [
			'hostname',
			'clientId',
			'clientSecret',
			'urlAuthorize',
			'urlAccessToken',
			'urlResourceOwnerDetails',
		];

		foreach ($key_list as $k) {
			$this->assertArrayHasKey($cfg, $k, "oAuth '$k' not set");
		}

	}
}
