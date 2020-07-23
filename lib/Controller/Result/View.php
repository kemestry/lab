<?php
/**
 * View a QA Result
 */

namespace App\Controller\Result;

use Edoceo\Radix\Session;

class View extends \App\Controller\Base
{
	function __invoke($REQ, $RES, $ARG)
	{
		$id = $ARG['id'];
		if (empty($id)) {
			_exit_text('Invalid Request', 400);
		}

		$dbc = $this->_container->DB;

		// Get Result
		$LR = new \App\Lab_Result($dbc, $id);
		// _exit_text($LR);

		if (empty($LR['id'])) {
			_exit_html(sprintf('QA Result Not Found, please <a href="/result/%s/sync">sync this result</a>', $id), 404);
		}

		$Product = $dbc->fetchRow('SELECT * FROM product WHERE id = ?', [ $Lab_Sample['product_id'] ]);
		$ProductType = $dbc->fetchRow('SELECT * FROM product_type WHERE id = ?', [ $Product['product_type_id'] ]);
		$Strain = $dbc->fetchRow('SELECT * FROM strain WHERE id = ?', [ $Lab_Sample['strain_id'] ]);

		$meta = json_decode($LR['meta'], true);
		// _ksort_r($meta);
		// _exit_text($meta);

		if (!empty($_POST['a'])) {
			return $this->_postHandler($REQ, $RES, $ARG, $LR, $meta);
		}

		// $LR->getMetrics();

		// Get authoriative lab metrics
		$lab_metric_type_list = [];
		$lab_result_metric_list = [];

		$sql = <<<SQL
	SELECT lab_result_metric.*
	 , lab_metric.type
	 , lab_metric.name
	 , lab_metric.meta
	FROM lab_result_metric
	JOIN lab_metric ON lab_result_metric.lab_metric_id = lab_metric.id
	WHERE lab_result_metric.lab_result_id = :lr0
	ORDER BY lab_metric.type, lab_metric.sort, lab_metric.stat, lab_metric.name
	SQL;
		$arg = [
			':lr0' => $LR['id']
		];
		$res = $dbc->fetchAll($sql, $arg);
		foreach ($res as $rec) {
			$lab_metric_type_list[$rec['type']] = $rec['type'];
			$lab_result_metric_list[$rec['type']][$rec['id']] = $rec;
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

		$data = $this->loadSiteData();
		$data['Page'] = array('title' => 'Result :: View');
		$data['Result'] = $LR->toArray();
		// $data['Sample'] = $LS->toArray();
		// $data['Sample']  = $meta['Sample'];
		// $data['Result']  = $meta['Result'];
		$data['Result']['coa_file'] = $LR->getCOAFile();
		if (!is_file($data['Result']['coa_file'])) {
			$data['Result']['coa_file'] = null;
		}
		$data['metric_type_list'] = $lab_metric_type_list;
		$data['MetricList'] = $lab_result_metric_list;

		// $data['Product'] = $meta['Product'];
		// $data['Strain']  = $meta['Strain'];

		// if (!empty($LR['license_id_lab'])) {
		// 	$x = \OpenTHC\License::findByGUID($LR['license_id_lab']);
		// 	if ($x) {
		// 		$data['Laboratory'] = $x->toArray();
		// 	}
		// }

		$data['coa_upload_hash'] = _encrypt(json_encode(array(
			'a' => 'coa-upload',
			'r' => $LR['id'],
			'x' => $_SERVER['REQUEST_TIME'] + (86400 * 4)
		)));

		// https://stackoverflow.com/a/8940515
		$data['share_mail_link'] = http_build_query(array(
			'subject' => sprintf('Lab Results %s', $data['Result']['global_id']),
			'body' => sprintf("\n\nHere is the link: https://%s/share/%s.html", $_SERVER['SERVER_NAME'], $LR['id']),
		), null, '&', PHP_QUERY_RFC3986);

		// _exit_text($data);

		return $this->_container->view->render($RES, $file = 'page/result/view.html', $data);

	}

	function _postHandler($REQ, $RES, $ARG, $LR, $meta)
	{
		$lab_result_id = $LR['id'];
		$coa_file = $LR->getCOAFile();
		if (empty($coa_file)) {
			//error_log("Invalid coa_file for {$LR['id']}");
			_exit_text('This QA Result Needs to be Re-Sync for Upload [CRV#083]');
		}

		$coa_name = sprintf('COA-%s.pdf', $LR['id']);

		switch ($_POST['a']) {
			case 'coa-create':
			case 'coa-create-pdf':

				// Genereate HTML then PDF
				$RES = $this->_viewCOA($REQ, $RES, $LR);
				$html = $RES->getBody()->__toString();

				// $coa_htm_file = _tempnam();
				// // Save some HTML (somehow?)
				// $subC = new self($this->_container);
				// $subR = $subC->__invoke($REQ, $RES, $ARG);
				// // var_dump($subR);

				// $html = $subR->getBody()->__toString();

				// var_dump($html);

				// exit;
				if ('coa-create-pdf' == $_POST['a']) {

					// _exit_text($html);
					$src_file = '/tmp/print.html';
					file_put_contents($src_file, $html);

					$cmd = [];
					$cmd[] = '/opt/openthc/lab/Matt/convert-to-pdf.sh';
					$cmd[] = escapeshellarg(sprintf('file://%s', $src_file));
					$cmd[] = '/tmp/print.pdf';
					$cmd[] = '2>&1';
					$cmd = implode(' ', $cmd);
					var_dump($cmd);

					$buf = shell_exec($cmd);

					var_dump($buf);

					$out_file = sprintf('%s/webroot/output/COA-%s.pdf', APP_ROOT, $LR['id']);
					var_dump($out_file);

					rename('/tmp/print.pdf', $out_file);

					$ret = sprintf('/output/COA-%s.pdf', $LR['id']);
					return $RES->withRedirect($ret, 303);

				}

				_exit_html($html);

			break;

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
			_exit_html('Not Implemented', 501);
			$S = new Sync($this->_container);
			return $S->__invoke(null, $RES, array('id' => $lab_result_id));
			break;
		case 'void':
			_exit_html('Not Implemented', 501);
			// $cre = new \OpenTHC\CRE($_SESSION['pipe-token']);
			// $res = $cre->qa()->delete($lab_result_id); // QAR['guid']);
			// var_dump($res);
			// exit;
			break;
		default:
			var_dump($_POST);
			var_dump($_FILES);
			die("not Handled");
		}

	}

	public function _viewCOA($REQ, $RES, $LR)
	{
		$dbc = $this->_container->DB;

		// $meta = json_decode($LR['meta'], true);
		// $data = [
		// 	'Sample' => $meta['Sample'],
		// 	'Result' => $meta['Result'],
		// 	'Product' => $meta['Product'],
		// 	'Strain' => $meta['Strain'],
		// ];

		// if (!empty($LR['license_id_lab'])) {
		// 	$x = \OpenTHC\License::findByGUID($LR['license_id_lab']);
		// 	if ($x) {
		// 		$data['Laboratory'] = $x->toArray();
		// 	}
		// }

		// // @todo whats the difference?
		// if (!empty($LR['license_id']))
		// {
		// 	$x = new \OpenTHC\License($dbc, $LR['license_id']);
		// 	if (!empty($x)) {
		// 		$data['License'] = $x->toArray();
		// 		$data['License']['phone'] = _phone_nice($data['License']['phone']);
		// 	}
		// }

		// $data['License_Client']['phone'] = _phone_nice($data['License_Client']['phone']);

		$file = 'coa/default.html';

		return $this->_container->view->render($RES, $file, $data);
	}
}
