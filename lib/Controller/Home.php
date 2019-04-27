<?php
/**
 * Home Controller
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

	/**
	 * When Someone Has Intent
	 * @param [type] $REQ [description]
	 * @param [type] $RES [description]
	 * @param [type] $ARG [description]
	 * @return [type] [description]
	 */
	function intent($REQ, $RES, $ARG)
	{
		switch ($_SESSION['intent']) {
		case 'share-all':
			unset($_SESSION['intent']);
			unset($_SESSION['intent-data']);
			$RES = $RES->withRedirect('/result');
			break;
		case 'share-one':
			$RES = $RES->withRedirect('/result/' . $_SESSION['intent-data']);
			unset($_SESSION['intent']);
			unset($_SESSION['intent-data']);
			break;
		}

		return $RES;
	}
}
