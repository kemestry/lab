<?php
/**
 * Show Result List
 */

namespace App\Controller\Result;

class Home extends \OpenTHC\Controller\Base
{
	function __invoke($REQ, $RES, $ARG)
	{
		$data = array(
			'Page' => array('title' => 'Lab Results'),
			'sync_want' => false,
			'result_list' => array(),
			'result_stat' => [
				'100' => 0,
				'200' => 0,
				'400' => 0,
			]
		);

		$dbc = $this->_container->DB;

		$sql_limit = 100;
		$sql_offset = 0;
		if (!empty($_GET['p'])) {
			$p = intval($_GET['p']) - 1;
			$sql_offset = $p * 100;
		}




		$sql = <<<SQL
SELECT count(id) AS c, stat
FROM lab_result
--  LEFT JOIN lab_result_license ON lab_result.id = lab_result_license.lab_result_id
WHERE lab_result.license_id = :l0
--  OR lab_result_license.license_id = :l0
GROUP BY stat
ORDER BY stat
OFFSET $sql_offset
LIMIT $sql_limit
SQL;
		$arg = array(':l0' => $_SESSION['License']['id']);
		$res = $dbc->fetchAll($sql, $arg);
		foreach ($res as $rec) {
			$data['result_stat'][ $rec['stat'] ] = $rec['c'];
		}
		// _exit_text($data);


		// Stuff my Company is linked to?
		$sql = <<<SQL
SELECT lab_result.*
FROM lab_result
--   LEFT JOIN lab_result_license ON lab_result.id = lab_result_license.lab_result_id
WHERE lab_result.license_id = :l0
--   OR lab_result_license.license_id = :l0
ORDER BY created_at DESC, lab_result.id
OFFSET $sql_offset
LIMIT $sql_limit
SQL;

		$arg = array(':l0' => $_SESSION['License']['id']);
		$res = $dbc->fetchAll($sql, $arg);
		foreach ($res as $rec) {

			$QAR = new \App\Lab_Result($dbc, $rec);

			$rec['meta'] = \json_decode($rec['meta'], true);

			$rec['coa_file'] = $QAR->getCOAFile();

			// Try to Read first from META -- our preferred data
			$rec['created_at'] = _date('m/d/y', $rec['created_at']);
			$rec['thc'] = $rec['meta']['Result']['thc'] ?: '-';
			$rec['cbd'] = $rec['meta']['Result']['cbd'] ?: '-';
			$rec['sum'] = $rec['meta']['Result']['sum'] ?: '-';
			$rec['testing_status'] = $rec['meta']['Result']['testing_status'];
			$rec['status'] = $rec['meta']['Result']['status'];

			$t = array();
			$x = $rec['meta']['Result']['batch_type'];
			$t[] = $x;

			$x = $rec['meta']['Result']['type'];
			$t[] = $x;

			$x = $rec['meta']['Result']['intermediate_type'];
			$t[] = $x;
			$rec['type'] = trim(implode('/', $t), '/');
			$rec['type_nice'] = $rec['meta']['Product']['type_nice'];
			if (empty($rec['type_nice'])) {
				$rec['type_nice'] = $rec['meta']['Result']['type_nice'];
			}
			if (empty($rec['type_nice'])) {
				$rec['type_nice'] = $rec['type'];
			}

			$stat = array();
			if (!empty($rec['coa_file'])) {
				if (is_file($rec['coa_file'])) {
					$stat[] = ' <i class="far fa-file-pdf"></i>';
				} else {
					$stat[] = ' <i class="far fa-file-pdf text-danger"></i>';
				}
			}

			$x = sprintf('%s/%s', $rec['testing_status'], $rec['status']);
			switch ($x) {
			case '/failed':
			case 'completed/failed':
				$stat[] = '<i class="fas fa-check-square" style="color: var(--red);"></i>';
				break;
			case 'completed/passed':
				$stat[] = '<i class="fas fa-check-square" style="color: var(--green);"></i>';
				break;
			case 'in_progress/failed':
				$stat[] = '<i class="fas fa-clock" style="color: var(--gray);"></i>';
				$stat[] = '<i class="fas fa-check-square" style="color: var(--red);"></i>';
				break;
			case 'in_progress/passed':
				$stat[] = '<i class="fas fa-clock"></i> <i class="fas fa-check-square" style="color: var(--green);"></i>';
				break;
			case 'not_started/failed':
				$stat[] = '<i class="fas fa-clock"></i>';
				$stat[] = '<i class="fas fa-check-square" style="color: var(--red);"></i>';
				break;
			default:
				$stat[] = h($x);
			}

			$rec['status_html'] = implode(' ', $stat);

			$rec['flag_sync'] = ($rec['flag'] & \App\Lab_Result::FLAG_SYNC);
			if (empty($rec['flag_sync'])) {
				$data['sync_want'] = true;
			}

			$data['result_list'][] = $rec;

			// Get Matching Record Counts
			$sql_count = preg_replace('/SELECT.+FROM /ms', 'SELECT count(*) FROM ', $sql);
			$sql_count = preg_replace('/LIMIT.+$/ms', null, $sql_count);
			$sql_count = preg_replace('/OFFSET.+$/ms', null, $sql_count);
			$sql_count = preg_replace('/ORDER BY.+$/ms', null, $sql_count);

			$c = $dbc->fetchOne($sql_count, $arg);
			$Pager = new \App\UI\Pager($c, $sql_limit, $_GET['p']);

			$data['page_list_html'] = $Pager->getHTML();

		}

		return $this->_container->view->render($RES, 'page/result/home.html', $data);

	}
}
