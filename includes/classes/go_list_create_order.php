<? require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");
use Bitrix\Main\Config\Option;
require_once 'order_from_turn.php';

class go_list_create_order extends main_class
{
	/*
	Проход ХБ со списком заказов которые нужно оформить
	*/
	/*
	$obElements;
	$obSections;
	$iblock_trun;
	$HBID;
	$HBIDLog;
	$HBIDOrder;
	*/
	public function go_list_order()
	{
		CModule::IncludeModule('highloadblock');
		$entity_data_class = $this->GetHBConnect($this->HBIDOrder);
		if (empty($entity_data_class)) return 'Not HB';

		$rsData = $entity_data_class::getList([
			'select' => ['*'],
			'order' => [],
			'filter' => ['=UF_ORDER_ID' => '']
		]);

		while ($arData = $rsData->Fetch())
		{
			/* проверка, есть ли на сегодня уже очередь для заказов */
			$is_new = 0;
			if ($arData['UF_IS_NEW'])
			{
				$is_new = $arData['UF_IS_NEW'];
			}

			$order_from_turn = new order_from_turn();
			$create_order = $order_from_turn->create_order($arData['UF_ID_USER'], $is_new);

			if (is_int($create_order))
			{
				$data = ['UF_ORDER_ID' => $create_order];
				
				// Получить цену заказа, если она = 0 (все товары входят в стоимость подписки), записать статус = оплачено.
				if ($is_new)
				{
					CModule::IncludeModule('sale');
					$order = \Bitrix\Sale\Order::load($create_order);

					if ((float)$order->getPrice() == 0)
					{
						$text = $arData['UF_TEXT'];
						$text[] = 'Price all products do not more price subscription';
						$data['UF_PAID'] = '1';
						$data['UF_ACTIVE'] = '0';
						$data['UF_TEXT'] = $text;
					}
				}

				$entity_data_class::update($arData['ID'], $data);
			}
			else
			{
				$data = ['UF_ERROR' => '1'];
				$entity_data_class::update($arData['ID'], $data);

				$log = [
					'UF_ORDER_ERROR' => '1',
					'UF_TEXT' => $create_order,
					'UF_ID_USER' => $arData['UF_ID_USER']
				];
				$this->PushToLog($log);
			}

		}
	}
}