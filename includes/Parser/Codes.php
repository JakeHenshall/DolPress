<?php
/**
 * Shared command-code tables for grammar 0.2.
 *
 * @package DolPress
 */

declare(strict_types=1);

namespace Nought\DolPress\Parser;

final class Codes {
	public const CORE = array(
		'TX',
		'CR',
		'FG',
		'BG',
		'UL',
		'IV',
		'HL',
		'LK',
		'BT',
		'TR',
		'IM',
		'HR',
		'WS',
		'WG',
		'WT',
		'WA',
		'WN',
		'WL',
		'WP',
		'WC',
		'WX',
		'WM',
		'WB',
		'SR',
		'TB',
		'PB',
		'PL',
		'LM',
		'RM',
		'HD',
		'FO',
		'ID',
		'FD',
		'BD',
		'WW',
		'BK',
		'SX',
		'SY',
		'CM',
		'AN',
		'MK',
		'CU',
		'PT',
		'CL',
		'DA',
		'CB',
		'LS',
		'MU',
		'HX',
		'MA',
		'SP',
		'SO',
		'HC',
	);

	public const PAIRED = array( 'TR' );

	public const STATE = array(
		'FG',
		'BG',
		'FD',
		'BD',
		'UL',
		'IV',
		'HL',
		'WW',
		'BK',
		'ID',
		'LM',
		'RM',
		'PL',
		'SX',
		'SY',
		'CM',
	);

	public const BLOCK = array(
		'HR',
		'IM',
		'TR',
		'WB',
		'WN',
		'WL',
		'WC',
		'WG',
		'SP',
		'SO',
		'HX',
		'HC',
		'PB',
		'HD',
		'FO',
		'DA',
		'CB',
		'LS',
	);
}
