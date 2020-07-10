<?php
/**
 * QA Results
 */

namespace App;

use Edoceo\Radix\DB\SQL;
use Edoceo\Radix\Net\HTTP;

class Lab_Result extends \OpenTHC\SQL\Record
{
	const FLAG_SYNC = 0x00100000;
	const FLAG_MUTE = 0x04000000;

	protected $_table = 'lab_result';

	public $_Result;

	function __construct($dbc=null, $obj=null)
	{
		parent::__construct($dbc, $obj);

		if (!empty($this->_data['meta'])) {
			$this->_meta = json_decode($this->_data['meta'], true);
			$this->_Result = $this->_meta['Result'];
		}

	}

	/**
	 * Get the Metrics
	 */
	function getMetrics()
	{
		$sql = <<<SQL
SELECT lab_result_metric.*
, lab_metric.name AS lab_metric_name
FROM lab_result_metric
JOIN lab_metric ON lab_result_metric.lab_metric_id = lab_metric.id
WHERE lab_result_metric.lab_result_id = :lr0
SQL;

		// $res = $this->_dbc->fetchAll('SELECT * FROM lab_metric');
		// $res_

		$arg = [
			':lr0' => $this->_data['id']
		];

		$res = $this->_dbc->fetchAll($sql, $arg);
	}

	/**
	 * Returns the COA File Path for this Lab Result
	 * @return [type] [description]
	 */
	function getCOAFile()
	{
		if (empty($this->_data['id'])) {
			throw new \Exception('Invalid Result [LQR#044]');
		}

		// if (!empty($coa_file) && is_file($coa_file) && is_readable($coa_file)) {
		// 	return $coa_file;
		// }

		// One True Method
		$coa_hash = implode('/', str_split(sprintf('%08x', crc32($this->_data['id'])), 2));
		$coa_file = sprintf('%s/coa/%s/%s.pdf', APP_ROOT, $coa_hash, $this->_data['id']);

		return $coa_file;

	}

	function setCOAFile($pdf_source)
	{
		$coa_file = $this->getCOAFile();

		$coa_path = dirname($coa_file);
		if (!is_dir($coa_path)) {
			mkdir($coa_path, 0755, true);
		}

		// $pdf_source_type = mime_content_type($coa_file);
		// case 'application/pdf':
		// case 'image/png':
		// case 'image/jpeg':

		rename($pdf_source, $coa_file);

		// @todo Inspect the document


		// /usr/bin/pdf2txt
		// Then evaluate Text Content?

		// Evaluate PDF
		$cmd = array();
		$cmd[] = '/usr/bin/pdftk';
		$cmd[] = escapeshellarg($coa_file);
		$cmd[] = 'dump_data';
		// $buf = shell_exec(implode(' ', $cmd));

		// PageMediaRect: 0 0 612 792
		// PageMediaDimensions: 612 792
		// if (preg_match('//')) {
		// }

		// Extract information with GS
		// Fix the PageSize to be Letter if it's too small (like from CA)
		// See http://milan.kupcevic.net/ghostscript-ps-pdf/#refs
		// $cmd = array();
		// $cmd[] = '/usr/bin/gs';
		// $cmd[] = escapeshellarg($coa_file);
		// $buf = shell_exec(implode(' ', $cmd));
		// $pdf_info = _pdf_get_info($coa_file);
		// if ($pdf_info['MediaBox'] < 629)

		// Resize the Document?
		$cmd = array();
		$cmd[] = '/usr/bin/gs';
		$cmd[] = '-dNumRenderingThreads=4';
		$cmd[] = '-dNOPAUSE';
		$cmd[] = '-sDEVICE=pdfwrite';
		$cmd[] = '-sPAPERSIZE=letter';
		$cmd[] = '-dFIXEDMEDIA';
		$cmd[] = '-dPDFFitPage';
		$cmd[] = '-dCompatibilityLevel=1.4';
		$cmd[] = '-o';
		$cmd[] = escapeshellarg($pdf_output); //  /tmp/coa-output-final.pdf';
		// -sOutputFile=
		$cmd[] = escapeshellarg($pdf_middle);
		$cmd[] = '2>&1';
		// $buf = shell_exec(implode(' ', $cmd));
		// var_dump($buf); exit;
		// rename($pdf_middle, $pdf_output);

	}

	function tryCOAImport()
	{
		$tmp_file = _tmp_file();

		if (!empty($this->_Result['pdf_path'])) {
			$res = HTTP::get($this->_Result['pdf_path']);
			switch ($res['info']['http_code']) {
			case 200:
				file_put_contents($tmp_file, $res['body']);
				$this->setCOAFile($tmp_file);
				return true;
				break;
			default:
				_exit_text($res);
			}
		}

		return false;
	}

}
