<?
ini_set('memory_limit', '1024M');
define("BX_CRONTAB_SUPPORT", true);
define("BX_CRONTAB", true);
$_SERVER["DOCUMENT_ROOT"] = realpath(dirname(__FILE__)."/../..");
@set_time_limit(0); // unlimited exec time

include 'classes/move_turn.php';
include 'classes/check_cart_month.php';
$obMove = new move_turn();
$result = $obMove->start();

$obCheck = new check_cart_month();
$checkResult = $obCheck->start();
?>