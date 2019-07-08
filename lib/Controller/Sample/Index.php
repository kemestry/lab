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
		$sql = 'SELECT id, meta FROM lab_sample WHERE company_id = :c0 AND flag & :f0 = 0 ORDER BY id DESC';
		$arg = array(':c0' => $_SESSION['gid'], ':f0' => \App\Lab_Sample::FLAG_DEAD);
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
