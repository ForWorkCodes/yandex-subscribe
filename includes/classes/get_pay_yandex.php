<?
$source = file_get_contents('php://input');
file_put_contents('text-'.date('H:i:s'), $source);

require_once 'pay_yandex.php';

$arSource = json_decode($source, true);
$obPay = new pay_yandex();

$tryPay = $arSource['object']['metadata']['BX_ORDER_NUMBER'];

if ($arSource['object']['status'] == 'succeeded' && $arSource['object']['payment_method']['saved'] && $arSource['object']['metadata']['SUBS'])
{// Если все успешно и это оплата подписки
	$id = $arSource['object']['payment_method']['id'];
	$idSub = $arSource['object']['metadata']['SUBS'];
	$payPush = $obPay->Save($tryPay, $id, $idSub);
}
if($arSource['object']['status'] == 'succeeded' && $arSource['object']['metadata']['ORDER_WITH_SUBS'])
{
	$PayCarFromSub = $obPay->PayCarFromSub($tryPay);
}
if($arSource['object']['status'] == 'canceled')
{
	$error = $arSource['object']['cancellation_details']['reason'];
	$ErrorPay = $obPay->ErrorPay($tryPay, $error);
}

require_once $_SERVER["DOCUMENT_ROOT"] . '/bitrix/tools/sale_ps_result.php';