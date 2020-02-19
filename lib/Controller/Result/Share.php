<?php
/**
 * View a Result
 */

namespace App\Controller\Result;

use Edoceo\Radix\DB\SQL;
use Edoceo\Radix\Net\HTTP;

class Share extends \OpenTHC\Controller\Base
{
	function __invoke($REQ, $RES, $ARG)
	{
		if (preg_match('/^(.+)\.(html|json|pdf|png)$/', $ARG['id'], $m)) {
			$ARG['id'] = $m[1];
			$ext = trim($m[2], '.');
		}

		if ('QATEST.ABC123' == $ARG['id']) {
			$data = array(
				'Page' => array('title' => 'Example QA Detail')
			);
			return $this->_container->view->render($RES, 'page/share/example.html', $data);
		}

		$QAR = new \App\Lab_Result($ARG['id']);
		if (empty($QAR['id'])) {
			$data = array(
				'Page' => array('title' => 'Not Found [CRS#030]'),
				'lab_result_id' => null, // $ARG['id'],
			);
			$RES = $RES->withStatus(404);
			return $this->_container->view->render($RES, 'page/result/404.html', $data);
		}

		$meta = json_decode($QAR['meta'], true);
		if (empty($meta)) {
			$data = array(
				'Page' => array('title' => 'Not Found [CRS#039]'),
				'lab_result_id' => $QAR['id'],
			);
			$RES = $RES->withStatus(404);
			return $this->_container->view->render($RES, 'page/result/404.html', $data);
		}

		$data = array();
		$data['Page'] = array('title' => 'Result :: View');
		$data['Result'] = $meta['Result'];
		unset($data['Result']['coa_file']);

		$coa_file = $QAR->getCOAFile();
		if (!empty($coa_file) && is_file($coa_file) && is_readable($coa_file)) {
			$data['Result']['coa_file'] = $coa_file;
		}

		$data['Sample'] = $meta['Sample'];
		if (empty($data['Sample']['id'])) {
			$data['Sample']['id'] = '- Not Found -';
			$data['Sample']['id'] = $data['Result']['global_for_inventory_id'];
		}


		$data['Product'] = $meta['Product'];
		if (empty($data['Product']['name'])) {
			$data['Product']['name'] = '- Not Found -';
		}

		$data['Strain']  = $meta['Strain'];

		switch ($ext) {
		case '':
		case 'html':
			// Nothing
			break;
		case 'json':
			unset($data['Page']);
			$data = $this->_data_clean($data);
			_ksort_r($data);
			return $RES->withJSON($data, 200, JSON_PRETTY_PRINT);
		case 'pdf':

			if (empty($data['Result']['coa_file'])) {
				_exit_text('PDF Copy of COA Not Found, please contact the supplier or laboratory', 404);
			}

			$coa_name = sprintf('COA-%s.pdf', $QAR['id']);

			header(sprintf('content-disposition: inline; filename="%s"', $coa_name));
			header('content-transfer-encoding: binary');
			header('content-type: application/pdf');

			readfile($data['Result']['coa_file']);

			exit(0);

			break;

		case 'png':

			$qrCode = new \Endroid\QrCode\QrCode(sprintf('https://%s/share/%s.html', $_SERVER['SERVER_NAME'], $ARG['id']));

			$coa_name = sprintf('%s.png', $ARG['id']);

			// Generate a QR Code pointing to this page
			header(sprintf('content-disposition: inline; filename="%s"', $coa_name));
			header('content-transfer-encoding: binary');
			header('content-type: image/png');

			echo $qrCode->writeString();

			exit(0);

			break;

		}

		$data['share_mail_link'] = http_build_query(array(
			'subject' => sprintf('QA Results %s', $data['Result']['global_id']),
			'body' => sprintf("\n\nHere is the link: https://lab.openthc.org/share/%s.html", $data['Result']['global_id']),
		), null, '&', PHP_QUERY_RFC3986);

		//_exit_text($data);

		return $this->_container->view->render($RES, 'page/result/share.html', $data);

	}

	function _data_clean($data)
	{
		unset($data['Product']['allergens']);
		unset($data['Product']['contains']);
		unset($data['Product']['cost']);
		unset($data['Product']['external_id']);
		unset($data['Product']['global_strain_id']);
		unset($data['Product']['global_user_id']);
		unset($data['Product']['ingredients']);
		unset($data['Product']['serving_num']);
		unset($data['Product']['serving_size']);
		unset($data['Product']['storage_instructions']);
		unset($data['Product']['total_marijuana_in_grams']);
		unset($data['Product']['value']);
		unset($data['Result']['cbd']);
		unset($data['Result']['coa_file']);
		unset($data['Result']['external_id']);
		unset($data['Result']['for_inventory_id']);
		unset($data['Result']['global_user_id']);
		unset($data['Result']['sum']);
		unset($data['Result']['thc']);
		unset($data['Sample']['additives']);
		unset($data['Sample']['cost']);
		unset($data['Sample']['external_id']);
		unset($data['Sample']['global_user_id']);
		unset($data['Sample']['is_initial_inventory']);
		unset($data['Sample']['lab_result_file_path']);
		unset($data['Sample']['legacy_id']);
		unset($data['Sample']['serving_num']);
		unset($data['Sample']['serving_size']);
		unset($data['Sample']['total_marijuana_in_grams']);
		unset($data['Sample']['value']);
		unset($data['Strain']['external_id']);
		//_ksort_r($data);
		return $data;
	}
}
