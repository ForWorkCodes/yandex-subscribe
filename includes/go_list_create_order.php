<?
ini_set('memory_limit', '1024M');
define("BX_CRONTAB_SUPPORT", true);
define("BX_CRONTAB", true);
$_SERVER["DOCUMENT_ROOT"] = realpath(dirname(__FILE__)."/../..");
@set_time_limit(0); // unlimited exec time
include 'classes/go_list_create_order.php';

$go_list_create_order = new go_list_create_order();
$go_list_order = $go_list_create_order->go_list_order();