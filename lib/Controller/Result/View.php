<?php
/**
 * View a QA Result
 */

namespace App\Controller\Result;

use Edoceo\Radix\DB\SQL;
use Edoceo\Radix\Net\HTTP;

class View extends \OpenTHC\Controller\Base
{
	function __invoke($REQ, $RES, $ARG)
	{
		$id = $ARG['id'];
		if (empty($id)) {
			_exit_text('Invalid Request', 400);
		}

		// Get Result
		$QAR = new \App\QA_Result($id);
		if (empty($QAR['id'])) {
			_exit_text('QA Result Not Found', 404);
		}

		$meta = json_decode($QAR['meta'], true);

		if (!empty($_POST['a'])) {
			return $this->_postHandler($RES, $QAR, $meta);
		}

		// if (empty($QAR['id'])) {
		// 	$data = array();
		// 	$data['Page'] = array('title' => 'Result :: Not Found');
		// 	$data['result_id'] = $ARG['id'];
		// 	if (empty($_SESSION['uid'])) {
		// 		return $this->_container->view->render($RES, 'page/result/404.html', $data);
		// 	}
		// 	$data['Result'] = array(
		// 		'id' => '-Not Found-',
		// 		'testing_status' => '-N/A-',
		// 		'status' => '-N/A-',
		// 		'thc' => '0',
		// 		'cbd' => '0',
		// 	);
		// 	$data['Sample'] = array(
		// 		'id' => '-Not Found-',
		// 	);
		// 	return $this->_container->view->render($RES, 'page/result/view.html', $data);
		// }

		$data = array();
		$data['Page'] = array('title' => 'Result :: View');
		$data['Sample']  = $meta['Sample'];
		$data['Result']  = $meta['Result'];
		$data['Result']['coa_file'] = $QAR->getCOAFile();
		if (!is_file($data['Result']['coa_file'])) {
			$data['Result']['coa_file'] = null;
		}

		$data['Product'] = $meta['Product'];
		$data['Strain']  = $meta['Strain'];

		if (!empty($QAR['license_id_lab'])) {
			$x = \OpenTHC\License::findByGUID($QAR['license_id_lab']);
			if ($x) {
				$data['Laboratory'] = $x->toArray();
			}
		}

		$data['coa_upload_hash'] = _encrypt(json_encode(array(
			'a' => 'coa-upload',
			'r' => $QAR['guid'],
			'x' => $_SERVER['REQUEST_TIME'] + (86400 * 4)
		)));

		// https://stackoverflow.com/a/8940515
		$data['share_mail_link'] = http_build_query(array(
			'subject' => sprintf('QA Results %s', $data['Result']['global_id']),
			'body' => sprintf("\n\nHere is the link: https://lab.openthc.org/share/%s.html", $data['Result']['global_id']),
		), null, '&', PHP_QUERY_RFC3986);

		if ('data' == $_GET['_dump']) {
			_exit_text($data);
		}

		return $this->_container->view->render($RES, $file = 'page/result/view.html', $data);

	}

	function _postHandler($RES, $QAR, $meta)
	{
		$lab_result_id = $QAR['id'];
		$coa_file = $QAR->getCOAFile();
		if (empty($coa_file)) {
			//error_log("Invalid coa_file for {$QAR['id']}");
			_exit_text('This QA Result Needs to be Re-Sync for Upload [CRV#083]');
		}

		$coa_name = sprintf('COA-%s.pdf', $QAR['id']);

		switch ($_POST['a']) {
		case 'coa-download':
		case 'download-coa':

			if (!empty($coa_file) && (is_file($coa_file))) {

				header(sprintf('content-disposition: inline; filename="%s"', $coa_name));
				header('content-transfer-encoding: binary');
				header('content-type: application/pdf');

				//var_dump($coa_file);
				readfile($coa_file);

				exit(0);
			}

			_exit_text('No COA to Download', 404);

			break;

		case 'coa-upload':
		case 'file-upload':

			$src_name = strtolower($_FILES['file']['name']);
			$qar_want = strtolower(preg_match('/\.(\w+)/', $lab_result_id, $m) ? $m[1] : $lab_result_id);

			$chk_name = strpos($src_name, $qar_want);

			if (false === $chk_name) {
				_exit_text('Please put the Lab Result ID in the Filename for verification');
			}

			$QAR->setCOAFile($_FILES['file']['tmp_name']);

			return $RES->withRedirect('/result/' . $lab_result_id);

			break;

		case 'mute':
			$QAR->setFlag(\App\QA_Result::FLAG_MUTE);
			$QAR->save();
			break;
		case 'share':
			return $RES->withRedirect(sprintf('/share/%s.html', $lab_result_id));
			break;
		case 'sync':
			$S = new Sync($this->_container);
			return $S->__invoke(null, $RES, array('id' => $lab_result_id));
			break;
		case 'void':
			$cre = new \OpenTHC\RCE($_SESSION['pipe-token']);
			$res = $cre->qa()->delete($lab_result_id); // QAR['guid']);
			var_dump($res);
			exit;
			break;
		default:
			var_dump($_POST);
			var_dump($_FILES);
			die("not Handled");
		}

	}
}
