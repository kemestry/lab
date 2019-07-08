<?php
/**
 * Accept Upload of Sampole Files
 */

namespace App\Controller\Result;

class Upload extends \OpenTHC\Controller\Base
{
	function __invoke($REQ, $RES, $ARG)
	{
		$data = array();
		$data['Page'] = array('title' => 'Result :: COA :: Upload');

		switch ($_SERVER['REQUEST_METHOD']) {
		case 'GET':
			return $this->_container->view->render($RES, 'page/result/upload.html', $data);
		case 'POST':

			// var_dump($_POST);
			// var_dump($_FILES);

			if (1 == count($_FILES)) {
				if (0 == $_FILES['file']['error']) {

					$import_queue_path = sprintf('%s/var/import/%s', APP_ROOT, $_SESSION['License']['id']);
					$import_queue_file = sprintf('%s/%s', $import_queue_path, urlencode($_FILES['file']['name']));

					if (!is_dir($import_queue_path)) {
						mkdir($import_queue_path, 0755, true);
					}

					move_uploaded_file($_FILES['file']['tmp_name'], $import_queue_file);

				}
			}

			return $RES->withJSON(array(
				'status' => 'success',
			));

			break;
		}
	}
}
