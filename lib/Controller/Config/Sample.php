<?php
/**
 * Configure Sample Stuff
 */

namespace App\Controller\Config;

class Sample extends \App\Controller\Base
{
	const BASE_OPTION_KEY = 'lab-sample-seq-format';

	function __invoke($REQ, $RES, $ARG)
	{

		// $default =
		$dbc = $this->_container->DB;

		switch ($_POST['a']) {
			case 'reset-seq0':
			case 'reset-seq1':
			case 'reset-seq2':
			case 'reset-seq3':

				var_dump($_POST);

				$i = substr($_POST['a'], -1);

				$s = sprintf('seq_%s_%d', $_SESSION['Company']['id'], $i );
				$s = strtolower($s);

				$d = intval($_POST[sprintf('sequence%d', $i)]);
				$d = max(1, $d);

				$res = $dbc->query(sprintf('DROP SEQUENCE IF EXISTS %s', $s));
				$res = $dbc->query(sprintf('CREATE SEQUENCE %s', $s ));

				$res = $dbc->query('SELECT setval(:s, :d, false)', [
					':s' => $s,
					':d' => $d,
				]);

				var_dump($res);

			break;
			case 'update-seq-format':
				$key = self::BASE_OPTION_KEY;
				$val = trim($_POST[$key]);
				$val = json_encode($val);
				$chk = $dbc->fetchRow('SELECT * FROM base_option WHERE key = :k', [ ':k' => $key ]);
				if (empty($chk)) {
					$dbc->insert('base_option', [
						'id' => _ulid(),
						'key' => self::BASE_OPTION_KEY,
						'val' => $val,
					]);
				} else {
					$dbc->update('base_option', [ 'val' => $val ], [ 'key' => $key ]);
				}
			break;
		}


		for ($idx=0; $idx<4; $idx++) {
			try {

				$s = sprintf('seq_%s_%d', $_SESSION['Company']['id'], $idx );
				$s = strtolower($s);
				$arg = [ ':s' => $s ];

				// $seq_data[$idx] = $dbc->fetchOne(sprintf('SELECT currval(%s)', $s));
				// $seq_data[$idx] = $dbc->fetchOne('SELECT currval(:s)', $arg);
				// $seq_data[$idx] = $dbc->fetchOne('SELECT nextval(:s)', $arg);
				$seq_data[$idx] = $dbc->fetchOne(sprintf('SELECT last_value FROM "%s"', $s));
			} catch (\Exception $e) {
				// Ignore
				// _exit_html($e->getMessage());
				$err = $e->getMessage();
				$seq_data[$idx] = '-not-set-';
			}
		}

		// $Company->setOption('sample-id-seq', '$YY$MA$SEQ_M');

		$data = $this->loadSiteData();
		$data['Page']['title'] = 'Config :: Samples';

		$val = $dbc->fetchOne('SELECT val FROM base_option WHERE key = :k', [ ':k' => self::BASE_OPTION_KEY ]);
		$data['seq_format'] = json_decode($val);

		$data['seq'] = [
			'YYYY' => date('Y'),
			'YY' => date('y'),
			'MM' => date('m'),
			'MA' => chr(64 + date('m')),
			'DD' => date('d'),
			'DDD' => sprintf('%03d', date('z') + 1),
			'HH' => date('H'),
			'II' => date('i'),
			'SS' => date('s'),
			'g' => $seq_data[0],
			'y' => $seq_data[1],
			'y6' => sprintf('%06d', $seq_data[1]),
			'q' => $seq_data[2],
			'q9' => sprintf('%06d', $seq_data[2]),
			'm' => $seq_data[3],
		];

		$file = 'page/config/sample.html';

		return $this->_container->view->render($RES, $file, $data);

	}
}
