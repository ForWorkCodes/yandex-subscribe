<? require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");
use Bitrix\Main\Config\Option;
require_once 'main_class.php';

class create_list_date_order extends main_class
{
	/*
	Сформировать даты когда списывать деньги
	Записать в ХБ
	*/
	/*
	$obElements;
	$obSections;
	$iblock_trun;
	$HBID;
	$HBIDLog;
	$HBIDOrder;
	*/
	private $countPlusMonth;

	public function create_list($user_id)
	{
		$this->countPlusMonth = 12;
		$dateSubsc = $this->GetDateSubscribe($user_id);
		$datas = $this->GetCurrentData($dateSubsc);
		
		return $this->PushDateToHB($datas, $user_id);		
	}

	private function GetCurrentData($dateSubsc)
	{

		$todayDate = new \DateTime();
		$today = (int)$todayDate->format('d');

		$daySubsc = (int)$dateSubsc->format('d');
		$timeSubsc = $dateSubsc->format('H:i:s');

		$month = (int)$todayDate->format('m');
		$year = (int)$todayDate->format('Y');

		if ($today > $daySubsc)
		{
			if ($month == 12)
			{
				$month = 1;
				$year++;
			}
			else
			{
				$month++;
			}
		}

		$maxMonth = $month + $this->countPlusMonth;
		$tmpDate = new \DateTime('01.'.$month.'.'.$year);

		for ($i=$month; $i < $maxMonth; $i++)
		{
			$curMonth = (int)$tmpDate->format('m');
			$curYear = (int)$tmpDate->format('Y');
			$tmpDay = $daySubsc;

			$maxDayInMonth = cal_days_in_month(CAL_GREGORIAN, $curMonth, $curYear);

			if ($daySubsc > $maxDayInMonth) // Если в этом месяце дней меньше чем в том, когда заказали
			{
				$tmpDay = $maxDayInMonth;
			} // Есть актуальное число в месце, теперь получить полную дату

			$datas[] = $tmpDay.'.'.$curMonth.'.'.$curYear.' '.$timeSubsc;

			$tmpDate->add(new DateInterval('P1M'));			
		}
		
		return $datas;
	}

	private function PushDateToHB($datas, $user_id)
	{
		$entity_data_class = $this->GetHBConnect($this->HBID);
		if (empty($entity_data_class)) return 'Not HB';

		$id_user_hb_data = $this->GetUserHbData($user_id, $entity_data_class);

		if ($id_user_hb_data)
		{
			$data = array(
				"UF_DATE_PAY" => $datas
			);
			$result = $entity_data_class::update($id_user_hb_data, $data);
		}

		return $result->GetID();

	}

	private function GetUserHbData($user_id, $entity_data_class = [])
	{
		if (empty($entity_data_class)) $entity_data_class = $this->GetHBConnect($this->HBID);
		if (empty($entity_data_class)) return 'Not HB';

		$rsData = $entity_data_class::getList(array(
		   "select" => array("*"),
		   "order" => array("ID" => "ASC"),
		   "filter" => array("UF_ID_USER" => $user_id)  // Задаем параметры фильтра выборки
		));

		while($arData = $rsData->Fetch())
		{
		   $arDataUser = $arData;
		}

		if (isset($arDataUser))
			return $arDataUser['ID'];
		else
			return $this->CreateUserHbData($user_id, $entity_data_class);
	}

	private function CreateUserHbData($user_id, $entity_data_class)
	{
		if (empty($entity_data_class)) $entity_data_class = $this->GetHBConnect($this->HBID);
		if (empty($entity_data_class)) return 'Not HB';

		// Массив полей для добавления
	   $data = array(
	      "UF_ID_USER" => $user_id
	   );

	   $result = $entity_data_class::add($data);
	   return $result->GetID();
	}

}