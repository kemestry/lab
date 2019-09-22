<?php
/**
 * View a QA Result
 */

namespace App\Controller\Result;

use Edoceo\Radix\Session;
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
		$LR = new \App\Lab_Result($id);
		if (empty($LR['id'])) {
			_exit_html(sprintf('QA Result Not Found, please <a href="/result/%s/sync">sync this result</a>', $id), 404);
		}

		$meta = json_decode($LR['meta'], true);
		// _ksort_r($meta);
		// _exit_text($meta);

		if (!empty($_POST['a'])) {
			return $this->_postHandler($RES, $LR, $meta);
		}


		// COA Formats
		if (!empty($_GET['f'])) {
			switch ($_GET['f']) {
			case 'coa':

				return $this->_viewCOA($REQ, $RES, $LR);

				break;
			case 'coa-pdf':
				// Genereate HTML then PDF
				// $coa = new \App\Output\COA()
				// $coa->setResult($LR);
				// file_contents();
				// render_with_puppetterrs()
				return(null);
				break;
			case 'coa-html':
				// Generate HTML PDF
				// $coa = new \App\Output\COA()
				// $coa->setResult($LR);
				// echo $coa;
				exit(0);
			}
		}


		// if (empty($LR['id'])) {
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
		$data['Result']['coa_file'] = $LR->getCOAFile();
		if (!is_file($data['Result']['coa_file'])) {
			$data['Result']['coa_file'] = null;
		}

		$data['Product'] = $meta['Product'];
		$data['Strain']  = $meta['Strain'];

		if (!empty($LR['license_id_lab'])) {
			$x = \OpenTHC\License::findByGUID($LR['license_id_lab']);
			if ($x) {
				$data['Laboratory'] = $x->toArray();
			}
		}

		$data['coa_upload_hash'] = _encrypt(json_encode(array(
			'a' => 'coa-upload',
			'r' => $LR['id'],
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
		// echo '<pre>'; var_dump($data);die;

		return $this->_container->view->render($RES, $file = 'page/result/view.html', $data);

	}

	function _postHandler($RES, $LR, $meta)
	{
		$lab_result_id = $LR['id'];
		$coa_file = $LR->getCOAFile();
		if (empty($coa_file)) {
			//error_log("Invalid coa_file for {$LR['id']}");
			_exit_text('This QA Result Needs to be Re-Sync for Upload [CRV#083]');
		}

		$coa_name = sprintf('COA-%s.pdf', $LR['id']);

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
			$pat_want = strtolower(preg_match('/\.(\w+)/', $lab_result_id, $m) ? $m[1] : $lab_result_id);

			$chk_name = strpos($src_name, $pat_want);

			if (false === $chk_name) {
				Session::flash('warn', 'Naming the file the same as the Lab Result is a good idea');
				// _exit_text('Please put the Lab Result ID in the Filename for verification');
			}

			$LR->setCOAFile($_FILES['file']['tmp_name']);

			return $RES->withRedirect('/result/' . $lab_result_id);

			break;

		case 'mute':
			$LR->setFlag(\App\Lab_Result::FLAG_MUTE);
			$LR->save();
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

	public function _viewCOA($REQ, $RES, $LR)
	{

		$meta = json_decode($LR['meta'], true);
		$data = [
			'Sample' => $meta['Sample'],
			'Result' => $meta['Result'],
			'Product' => $meta['Product'],
			'Strain' => $meta['Strain'],
		];

		if (!empty($LR['license_id_lab'])) {
			$x = \OpenTHC\License::findByGUID($LR['license_id_lab']);
			if ($x) {
				$data['Laboratory'] = $x->toArray();
			}
		}

		// @todo whats the difference?
		if (!empty($LR['license_id']))
		{
			$x = new \OpenTHC\License($LR['license_id']);
			if (!empty($x)) {
				$data['License'] = $x->toArray();
			}
		}
		// echo '<pre>';
		// var_dump($data);die;
		return $this->_container->view->render($RES, $page = 'coa/openthc.html', $data);
	}
}
