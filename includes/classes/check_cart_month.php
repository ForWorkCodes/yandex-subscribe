<? require_once ($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");
use Bitrix\Main\Config\Option;

class check_cart_month
{
	private $iblock_month;
	private $obSections;
	private $obElements;

	public function __construct()
	{

		CModule::IncludeModule('iblock');
		CModule::IncludeModule('git.module');
		$this->obElements = new CIBlockElement();
		$this->obSections = new CIBlockSection();

		$this->iblock_month = Option::get('git.module', "Iblock_cart_month");
		if (!$this->iblock_month)
			$this->iblock_month = 17;
	}

	public function start()
	{
		$property_enums = CIBlockPropertyEnum::GetList(["DEF"=>"DESC", "SORT"=>"ASC"], ["IBLOCK_ID" => $this->iblock_month, "CODE" => "GENDER"]);
		while($enum_fields = $property_enums->GetNext())
		{
			$mList[$enum_fields['VALUE']] = $enum_fields['ID'];
		}

		foreach ($mList as $genderID)
		{
			$this->searchMonth($genderID);
		}

	}

	private function searchMonth($gender)
	{
		$i = 0;
		$obElements = $this->obElements->GetList( // Определить есть ли элементы
			[],
			['IBLOCK_ID' => $this->iblock_month],
			false,
			false,
			[]
		);
		while ($arElement = $obElements->GetNext())
		{
			$i++;
		}

		if ($i > 0)
		{

			$year = date('Y');
			$month = date('m');
			unset($obElements);
			$obElements = $this->obElements->GetList( // Есть ли текущий месяц?
				[],
				['IBLOCK_ID' => $this->iblock_month, 'PROPERTY_YEAR_VALUE' => $year, 'PROPERTY_MONTH_VALUE' => $month, 'PROPERTY_GENDER' => $gender],
				false,
				['nTopCount' => '1'],
				[]
			);
			while ($arElement = $obElements->GetNext())
			{
				$isset = true;
				/* Выключить активность у прошлого */
				$this->SwitchOffPastMonth($gender);
				/* -Выключить активность у прошлого- */
			}
			if ($isset)
				return;
			else
				$this->addMoth($gender);
		}
	}

	private function SwitchOffPastMonth($gender)
	{
		$curDate = new \DateTime();
		$year = $curDate->format('Y');
		$month = $curDate->format('m');

		$obElements = $this->obElements->GetList( // Найти месяц который меньше текущего
			['property_YEAR' => 'DESC', 'property_MONTH' => 'DESC'],
			['IBLOCK_ID' => $this->iblock_month, '<=PROPERTY_YEAR_VALUE' => $year, '<PROPERTY_MONTH_VALUE' => $month, 'PROPERTY_GENDER' => $gender],
			false,
			['nTopCount' => '1'],
			[]
		);
		while ($arElement = $obElements->GetNext())
		{
			$id = $arElement['ID'];
		}

		$this->obElements->Update($id, ['ACTIVE' => 'N']);
	}

	private function addMoth($gender)
	{
		$curDate = new \DateTime();
		// $curDate->sub(new DateInterval('P1M'));
		$year = $curDate->format('Y');
		$month = $curDate->format('m');
		$obElements = $this->obElements->GetList( // Найти месяц который меньше текущего
			['property_YEAR' => 'DESC', 'property_MONTH' => 'DESC'],
			['IBLOCK_ID' => $this->iblock_month, '<=PROPERTY_YEAR_VALUE' => $year, '<PROPERTY_MONTH_VALUE' => $month, 'PROPERTY_GENDER' => $gender],
			false,
			['nTopCount' => '1'],
			[]
		);

		while ($obElement = $obElements->GetNextElement())
		{
			$field = $obElement->GetFields();
			$field['PROPERTIES'] = $obElement->GetProperties();
			$arCart = $field;
		}

		if ($arCart)
		{
			$this->CreateMonth($arCart, $gender);
		}
		else
		{
			unset($obElements);
			$obElements = $this->obElements->GetList( // Найти месяц который больше текущего
				['property_YEAR' => 'DESC', 'property_MONTH' => 'DESC'],
				['IBLOCK_ID' => $this->iblock_month, '>=PROPERTY_YEAR_VALUE' => $year, '>PROPERTY_MONTH_VALUE' => $month, 'PROPERTY_GENDER' => $gender],
				false,
				['nTopCount' => '1'],
				[]
			);
			while ($obElement = $obElements->GetNextElement())
			{
				$field = $obElement->GetFields();
				$field['PROPERTIES'] = $obElement->GetProperties();
				$arCart = $field;
			}
			$this->CreateMonth($arCart, $gender);
		}
	}

	private function CreateMonth($arCart, $gender)
	{
		$year = date('Y');
		$month = date('m');

		$property_enums = CIBlockPropertyEnum::GetList(["DEF"=>"DESC", "SORT"=>"ASC"], ["IBLOCK_ID" => $this->iblock_month, "CODE" => "MONTH"]);
		while($enum_fields = $property_enums->GetNext())
		{
		  $mList[$enum_fields['VALUE']] = $enum_fields['ID'];
		}
		$property_enums = CIBlockPropertyEnum::GetList(["DEF"=>"DESC", "SORT"=>"ASC"], ["IBLOCK_ID" => $this->iblock_month, "CODE" => "YEAR"]);
		while($enum_fields = $property_enums->GetNext())
		{
		  $yList[$enum_fields['VALUE']] = $enum_fields['ID'];
		}

		$name = $arCart['PROPERTIES']['GENDER']['VALUE'];
		$arParams = array("replace_space"=>"-","replace_other"=>"-");
		$trans = Cutil::translit($name,"ru",$arParams);

		$PROP = [
			'YEAR' => $yList[$year],
			'MONTH' => $mList[$month],
			'ITEM_ID' => $arCart['PROPERTIES']['ITEM_ID']['VALUE'],
			'GENDER' => $gender,
			'QUOTE' => $arCart['PROPERTIES']['QUOTE']['VALUE'],
			'QUOTE_AUTHOR' => $arCart['PROPERTIES']['QUOTE_AUTHOR']['VALUE'],
			'BRAND' => $arCart['PROPERTIES']['BRAND']['VALUE'],
			'BRAND_TEXT' =>  Array("VALUE" => ["TEXT" => $arCart['PROPERTIES']['BRAND_TEXT']['~VALUE']['TEXT'], "TYPE" => $arCart['PROPERTIES']['BRAND_TEXT']['~VALUE']['TYPE']]),
		];
		$field = [
			'IBLOCK_ID' => $this->iblock_month,
			'NAME' => $month.' '.$year.' '.$arCart['PROPERTIES']['GENDER']['VALUE'],
			'CODE' => $month.'-'.$year.'-'.$trans.'-'.$arCart['ID'],
			'PROPERTY_VALUES' => $PROP,
			'DETAIL_PICTURE' => CFile::MakeFileArray($arCart['DETAIL_PICTURE'])
		];
		$this->obElements->Add($field);
	}
}