<? require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");
use Bitrix\Main\Config\Option;
require_once 'main_class.php';

class order_from_turn extends main_class
{
	/*
	Оформление заказа из подписки
	Записть в ХБ со списком заказов которые нужно сделать
	Записать в очередь товар месяца
	*/
	/*
	$obElements;
	$obSections;
	$iblock_trun;
	$HBID;
	$HBIDLog;
	$HBIDOrder;
	*/

	public function create_order_of_month($user_id)
	{
		$Iblock_cart_month = Option::get('git.module', "Iblock_cart_month");
		$id_item = $this->GetCartMonth($Iblock_cart_month, $user_id);

		$arProducts[] = [
			'PRODUCT_ID' => $id_item,
			'QUANTITY' => 1
		];

		$PROPS['2'] = 'Y';
		$PROPS['3'] = 'Y';

		$params = [
			'DESCRIPTION' => Option::get('git.module', "subs_desc"),
			'PAYMENT' => Option::get('git.module', "subs_payment"),
			'USER_ID' => $user_id,
			'PRICE_ID' => Option::get('git.module', "price_turn"),
			'DELIVERY' => Option::get('git.module', "subs_delivery"),
			'PROPS' => $PROPS,
			'IS_NEW' => 'Y'
		];

		$obOrder = new \git\module\Order($params, $arProducts);
		$result = $obOrder->CreateOrder();

	}

	public function create_order($user_id, $is_new = '')
	{
		$arProducts = $this->GetArrayProducts($user_id);

		if (is_array($arProducts) && count($arProducts) > 0)
		{
			CModule::IncludeModule('git.module');
			// $price_subs = $this->GetPriceSubs($user_id);

			// foreach ($arProducts as $arProduct)
			// {
			// 	$fullPrice = $arProduct['PRICE']
			// }
			// $price = (float)$price_subs / count($arProducts);

			// foreach ($arProducts as &$arProduct)
			// {
			// 	$arProduct['PRICE'] =  $price;
			// 	$arProduct['CUSTOM_PRICE'] = 'Y';
			// }
			$PROPS['2'] = 'Y';
			if ($is_new)
			{
				$PROPS['3'] = 'Y';
			}
			$params = [
				'DESCRIPTION' => Option::get('git.module', "subs_desc"),
				'PAYMENT' => Option::get('git.module', "subs_payment"),
				'USER_ID' => $user_id,
				'PRICE_ID' => Option::get('git.module', "price_turn"),
				'DELIVERY' => Option::get('git.module', "subs_delivery"),
				'PROPS' => $PROPS,
				'IS_NEW' => $is_new
			];
			$obOrder = new \git\module\Order($params, $arProducts);
			$result = $obOrder->CreateOrder();
			if (is_int($result))
			{
				$this->RemoveFirstTurn($user_id);
			}
			return $result;
		}
		else
		{
			$log['UF_ID_USER'] = $user_id;
			$log['UF_TEXT'] = 'В первом месяце нет товаров';
			$this->PushToLog($log);
			return;
		}
	}
	
	private function RemoveFirstTurn($user_id)
	{
		$arMonths = $this->GetMonthUser($user_id);
		$arMonth = array_shift($arMonths);
		CIBlockSection::Delete($arMonth['ID']);
	}

	public function PushToOrderList($user_id, $is_new = '')
	{
		$entity_data_class = $this->GetHBConnect($this->HBIDOrder);
		if (empty($entity_data_class)) return 'Not HB';

		$date = new \DateTime();

		/* Есть ли сегодня уже записи */
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
				return;
		}
		/* -Есть ли сегодня уже записи- */

		$data = [
			'UF_ID_USER' => $user_id,
			'UF_ACTIVE' => 1,
			'UF_DATE_CREATE'  => $date->format('d.m.Y H:i:s')
		];
		if ($is_new)
		{
			$data['UF_IS_NEW'] = $is_new;
		}
		$result = $entity_data_class::add($data);
	}

	private function GetArrayProducts($user_id)
	{
		$arMonths = $this->GetMonthUser($user_id);
		if (!is_array($arMonths)) return;

		$arMonth = array_shift($arMonths);

		CModule::IncludeModule('git.module');
		$arItemsMonth = \git\module\MainModule::GetElements($arMonth['ID'], $this->iblock_trun); // Список элементов в первом месяце
		
		if (is_array($arItemsMonth) && count($arItemsMonth) > 0)
		{
			foreach ($arItemsMonth as $arItemMonth)
			{
				$arItemsId = $arItemMonth['PROPERTIES']['ID_ITEM']['VALUE'];
				$arItems[] = \git\module\MainModule::GetElement($arItemsId); // Список товаров
			}
		}
		
		if ($arItems)
		{
			foreach ($arItems as $arItem)
			{
				$arProducts[] = [
					'PRODUCT_ID' => $arItem['ID'],
					'QUANTITY' => 1
				];
			}
		}
		
		return $arProducts;
	}

	public function push_cart_to_month($user_id)
	{
		$main_section = $this->GetMainSectionUser($user_id);
		$Iblock_cart_month = Option::get('git.module', "Iblock_cart_month");

		if ($main_section && $Iblock_cart_month)
		{
			$id_section = $this->Create_new_turn([], $main_section);

			$id_item = $this->GetCartMonth($Iblock_cart_month, $user_id);
			$id_item_in_month = $this->Create_element_in_turn($id_item, $id_section);
			if ($id_item_in_month)
				return $id_section;
		}
	}

	public function PushCartMonthToItemMonth($item_id, $user_id)
	{
		$Iblock_cart_month = Option::get('git.module', "Iblock_cart_month");
		$new_cart = $this->GetCartMonth($Iblock_cart_month, $user_id);
		prr($new_cart);
		$this->obElements->SetPropertyValuesEx($item_id, false, ['ID_ITEM' => $new_cart]);
	}

	private function GetCartMonth($Iblock_cart_month, $user_id)
	{
		$month = date('m');
		$year = date('Y');

		$data_user = $this->GetDataUser($user_id);
		if ($data_user['UF_GENDER'] == '6')
		{
			$gender = 2108;
		}
		elseif($data_user['UF_GENDER'] == '7')
		{
			$gender = 2107;
		}

		$maxDayInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
		$obElements = $this->obElements->GetList(
			[],
			['IBLOCK_ID' => $Iblock_cart_month, 'PROPERTY_YEAR_VALUE' => $year, 'PROPERTY_MONTH_VALUE' => $month, 'PROPERTY_GENDER' => $gender, 'ACTIVE' => 'Y'],
			false,
			false,
			[]
		);
		while ($obElement = $obElements->GetNextElement())
		{
			$prop = $obElement->GetProperties();
			$cart_id = $prop['ITEM_ID']['VALUE'];
		}

		if ($cart_id)
			return $cart_id;
	}

	private function GetPriceSubs($user_id)
	{
		$arFilter = array("ID" => $user_id);
		$arParams["SELECT"] = array("*", "UF_SUBSCRIBE");

		$obRes = CUser::GetList($by,$desc,$arFilter, $arParams);
		while ($arRes = $obRes->Fetch())
		{
			$id_cart = $arRes['UF_SUBSCRIBE'];
		}

		if (!$id_cart)
		{
			$log = [
				'UF_ID_USER' => $user_id,
				'UF_ORDER_ERROR' => '1',
				'UF_NOW_DATE' => date(),
				'UF_TEXT' => 'User is not subscribe',
				'UF_FATAL' => '1'
			];
			$this->PushToLog($log);
			return;
		}

		$arPrice = \Bitrix\Catalog\PriceTable::getList([
		  "select" => ["*"],
		  "filter" => [
		       "=PRODUCT_ID" => $id_cart,
		  ],
		   "order" => []
		])->fetchAll();
		foreach ($arPrice as $price)
		{
			return $price['PRICE'];
		}
	}

}