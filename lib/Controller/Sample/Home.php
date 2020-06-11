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

		$dbc = $this->_container->DB;

		$data = array(
			'Page' => [ 'title' => 'Samples' ],
			'sample_list' => [],
			'sample_stat' => [
				'100' => 0,
				'200' => 0,
				'401' => 0,
			]
		);

		$item_offset = 0;
		if (!empty($_GET['p'])) {
			$p = intval($_GET['p']) - 1;
			$item_offset = $p * 100;
		}

		$sql = <<<SQL
SELECT count(id) AS c, stat FROM lab_sample WHERE license_id = :l0 GROUP BY stat
SQL;
		$arg = [
			':l0' => $_SESSION['License']['id'],
		];
		$res = $dbc->fetchAll($sql, $arg);
		foreach ($res as $rec) {
			$data['sample_stat'][ $rec['stat'] ] = $rec['c'];
		}

		// Status
		$stat = $_GET['stat'];
		if (empty($stat) || ('*' == $stat)) {

			$sql = 'SELECT id, stat, meta FROM lab_sample WHERE license_id = :l0 AND flag & :f0 = 0 ORDER BY created_at DESC OFFSET %d LIMIT %d ';
			$sql = sprintf($sql, $item_offset, 100);
			$arg = array(
				':l0' => $_SESSION['License']['id'],
				':f0' => \App\Lab_Sample::FLAG_DEAD
			);

		} else {

			$sql = 'SELECT id, stat, meta FROM lab_sample WHERE license_id = :l0 AND stat = :s0 ORDER BY created_at DESC OFFSET %d LIMIT %d';
			$sql = sprintf($sql, $item_offset, 100);
			$arg = array(
				':l0' => $_SESSION['License']['id'],
				':s0' => $stat,
			);

		}

		// Get Sample Data
		$sample_list = $dbc->fetchAll($sql, $arg);
		array_walk($sample_list, function(&$v, $k) {
			$v['meta'] = json_decode($v['meta'], true);
		});

		$data['sample_list'] = $sample_list;

		// Get Matching Record Counts
		$sql_count = preg_replace('/SELECT.+FROM /', 'SELECT count(*) FROM ', $sql);
		$sql_count = preg_replace('/LIMIT.+$/', null, $sql_count);
		$sql_count = preg_replace('/OFFSET.+$/', null, $sql_count);
		$sql_count = preg_replace('/ORDER BY.+$/', null, $sql_count);

		$c = $dbc->fetchOne($sql_count, $arg);
		$Pager = new \App\UI\Pager($c, 100, $_GET['p']);

		$data['page_list_html'] = $Pager->getHTML();

		return $this->_container->view->render($RES, 'page/sample/home.html', $data);
	}

}
