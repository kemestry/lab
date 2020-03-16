<?php
/**
 * Show the Active Inventory Samples
 */

namespace App\Controller\Sample;

use Edoceo\Radix\DB\SQL;

class Home extends \OpenTHC\Controller\Base
{
	function __invoke($REQ, $RES, $ARG)
	{

		$data = array(
			'Page' => [ 'title' => 'Samples' ],
			'sample_list' => [],
			'sample_stat' => [
				'100' => 0,
				'200' => 0,
				'401' => 0,
			]
		);


		$sql = <<<SQL
SELECT count(id) AS c, stat FROM lab_sample WHERE license_id = :l0 GROUP BY stat
SQL;
		$arg = [
			':l0' => $_SESSION['License']['id'],
		];
		$res = SQL::fetch_all($sql, $arg);
		foreach ($res as $rec) {
			$data['sample_stat'][ $rec['stat'] ] = $rec['c'];
		}

		// Status
		$stat = $_GET['stat'];
		if (empty($stat) || ('*' == $stat)) {

			$sql = 'SELECT id, stat, meta FROM lab_sample WHERE license_id = :l0 AND flag & :f0 = 0 ORDER BY id DESC';
			$arg = array(
				':l0' => $_SESSION['License']['id'],
				':f0' => \App\Lab_Sample::FLAG_DEAD
			);

		} else {

			$sql = 'SELECT id, stat, meta FROM lab_sample WHERE license_id = :l0 AND stat = :s0 ORDER BY id DESC';
			$arg = array(
				':l0' => $_SESSION['License']['id'],
				':s0' => $stat,
			);

		}

		$sample_list = SQL::fetch_all($sql, $arg);
		array_walk($sample_list, function(&$v, $k) {
			$v['meta'] = json_decode($v['meta'], true);
		});

		$data['sample_list'] = $sample_list;

		return $this->_container->view->render($RES, 'page/sample/home.html', $data);
	}

}
