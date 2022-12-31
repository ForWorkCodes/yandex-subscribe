<?
ini_set('memory_limit', '1024M');
define("BX_CRONTAB_SUPPORT", true);
define("BX_CRONTAB", true);
$_SERVER["DOCUMENT_ROOT"] = realpath(dirname(__FILE__)."/../..");
@set_time_limit(0); // unlimited exec time

include 'classes/push_pay.php';
include 'yandex/lib/autoload.php'; 

$push_pay = new push_pay();
$StartMoveToPay = $push_pay->StartMoveToPay();