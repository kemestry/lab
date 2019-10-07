<?php
/**
 * Show the Active Inventory Samples
 */

namespace App\Controller\Sample;

use Edoceo\Radix\DB\SQL;

class Index extends \OpenTHC\Controller\Base
{
	function __invoke($REQ, $RES, $ARG)
	{

		$stat = $_GET['stat'];
		if (empty($stat)) {

			$sql = 'SELECT id, meta FROM lab_sample WHERE license_id = :l0 AND flag & :f0 = 0 ORDER BY id DESC';
			$arg = array(
				':l0' => $_SESSION['License']['id'],
				':f0' => \App\Lab_Sample::FLAG_DEAD
			);


		} else {

			switch ($stat)
			{
				case '100': // Active
					$flag = \App\Lab_Sample::FLAG_ACTIVE;
					break;

				case '200': // Completed
					$flag = \App\Lab_Sample::FLAG_FAILED | \App\Lab_Sample::FLAG_PASSED;
					break;

				case '410': // Void
					$flag = \App\Lab_Sample::FLAG_REJECT;
					break;

				case '*':	// all
					// 0x8000073 | flag = 0x8000073
					// $flag = 0x8000073;
					$flag = \App\Lab_Sample::FLAG_ACTIVE | \App\Lab_Sample::FLAG_FAILED | \App\Lab_Sample::FLAG_PASSED | \App\Lab_Sample::FLAG_REJECT;
					break;

				default:
					// flash, wrong stat
					return $RES->withRedirect('/sample');
			}

			$sql = 'SELECT id, meta FROM lab_sample WHERE license_id = :l0 AND flag | :f0 = :f0 ORDER BY id DESC';
			$arg = array(
				':l0' => $_SESSION['License']['id'],
				':f0' => $flag,
			);

		}
		$sample_list = SQL::fetch_all($sql, $arg);
		array_walk($sample_list, function(&$v, $k) {
			$v['meta'] = json_decode($v['meta'], true);
		});
		//uasort($sample_list, function($a, $b) {
		//	return strcmp($a['created_at'], $b['created_at']);
		//});
		// _exit_text($sample_list);

		$data = array(
			'Page' => array('title' => 'Samples'),
			'sample_list' => $sample_list,
		);

		return $this->_container->view->render($RES, 'page/sample/index.html', $data);
	}

}
