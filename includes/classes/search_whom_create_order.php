<? require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");
use Bitrix\Main\Config\Option;
require_once 'order_from_turn.php';
require_once 'create_list_date_order.php';

class search_whom_create_order extends main_class
{
	/*
	$obElements;
	$obSections;
	$iblock_trun;
	$HBID;
	$HBIDLog;
	$HBIDOrder;
	*/
	
	private $turn_before_date;

	/*
	Получить всех пользователей которые оформили подписку, найти тех у кого настал срок блокировки/оформления заказа и выполнить нужное действие
	*/

	private function Start()
	{
		$this->turn_before_date = Option::get('git.module', "turn_before_date");
		if (empty($this->turn_before_date))
			$this->turn_before_date = 1;
	}

	public function Search()
	{
		$this->Start();
		$arUsers = $this->GetNeedUsers(); // Получение пользователей у которых оформлена подписка
		if (count($arUsers) > 0 && is_array($arUsers))
		{
			foreach ($arUsers as $arUser)
			{
				$create_list_date_order = new create_list_date_order();
				$create_list_date_order->create_list($arUser['ID']);	

				$check_date = $this->Check_date($arUser); // Получить статус даты
			}
		}
	}

	public function GetNeedUsers()
	{
		$this->CheckNewUsers();

		$filter = ['!UF_SUBSCRIBE' => ['', $this->id_subs_empty], '!UF_NEW_USER' => '1'];
        $obUser = \CUser::GetList(($by = "NAME"), ($order = "desc"), $filter);
        while ($fieldUser = $obUser->GetNext())
        {
        	$fieldUsers[] = $fieldUser;
        }
        return $fieldUsers;
	}

	private function CheckNewUsers()
	{
		$filter = ['!UF_SUBSCRIBE' => ['', $this->id_subs_empty], 'UF_NEW_USER' => '1']; // Все пользователи с любой оформленной подпиской
		$select = ['UF_DATE_SUBSCRIPTION'];
        $obUser = \CUser::GetList(($by = "NAME"), ($order = "desc"), $filter, ['SELECT' => $select]);
        while ($fieldUser = $obUser->GetNext())
        {
        	$today = new \DateTime();
        	$date = new \DateTime($fieldUser['UF_DATE_SUBSCRIPTION']);
        	$date->add(new DateInterval('P'.$this->subs_count_day.'D'));
        	if ($today > $date) // Спустя покупки подписки должно пройти n ней, после чего система отправляет товар из очереди или товар месяца
        	{
        		$user = new \CUser;
        		$user->Update($fieldUser['ID'], ['UF_NEW_USER' => '0']);
        		$new = '1';
        		$this->BlockTurn($fieldUser['ID']);
        		$this->PushToCrateOrder($fieldUser['ID'], $new);
        	}
        }
	}

	private function Check_date($user)
	{
		$user_id = $user['ID'];
		$entity_data_class = $this->GetHBConnect($this->HBID);
		$arDatas = $this->GetUserHbData($user_id, $entity_data_class);
		
		if ($arDatas[0]) // Получение дат списаний. Записи формируются в ХБ на год вперед
		{
			$todayDate = new \DateTime();
			$blockDate = new \DateTime($arDatas[0]);
			$currentDate = new \DateTime($arDatas[0]);
			$blockDate->sub(new DateInterval('P'.$this->turn_before_date.'D'));
		}
		else
		{
			$log['UF_ID_USER'] = $user_id;
			$log['UF_TEXT'] = 'Не расписаны даты списаний';
			$this->PushToLog($log);
			return;
		}

		if ((int)$todayDate->format('d') == (int)$blockDate->format('d'))
		{
			$this->BlockTurn($user_id); // Если есть товары, блокировать, если нет, пушить товар месяца
		}
		elseif ((int)$todayDate->format('d') == (int)$currentDate->format('d'))
		{
			$this->PushToCrateOrder($user_id); // Поставить товар в очередь на оформление
		}
	}

	private function BlockTurn($user_id)
	{
		$arMonths = $this->GetMonthUser($user_id, $arMonths);
		$this->CheckItem($user_id, $arMonths); // Проверка актуальности товара
		if (is_array($arMonths) && count($arMonths) > 0) // Если очередь заполнена
		{
			$arMonth = array_shift($arMonths);
			if (is_array($arMonth) && count($arMonth) > 0 && !$arMonth['UF_BLOCKED']) // Должен быть массив с очередью
			{
				$this->BlockMonth($arMonth['ID']);
			}
		}
		else
		{ // Пушить товар месяца
			$order_from_turn = new order_from_turn();
			$push_cart_to_month = $order_from_turn->push_cart_to_month($user_id);
			$this->BlockMonth($push_cart_to_month);
		}

	}

	private function PushToCrateOrder($user_id, $is_new = '')
	{
		$arMonths = $this->GetMonthUser($user_id);
		$this->CheckItem($user_id, $arMonths); // Проверка актуальности товара

		/* Есть ли сегодня уже записи */
		$todayEmptyList = 'Y';

		$entity_data_class = $this->GetHBConnect($this->HBIDOrder);
		$date = new \DateTime();

		$rsData = $entity_data_class::getList(array(
		   "select" => array("*"),
		   "order" => array("ID" => "DESC"),
		   "filter" => array("UF_ID_USER" => $user_id),
		   "limit" => 10
		));

		while($arData = $rsData->fetch())
		{
			$is_date = new \DateTime($arData['UF_DATE_CREATE']);
			if ($is_date->format('d.m.Y') == $date->format('d.m.Y'))
				$todayEmptyList = 'N';
		}
		/* -Есть ли сегодня уже записи- */
		
		if ( (!is_array($arMonths) && count($arMonths) == 0) && $todayEmptyList == 'Y') // Если очередь не заполнена
		{
			$order_from_turn = new order_from_turn();
			$push_cart_to_month = $order_from_turn->push_cart_to_month($user_id);
			$this->BlockMonth($push_cart_to_month);
		}

		if ($todayEmptyList == 'Y')
		{
			$order_from_turn = new order_from_turn();
			$create_order = $order_from_turn->PushToOrderList($user_id, $is_new);
		}
	}

	private function CheckItem($user_id, $arMonths)
	{
		CModule::IncludeModule('git.module');
		if ( (is_array($arMonths) && count($arMonths) > 0) ) // Если очередь не заполнена
		{
			$arMonth = array_shift($arMonths);
			$arItems = \git\module\MainModule::GetElements($arMonth['ID']);
			if (is_array($arItems) && count($arItems) > 0)
			{
				foreach ($arItems as $arItem)
				{
					$cart = \git\module\MainModule::GetElement($arItem['PROPERTIES']['ID_ITEM']['VALUE']);
					if (empty($cart))
					{
						$order_from_turn = new order_from_turn();
						$create_order = $order_from_turn->PushCartMonthToItemMonth($arItem['ID'], $user_id);
					}
				}
			}
		}
	}

	private function BlockMonth($id)
	{
		if ($id)
		{
			$arFields = ['UF_BLOCKED' => '1'];
			$result = $this->obSections->Update($id, $arFields);
		}
	}

	private function GetUserHbData($user_id, $entity_data_class = [])
	{
		if (empty($entity_data_class)) $entity_data_class = $this->GetHBConnect($this->HBID);
		if (empty($entity_data_class)) return 'Not HB';

		$rsData = $entity_data_class::getList(array(
		   "select" => array("*"),
		   "order" => array("ID" => "ASC"),
		   "filter" => array("UF_ID_USER" => $user_id)
		));

		while($arData = $rsData->fetch())
		{
		   $arDataUser = $arData;
		}

		if (isset($arDataUser))
			return $arDataUser['UF_DATE_PAY'];
	}

}