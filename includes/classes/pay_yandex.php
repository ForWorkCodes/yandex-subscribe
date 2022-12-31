<?
require_once 'main_class.php';
require_once 'order_from_turn.php';

class pay_yandex extends main_class
{
	public function PayCarFromSub($order_id)
	{
		$entity_data_class = $this->GetHBConnect($this->HBIDOrder);
		$rsData = $entity_data_class::getList([
			'select' => ['*'],
			'order' => [],
			'filter' => ['UF_ORDER_ID' => $order_id]
		]);

		while ($arData = $rsData->Fetch())
		{
			$text = $arData['UF_TEXT'];
			$text[] = 'Pay complete. ' . date('d.m.Y H:i:s');
			$data = [
				'UF_ACTIVE' => '0',
				'UF_PAID' => '1',
				'UF_TEXT' => $text
			];
			$entity_data_class::update($arData['ID'], $data);
		}

	}

	public function ErrorPay($order_id, $error)
	{
		$user_id = $this->GetUserID($order_id);
		$IsFirstOrder = 0;

		$entity_data_class = $this->GetHBConnect($this->HBIDOrder);
		$rsData = $entity_data_class::getList([
			'select' => ['*'],
			'order' => [],
			'filter' => ['UF_ORDER_ID' => $order_id]
		]);

		while ($arData = $rsData->Fetch())
		{
			if ($error == 'permission_revoked')
			{
				$error += ' - User canceled subscription';
			}
			else
			{
				$error = GetYandexError($error);
			}

			$IsFirstOrder = $arData['UF_IS_NEW'];

			$text = $arData['UF_TEXT'];
			$newText = $error . ' ' . date('d.m.Y H:i:s');
			$text[] = $newText;
			$finalText = $newText;

			if (count($text) >= (int)$this->subs_count_limit)
			{
				if ($IsFirstOrder != '1')
					$finalText = $newText.'. Too many request pay subscription, subscription is canceled | Слишком много попыток снятия средств, подписка отменена';
				else
					$finalText = $newText.'. Too many request pay subscription, we send the fragrance of the month | Слишком много попыток снятия средств, отправляем аромат месяца';

				$text[] = $finalText;

				$this->DelSubsUser($user_id, $finalText, $order_id, $IsFirstOrder);
				$this->PushMessageToUser($log['UF_TEXT'], $user_id);
				$data['UF_ACTIVE'] = '0';
			}
			else
			{
				$data['UF_ACTIVE'] = '1';
			}

			$log = [
				'UF_TEXT' => $finalText,
				'UF_ORDER_ID' => $order_id,
				'UF_ERROR_PAY' => '1',
				'UF_FATAL' => '1',
				'UF_ID_USER' => $user_id
			];
			$this->PushToLog($log);

			$data['UF_PAID'] = '0';
			$data['UF_TEXT'] = $text;
			$entity_data_class::update($arData['ID'], $data);

			// $log = [
			// 	'UF_TEXT' => $error,
			// 	'UF_ORDER_ID' => $order_id,
			// 	'UF_ERROR_PAY' => '1',
			// 	'UF_FATAL' => '1',
			// 	'UF_ID_USER' => $user_id
			// ];
			// $this->PushToLog($log);
		}

	}

	public function Save($order_id, $save_id, $idSub)
	{
		/*
		При оформлении подписки, данные для рекуррентных платежей нужно сохранить
		*/
		$user_id = $this->GetUserID($order_id);
		// $subs_id = $this->GetIDSubs($user_id);
		$dop['UF_NEW_USER'] = '1';
		$dop['UF_WARNING'] = $this->subs_text_new_user;
		$this->PushToHB($user_id, $idSub, $save_id);
		$this->ActiveSubs($user_id, $idSub, '', $dop);
	}

	public function DelSubsUser($user_id, $finalText, $order_id = '', $IsFirstOrder = '')
	{
		$this->PushToHB($user_id, '', '');
		if ($IsFirstOrder != '1') // Если это не первый заказ после покупки подписки
			$this->ActiveSubs($user_id, $this->id_subs_empty, 'del');

		CModule::IncludeModule("sale");
		CModule::IncludeModule("catalog");

		if ($order_id)
		{
			$order = \Bitrix\Sale\Order::loadByAccountNumber($order_id);
			$order->setField('STATUS_ID', 'C');
			$order->save();
		}

		if ($IsFirstOrder == '1') // Если это первый заказ после покупки подписки
		{
			$order_from_turn = new order_from_turn();		
			$order_from_turn->create_order_of_month($user_id);
		}

		return '1';
	}

	private function GetUserID($order_id)
	{
		CModule::IncludeModule('sale');
		$order = \Bitrix\Sale\Order::load($order_id);
		return $order->getUserId();
	}

	private function ActiveSubs($user_id, $subs_id, $action = '', $dop = [])
	{
		$user = new \CUser;
		$field['UF_SUBSCRIBE'] = $subs_id;
		$field['UF_PAY_ID'] = "";
		if ($action == 'del')
			$field['UF_DATE_SUBSCRIPTION'] = '';
		else
			$field['UF_DATE_SUBSCRIPTION'] = date('d.m.Y H:i:s');
		
		if ($dop)
		{
			foreach ($dop as $key => $p)
			{
				if ($key == 'UF_WARNING')
					$this->PushMessageToUser($p, $user_id);
				else
					$field[$key] = $p;
			}
		}

		$user->Update($user_id, $field);
		$strError .= $user->LAST_ERROR;
		if ($strError)
		{
			$log = [
				'UF_TEXT' => $strError,
				'UF_ID_USER' => $user_id
			];
			$this->PushToLog($log);
		}
	}

	private function PushToHB($user_id, $subs_id, $save_id)
	{
		CModule::IncludeModule('highloadblock');

		$entity_data_class = $this->GetHBConnect($this->HBPay);

		$rsData = $entity_data_class::getList([
			'select' => ['*'],
			'order' => [],
			'filter' => ['UF_ID_USER' => $user_id]
		]);
		while ($arData = $rsData->Fetch())
		{
			$issetID = $arData['ID'];
		}

		$data = [
			'UF_DATE' => date('d.m.Y H:i:s'),
			'UF_ID_USER' => $user_id,
			'UF_ID_PAY' => $save_id,
			'UF_ID_SUBS' => $subs_id,
			'UF_ACTIVE' => '1'
		];
		if (!$issetID)
			$entity_data_class::add($data);
		else
			$entity_data_class::update($issetID, $data);
	}
}