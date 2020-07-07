<?php
/**
 * B2B Transfer Incoming
 */

namespace App;

class B2B_Incoming extends \OpenTHC\SQL\Record
{
	// Copied from WeedTraQR lib/Inventory.php:21
	const FLAG_SAMPLE = 0x00000040;

	// Copied from OT Lab Portal lib/Lab_Result.php:16
	const FLAG_SYNC = 0x00100000;

	protected $_table = 'b2b_incoming';

	function addItem($B2BI)
	{
		if (!($B2BI instanceof B2B_Incoming_Item)) {
			throw new \Exception('Invalid Parameter');
		}

		$B2BI['transfer_id'] = $this->_data['id'];
		$B2BI->save();

		return $B2BI;

	}


}
