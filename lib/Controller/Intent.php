<?php
/**
 * When Someone Has Intent
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

use OpenTHC\Company;

class Intent extends \OpenTHC\Controller\Base
{
	function __invoke($REQ, $RES, $ARG)
	{
		if (!empty($_GET['_'])) {

			$arg = _decrypt($_GET['_']);
			$arg = json_decode($arg, true);

			if (!empty($arg['x'])) {
				// Expire Time
			}

			// Action
			switch ($arg['a']) {
			case 'coa-upload':

				$LR = new \App\Lab_Result($arg['r']);
				// var_dump($LR); exit;

				$file = 'page/intent/coa-upload.html';
				$data = array(
					'Page' => array('title' => 'COA Upload'),
					'Result' => array(
						'id' => $LR['id'],
					)
				);


				switch ($_POST['a']) {
				case 'coa-upload':
					// Whenever this triggers, fix it to use Lab_Result->getCOAFile();
					$LR->setCOAFile($_FILES['file']['tmp_name']);
					$data['alert'] = 'success';

				}

				return $this->_container->view->render($RES, $file, $data);

				break;

			case 'coa-upload-bulk':
				return $this->_coa_bulk($RES, $arg);
			}
		}


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

	private function _coa_bulk($RES, $arg)
	{
		// var_dump($arg);
		$Company = new Company($arg['company_id']);
		if (empty($Company['id'])) {
			return $RES->withStatus(400);
		}

		// var_dump($_POST);
		// var_dump($_FILES);

		if (1 == count($_FILES)) {
			if (0 == $_FILES['file']['error']) {

				$import_queue_path = sprintf('%s/var/import/%s', APP_ROOT, $Company['id']);
				$import_queue_file = sprintf('%s/%s', $import_queue_path, urlencode($_FILES['file']['name']));

				if (!is_dir($import_queue_path)) {
					mkdir($import_queue_path, 0755, true);
				}

				move_uploaded_file($_FILES['file']['tmp_name'], $import_queue_file);

			}

			return $RES->withStatus(201);

		}

		$data = [
			'Page' => [ 'title' => 'Result :: COA :: Upload' ],
			'Company' => $Company->toArray(),
			'mode' => 'lab-bulk',
		];
		$file = 'page/result/upload.html';

		return $this->_container->view->render($RES, $file, $data);
	}
}
