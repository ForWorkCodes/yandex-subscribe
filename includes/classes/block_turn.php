<?
include 'move_turn.php';

class block_turn extends move_turn
{
	public function block()
	{
		$arTurns = $this->GetListMainSections();
		$arUsers = $this->GetListUsersFromSections($arTurns); // Массив пользователей
		if (empty($arUsers)) return;

		foreach ($arTurns as $arTurn)
		{
			if ( $arUsers[$arTurn['UF_ID_USER']]['UF_SUBSCRIBE'] == $this->id_subs_empty || empty($arUsers[$arTurn['UF_ID_USER']]) ) continue; // Нужно работать только с теми, кто подписан | на данном этапе отобраны все разделы где заполнена очередь, есть пользователь.
			$this->BlockFirstSection($arTurn['ID']); // Блокировка от изменения первого раздела/месяца
		}
	}

	public function block_one()
	{
		
	}

	protected function BlockFirstSection($parent)
	{
		$obSection = $this->obSections->GetList(
			['ID' => 'ASC'],
			['IBLOCK_ID' => $this->iblock_trun, 'SECTION_ID' => $parent, 'ACTIVE' => 'Y'],
			false,
			[],
			['nTopCount' => 1]
		);
		while ($arSection = $obSection->GetNext())
		{
			$this->obSections->Update($arSection['ID'], ['UF_BLOCKED' => '1']);
		}
	}
}
?>