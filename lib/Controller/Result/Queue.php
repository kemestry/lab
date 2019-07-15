<?php
/**
 * Process Queue of COA Uploads
 */

namespace App\Controller\Result;

class Queue extends \App\Controller\Result\Upload
{
	function __invoke($REQ, $RES, $ARG)
	{
		$data = array();
		$data['Page'] = array('title' => 'Result :: COA :: Upload :: Queue');

		if (!empty($_GET['f'])) {
			// Single File
			$pdf_file = $this->resolveFile($_GET['f']);
			$txt_file = preg_replace('/pdf$/i', 'txt', $pdf_file);

			if (!is_file($txt_file)) {

				$cmd = array();
				$cmd[] = '/usr/bin/pdftotext';
				$cmd[] = '-layout';
				// $cmd[] = '-raw';
				$cmd[] = \escapeshellarg($pdf_file);
				$cmd[] = \escapeshellarg($txt_file);
				$cmd[] = '2>&1';
				$buf = shell_exec(implode(' ', $cmd));
				// var_dump($buf);
			}

			$data['pdf_file'] = basename($pdf_file);
			$data['txt_file'] = basename($txt_file);
			$data['txt_data'] = \file_get_contents($txt_file);

			if (preg_match_all('/(WA\w{2,7}\.(IN|LR)\w+)/', $data['txt_data'], $m)) {
				$lot_list = $m[1];
				$data['lot_list'] = $lot_list;
				foreach ($lot_list as $l) {

					// $chk = $this->_container->DB->fetchRow('SELECT id FROM lab_sample WHERE id = ?');
					// $chk = $this->_container->DB->fetchRow('SELECT id FROM lab_result WHERE id = ?');
					// $chk = $this->_container->DB->fetchRow('SELECT id FROM lab_result WHERE meta->'Sample'->'global_id' = ?');
				}
			}


			return $this->_container->view->render($RES, 'page/result/queue-one.html', $data);
		}

		$import_queue_list = array();
		$import_queue_path = sprintf('%s/var/import/%s', APP_ROOT, $_SESSION['License']['id']);
		$pdf_list = glob("$import_queue_path/*.pdf");
		foreach ($pdf_list as $pdf_file) {
			$import_queue_list[] = basename($pdf_file);
		}
		sort($import_queue_list);

		$data['file_list'] = $import_queue_list;

		return $this->_container->view->render($RES, 'page/result/queue.html', $data);
	}
}
