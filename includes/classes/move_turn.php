<? require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");
use Bitrix\Main\Config\Option;

class move_turn
{
	protected $iblock_trun;
	protected $obSections;
	protected $obElements;
	protected $MESS;

	public function __construct()
	{
		CModule::IncludeModule('iblock');
		CModule::IncludeModule('git.module');
		$this->iblock_trun = Option::get('git.module', "turn_order");
		$this->obElements = new CIBlockElement();
		$this->obSections = new CIBlockSection();

		$this->MESS['MON_01'] = 'Январь';
		$this->MESS['MON_02'] = 'Февраль';
		$this->MESS['MON_03'] = 'Март';
		$this->MESS['MON_04'] = 'Апрель';
		$this->MESS['MON_05'] = 'Май';
		$this->MESS['MON_06'] = 'Июнь';
		$this->MESS['MON_07'] = 'Июль';
		$this->MESS['MON_08'] = 'Август';
		$this->MESS['MON_09'] = 'Сентябрь';
		$this->MESS['MON_10'] = 'Октябрь';
		$this->MESS['MON_11'] = 'Ноябрь';
		$this->MESS['MON_12'] = 'Декабрь';
	}

	public function start()
	{
		$arTurns = $this->GetListMainSections();
		$arUsers = $this->GetListUsersFromSections($arTurns); // Массив пользователей
		if (empty($arUsers)) return;

		foreach ($arTurns as $arTurn)
		{
			if ( $arUsers[$arTurn['UF_ID_USER']]['UF_SUBSCRIBE'] != $this->id_subs_empty || empty($arUsers[$arTurn['UF_ID_USER']]) ) continue; // Нужно работать только с теми, кто не подписан | на данном этапе отобраны все разделы где заполнена очередь, есть пользователь.
			// Раздел нужно выключить на время перестройки:
			$this->obSections->Update($arTurn['ID'], ['ACTIVE' => 'N']);
			// Зайти внутрь, создать новый месяц:
			$id_last_section = $this->CreateSectionAfterLast($arTurn['ID']);
			if (!$id_last_section)
			{
				$this->obSections->Update($arTurn['ID'], ['ACTIVE' => 'Y']);
				continue;
			}
			else
			{
				// Последовательно переносить элементы на месяц вперед
				$this->MigrateItems($arTurn['ID'], $id_last_section);
				$this->obSections->Update($arTurn['ID'], ['ACTIVE' => 'Y']);
			}

		}
	}

	protected function MigrateItems($parent, $id_last_section)
	{
		$next_section = $id_last_section;
		$obSectionsMigrate = $this->obSections->GetList(
			['ID' => 'DESC'],
			['IBLOCK_ID' => $this->iblock_trun, 'SECTION_ID' => $parent, 'ACTIVE' => 'Y', '!ID' => $id_last_section],
			false,
			[],
			[]
		);
		while ($arSectionsMigrate = $obSectionsMigrate->GetNext())
		{
			$arItems = git\module\MainModule::GetElements($arSectionsMigrate['ID'], $this->iblock_trun); // Получаем все элементы внутри раздела
			if (!empty($arItems) && count($arItems) > 0)
			{
				foreach ($arItems as $arItem)
				{
					$this->obElements->Update($arItem['ID'], ['IBLOCK_SECTION_ID' => $next_section]);
				}
			}
			$next_section = $arSectionsMigrate['ID'];
		}
		$this->DelEmptySection($parent);
	}

	protected function DelEmptySection($parent)
	{
		$obSearchEmpty = $this->obSections->GetList(
			['ID' => 'ASC'],
			['IBLOCK_ID' => $this->iblock_trun, 'SECTION_ID' => $parent, 'ACTIVE' => 'Y'],
			true,
			[],
			[]
		);

		while ($arSearchEmpty = $obSearchEmpty->GetNext())
		{
			if ((int)$arSearchEmpty['ELEMENT_CNT'] == 0)
			{
				$this->obSections->Delete($arSearchEmpty['ID']);
			}
		}
	}

	protected function CreateSectionAfterLast($parent)
	{
		$obSection = $this->obSections->GetList(
			['ID' => 'DESC'],
			['IBLOCK_ID' => $this->iblock_trun, 'ACTIVE' => 'Y', 'SECTION_ID' => $parent],
			false,
			['*', 'UF_*'],
			['nTopCount' => 1]
		);
		while ($arSection = $obSection->GetNext())
		{
			$data = $arSection;
		} // Последний месяц / раздел. Далее создать новый, пустой
		if (empty($data)) return;
		$arSection = $data;
		
		$month = $arSection['UF_MONTH'];
		$year = $arSection['UF_YEAR'];
		$stringTime = "01.".$month.".".$year." 00:00:00";

		$objDateTime = new \Bitrix\Main\Type\DateTime($stringTime);
		$objDateTime->add('1 month');

		$newYear = $objDateTime->format('Y');
		$newMonth = $objDateTime->format('m');
		$newMonthText = $this->MESS['MON_'.$newMonth];

		$fieldSe = [
			'IBLOCK_ID' => $this->iblock_trun,
			'ACTIVE' => 'Y',
			'NAME' => $newMonthText . ' ' . $newYear,
			'IBLOCK_SECTION_ID' => $parent,
			'UF_YEAR' => $newYear,
			'UF_MONTH' => $newMonth
		];
		return $this->obSections->Add($fieldSe);
	}

	protected function GetListMainSections()
	{ // Получить список разделов всех пользователей
		$obSection = $this->obSections->GetList(
			[],
			['IBLOCK_ID' => $this->iblock_trun, 'DEPTH_LEVEL' => 1],
			true,
			['*', 'UF_*'],
			['nTopCount' => 1000000]
		);
		while ($arSection = $obSection->GetNext())
		{
			if ( (int)$arSection['ELEMENT_CNT'] < 1 && $arSection['ELEMENT_CNT'] == '' ) continue;
			$arSections[$arSection['ID']] = $arSection;
		}
		return $arSections;
	}

	protected function GetListUsersFromSections($arTurns)
	{
		if (!$arTurns) return;

		foreach ($arTurns as $arTurn)
		{
			$id_user[] = $arTurn['UF_ID_USER'];
		}

		$arFilter = array("ID" => $id_user);
		$arParams["SELECT"] = array("*", "UF_SUBSCRIBE");

		$obRes = CUser::GetList($by,$desc,$arFilter, $arParams);
		while ($arRes = $obRes->Fetch())
		{
			if (!$arRes['UF_SUBSCRIBE'])
			{
				$arRes['UF_SUBSCRIBE'] = $this->id_subs_empty;
			}
			$arUsers[$arRes['ID']] = $arRes;
		}

		return $arUsers;
	}
}