<?php
/**
 * View a QA Result
 */

namespace App\Controller\Result;

use Edoceo\Radix\Session;

use App\Lab_Sample;
use App\Lab_Result;

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
		$chk = $dbc->fetchRow('SELECT * FROM lab_result WHERE (id = :lr0 OR guid = :lr0)', [ ':lr0' => $id ]);
		$Lab_Result = new Lab_Result($dbc, $chk);

		if (empty($Lab_Result['id'])) {
			// var_dump($_SESSION); exit;
			_exit_html(sprintf('Lab Result Not Found, please <a href="/result/%s/sync">sync this result</a>', $id), 404);
		}

		$Lab_Sample = new Lab_Sample($dbc, $Lab_Result['lab_sample_id']);

		$Product = $dbc->fetchRow('SELECT * FROM product WHERE id = ?', [ $Lab_Sample['product_id'] ]);
		$ProductType = $dbc->fetchRow('SELECT * FROM product_type WHERE id = ?', [ $Product['product_type_id'] ]);
		$Strain = $dbc->fetchRow('SELECT * FROM strain WHERE id = ?', [ $Lab_Sample['strain_id'] ]);

		// Rewrite IDs to promote GUID into ID if present
		if (!empty($Lab_Result['guid']) && ($Lab_Result['guid'] != $Lab_Result['id'])) {
			$Lab_Result['id'] = $Lab_Result['guid'];
		}
		if (!empty($Lab_Sample['guid']) && ($Lab_Sample['guid'] != $Lab_Sample['id'])) {
			$Lab_Sample['id'] = $Lab_Sample['guid'];
		}

		// $meta = json_decode($Lab_Result['meta'], true);
		// $Lab_Result->getMetrics();

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
			':lr0' => $Lab_Result['id']
		];
		$res = $dbc->fetchAll($sql, $arg);
		foreach ($res as $rec) {
			$lab_metric_type_list[$rec['type']] = $rec['type'];
			$lab_result_metric_list[$rec['type']][$rec['lab_metric_id']] = $rec;
		}

		// Data
		$data = $this->loadSiteData();
		$data['Page'] = array('title' => 'Result :: View');
		$data['Sample'] = $Lab_Sample->toArray();
		$data['Result'] = $Lab_Result->toArray();
		$data['Product'] = $Product;
		$data['Product_Type'] = $ProductType;
		$data['Variety'] = $Strain;
		// $data['Sample'] = $LS->toArray();
		// $data['Sample']  = $meta['Sample'];
		// $data['Result']  = $meta['Result'];
		$data['Result']['coa_file'] = $Lab_Result->getCOAFile();
		if (!is_file($data['Result']['coa_file'])) {
			$data['Result']['coa_file'] = null;
		}
		$data['metric_type_list'] = $lab_metric_type_list;
		$data['MetricList'] = $lab_result_metric_list;

		$data['License'] = $dbc->fetchRow('SELECT * FROM license WHERE id = :l0', [ ':l0' => $Lab_Sample['license_id'] ]);
		$data['License_Source'] = $dbc->fetchRow('SELECT * FROM license WHERE id = :l0', [ ':l0' => $Lab_Sample['license_id_source'] ]);

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

		// if (!empty($Lab_Result['license_id_lab'])) {
		// 	$x = \OpenTHC\License::findByGUID($Lab_Result['license_id_lab']);
		// 	if ($x) {
		// 		$data['Laboratory'] = $x->toArray();
		// 	}
		// }

		$data['coa_upload_hash'] = _encrypt(json_encode(array(
			'a' => 'coa-upload',
			'r' => $Lab_Result['id'],
			'x' => $_SERVER['REQUEST_TIME'] + (86400 * 4)
		)));

		// https://stackoverflow.com/a/8940515
		// $data['share_mail_link'] = http_build_query(array(
		// 	'subject' => sprintf('Lab Results %s', $data['Result']['global_id']),
		// 	'body' => sprintf("\n\nHere is the link: https://%s/share/%s.html", $_SERVER['SERVER_NAME'], $Lab_Result['id']),
		// ), null, '&', PHP_QUERY_RFC3986);

		// _exit_text($data);

		if (!empty($_POST['a'])) {
			return $this->_postHandler($REQ, $RES, $Lab_Result, $data);
		}


		return $this->_container->view->render($RES, $file = 'page/result/view.html', $data);

	}

	/*
	 *
	 */
	function _postHandler($REQ, $RES, $LR, $data)
	{
		switch ($_POST['a']) {
			case 'coa-create':
			case 'coa-create-pdf':

				// Genereate HTML then PDF
				$RES = $this->_viewCOA($REQ, $RES, $data);
				$html = $RES->getBody()->__toString();

				// Save some HTML (somehow?)
				// $coa_htm_file = _tempnam();
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
					$cmd[] = sprintf('%s/bin/html2pdf.sh', APP_ROOT);
					$cmd[] = escapeshellarg(sprintf('file://%s', $src_file));
					$cmd[] = '/tmp/print.pdf';
					$cmd[] = '2>&1';
					$cmd = implode(' ', $cmd);
					var_dump($cmd);

					$buf = shell_exec($cmd);

					var_dump($buf);

					$out_file = sprintf('%s/webroot/output/COA-%s.pdf', APP_ROOT, $data['Result']['id']);
					var_dump($out_file);

					rename('/tmp/print.pdf', $out_file);

					$ret = sprintf('/output/COA-%s.pdf', $data['Result']['id']);
					return $RES->withRedirect($ret, 303);

				}

				_exit_html($html);

			break;

		case 'coa-download':
		case 'download-coa':

			if (empty($data['Result']['coa_file'])) {
				_exit_html('<h1>COA File Not Found [CRV#186]</h1>', 404);
			}

			if (!is_file($data['Result']['coa_file'])) {
				_exit_html('<h1>COA File Not Found [CRV#190]</h1>', 404);
			}

			if (filesize($data['Result']['coa_file']) < 512) {
				_exit_html('<h1>COA File Not Found [CRV#194]</h1>', 404);
			}

			$data['Result']['coa_type'] = mime_content_type($data['Result']['coa_file']);

			// File Type
			switch ($data['Result']['coa_type']) {
				case 'application/pdf':
					// Proper
				break;
				case 'image/jpeg':
					// OK
				break;
				default:
					_exit_html('<h1>COA File Type Not Supported [CRV#211]</h1>', 404);
			}

			header(sprintf('content-disposition: inline; filename="COA-%s.pdf"', $data['Result']['id']));
			header('content-transfer-encoding: binary');
			header(sprintf('content-type: %s', $data['Result']['coa_type']));

			readfile($data['Result']['coa_file']);

			exit(0);

			break;

		case 'coa-upload':

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

			// Connect to my self
			// _exit_text($data);

			$lab = new \OpenTHC\Service\OpenTHC('lab');
			$arg = [ 'form_params' => [
				'id' => $LR['id'],
				'license_id' => $_SESSION['License']['id'],
				'type' => $LR['type'],
				'name' => $LR['name'],
				'meta' => $data,
			]];
			$res = [];
			$chk = $lab->get('/api/v2015/result/' . $LR['id']);
			switch ($chk['code']) {
				case 200:
					// UPDATE
					$res = $lab->post('/api/v2015/result/' . $LR['id'], $arg);
				break;
				case 404:
					// INSERT
					$res = $lab->post('/api/v2015/result', $arg);
				break;
				default:
					_exit_text('Lab API Failure', 500);
			}
			if (200 != $res['code']) {
				// _exit_text(print_r($lab, true));
				throw new \Exception('Unexpected Response from Lab Portal');
			}

			return $RES->withRedirect(sprintf('/share/%s.html', $data['Result']['id']));

			break;
		case 'sync':
			_exit_html('Not Implemented', 501);
			$S = new Sync($this->_container);
			return $S->__invoke(null, $RES, array('id' => $data['Result']['id']));
			break;
		case 'void':
			_exit_html('Not Implemented', 501);
			// $cre = new \OpenTHC\CRE($_SESSION['pipe-token']);
			// $res = $cre->qa()->delete($data['Result']['id']);
			// var_dump($res);
			// exit;
			break;
		default:
			var_dump($_POST);
			var_dump($_FILES);
			die("not Handled");
		}
	}

	/**
	 * Create a Printable COA
	 */
	public function _viewCOA($REQ, $RES, $data)
	{
		// _exit_text($data);
		$file = 'coa/default.html';
		return $this->_container->view->render($RES, $file, $data);
	}
}
