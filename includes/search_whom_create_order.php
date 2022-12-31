<?
ini_set('memory_limit', '1024M');
define("BX_CRONTAB_SUPPORT", true);
define("BX_CRONTAB", true);
$_SERVER["DOCUMENT_ROOT"] = realpath(dirname(__FILE__)."/../..");
@set_time_limit(0); // unlimited exec time
include 'classes/search_whom_create_order.php';

$search_whom_create_order = new search_whom_create_order();
$Search = $search_whom_create_order->Search();