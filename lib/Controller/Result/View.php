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
		if (empty($LR['id'])) {
			_exit_html(sprintf('QA Result Not Found, please <a href="/result/%s/sync">sync this result</a>', $id), 404);
		}

		$meta = json_decode($LR['meta'], true);
		// _ksort_r($meta);
		// _exit_text($meta);

		if (!empty($_POST['a'])) {
			return $this->_postHandler($REQ, $RES, $ARG, $LR, $meta);
		}

		$LR->getMetrics();

		// Get authoriative lab metrics
		$sql = 'SELECT * FROM lab_metric ORDER BY type,stat,name';
		$metricTab = $dbc->fetchAll($sql);
		// _exit_text($metricTab);
		$MetricList = array(); // This list is organized by the metric's type. I need it to make render the view eaiser.
		// I could have made it type-flat and made the view branch on the incorrect type. I think this would have made
		// it more difficult to refactor this for other RCEs.
		foreach ($metricTab as $index => $metric) {

			$type = $metric['type'];
			$key = $metric['id'];
			$metric_meta = json_decode($metric['meta'], true);

			// If the last character of CRE path is a deprecation symbol (null, '', '~', ...), then filter out
			// $creEngine = $_SESSION['rbe']['engine'];
			$creEngine = 'leafdata';
			$metricPath = $metric_meta['cre'][$creEngine]['path'];
			if (empty($metricPath) || substr($metricPath, -1) === '~') {
				continue;
			}

			// Promote the user's RCE metric path to the stub
			$metric_meta['stub'] = $metricPath;

			// Add metric to it's type list, in the Metric List
			if (empty($MetricList[$type])) $MetricList[$type] = array();

			$metric['meta'] = $metric_meta;

			$metric['result'] = $meta['Result'][$metricPath]; // Load the value from LD into our Metric Table
			$MetricList[$type][$key] = $metric;
		}

		// COA Formats
		if (!empty($_GET['f'])) {
			switch ($_GET['f']) {
			case 'coa':

				return $this->_viewCOA($REQ, $RES, $LR);
				break;

			case 'coa-create': // Canon
			case 'coa-generate': // Legacy
			case 'coa-pdf': // Legacy

				// Genereate HTML then PDF
				$RES = $this->_viewCOA($REQ, $RES, $LR);
				$html = $RES->getBody()->__toString();

				// unset($_POST['a']);

				// // Save some HTML (somehow?)
				// $subC = new self($this->_container);
				// $subR = $subC->__invoke($REQ, $RES, $ARG);
				// // var_dump($subR);

				// $html = $subR->getBody()->__toString();

				// var_dump($html);

				// exit;

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

				var_dump(sprintf('https://lab.openthc.com/output/COA-%s.pdf', $LR['id']));

				exit;

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
		$data = $this->loadSiteData($data);
		$data['MetricList'] = $MetricList;
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

		if ('paper' == $_GET['o']) {
			return $this->_container->view->render($RES, 'coa/base.html', $data);
		}

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
			$cre = new \OpenTHC\CRE($_SESSION['pipe-token']);
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
		$dbc = $this->_container->DB;

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
			$x = new \OpenTHC\License($dbc, $LR['license_id']);
			if (!empty($x)) {
				$data['License'] = $x->toArray();
				$data['License']['phone'] = _phone_nice($data['License']['phone']);
			}
		}

		$data['License_Client']['phone'] = _phone_nice($data['License_Client']['phone']);

		return $this->_container->view->render($RES, $page = 'coa/default.html', $data);
	}
}
