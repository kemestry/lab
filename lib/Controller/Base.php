<?php
/**
 * Base Controller
 *
 * This file is part of OpenTHC Laboratory Portal
 *
 * OpenTHC Laboratory Portal is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 3 as published by
 * the Free Software Foundation.
 *
 * OpenTHC Laboratory Portal is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with OpenTHC Laboratory Portal.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace App\Controller;

class Base extends \OpenTHC\Controller\Base
{
	function loadSiteData($data=[])
	{
		$cfg = \OpenTHC\Config::get('application');
		$base = [
			'Site' => [
				'hostname' => $_SERVER['SERVER_NAME'],
			],
			'OpenTHC' => []
		];

		$base['OpenTHC']['dir'] = \OpenTHC\Config::get('openthc/dir');
		$base['OpenTHC']['sso'] = \OpenTHC\Config::get('openthc/sso');

		$data = array_merge($base, $data);

		return $data;
	}

	/**
	 * PHP Script Render Helper
	 */
	function render($file)
	{
		$root = substr($file, 0, strlen(APP_ROOT));
		if (APP_ROOT === $root) {
			// Already a Valid Path
		} else {
			$file = trim($file, '/');
			$file = sprintf('%s/view/%s', APP_ROOT, $file);
		}

		ob_start();
		require_once($file);
		$html = ob_get_clean();
		return $html;
	}

}
