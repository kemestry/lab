<?php
/**
 * Home Controller
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

class Home extends \OpenTHC\Controller\Base
{
	function __invoke($REQ, $RES, $ARG)
	{
		$data = array(
			'Page' => array('title' => 'Dashboard'),
			'Company' => $_SESSION['Company'],
			'License' => $_SESSION['License'],
			'Contact' => $_SESSION['Contact'],
		);

		$file = 'page/home-supply.html';
		switch ($_SESSION['License']['type']) {
		case '':
		case 'Laboratory':
			$file = 'page/home-lab.html';
			break;
		}

		return $this->_container->view->render($RES, $file, $data);

	}

}
