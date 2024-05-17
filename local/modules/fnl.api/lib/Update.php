<?php

namespace Fnl\Api;

use Bitrix\Main\Type\DateTime;

class Update
{
    const hlSeasons = 1;
    const hlCompetitions = 2;
    const hlTours = 3;
    const hlGame = 4;
    const hlPlayers = 5;
    const hlPlayerStat = 9;
    const hlTeam = 6;
    const hlEvents = 10;
    const hlTtable = 11;
    
    //Сезоны. Список доступных сезонов
    public static function seasons()
    {
        $arGet = [
            'format=json',
            'type=list'
        ];
        
        $arResult = Request::request('seasons', $arGet);
        
        if ( ! empty($arResult['seasons'])) {
            self::hl($arResult['seasons'], self::hlSeasons);
        }
        
        return "\Fnl\Api\Update::seasons();";
    }
    
    //Сезоны. Текущий сезон
    public static function seasonsCurrent()
    {
        $arGet = [
            'format=json',
            'type=current'
        ];
        
        $arResult = Request::request('seasons', $arGet);
        
        if ( ! empty($arResult['seasons'])) {
            self::hl($arResult['seasons'], self::hlSeasons);
        }
        
        return "\Fnl\Api\Update::seasonsCurrent();";
    }
    
    //Справочник. События
    public static function events()
    {
        $arGet = [
            'format=json',
            'type=list'
        ];
        
        $arResult = Request::request('events', $arGet);
        
        if ( ! empty($arResult)) {
            self::hl($arResult, self::hlEvents);
        }
        
        return "\Fnl\Api\Update::events();";
    }
    
    //Группы. Список
    public static function competitions()
    {
        $hl = new HL(self::hlSeasons);
        $arHl = $hl->getList();
        $payload = [];
        
        foreach ($arHl as $arItem) {
            $arGet = [
                'format=json',
                'season_id=' . $arItem['UF_ID']
            ];
            
            $arResult = Request::request('competitions', $arGet, $payload);
            if ( ! empty($arResult)) {
                foreach ($arResult as $key => &$item) {
                    if(empty($arItem['UF_ID'])){
                        unset($arResult[$key]);
                    }
                    $item['SEASON_ID'] = $arItem['UF_ID'];
                }
                self::hl($arResult, self::hlCompetitions);
            }
        }
        
        return "\Fnl\Api\Update::competitions();";
    }
    
    //Туры. Список
    public static function tours()
    {
        $hl = new HL(self::hlSeasons);
        $arSeasonHl = $hl->getList();
        $payload = [];
        
        $hlGroup = new HL(self::hlCompetitions);
        $arGroupHl = $hlGroup->getList();
        $payload = [];
        
        foreach ($arGroupHl as $arGroupItem) {
            foreach ($arSeasonHl as $arItem) {
                $arGet = [
                    'format=json',
                    'type=list',
                    'season_id=' . $arItem['UF_ID'],
                    'group=' . $arGroupItem['UF_ID']
                ];
                $arResult = Request::request('tours', $arGet, $payload);
                
                if ( ! empty($arResult['tours'])) {
                    foreach ($arResult['tours'] as &$item) {
                        $item['SEASON_ID'] = $arItem['UF_ID'];
                        $item['group'] = $arGroupItem['UF_ID'];
                    }
                    self::hl($arResult['tours'], self::hlTours);
                }
            }
        }
        
        return "\Fnl\Api\Update::tours();";
    }
    
    //Турнирная таблица | Список команд
    public static function ttable()
    {
        $hl = new HL(self::hlCompetitions);
        $arHl = $hl->getList();
        $payload = [];
        
        foreach ($arHl as $arItem) {
            if (empty($arItem['UF_SEASON_ID']) or empty($arItem['UF_ID'])) {
                continue;
            }
            $arGet = [
                'format=json',
                'type=list',
                'season_id=' . $arItem['UF_SEASON_ID'],
                'group=' . $arItem['UF_ID']
            ];
            
            $arResult = Request::request('ttable', $arGet);
            
            if ( ! empty($arResult)) {
                $arResultNew = [];
                foreach ($arResult as $arItem2) {
                    if ($arItem2['team']['id'] == false) {
                        continue;
                    }
                    $key = $arItem['UF_SEASON_ID'] . '_' . $arItem['UF_ID'] . '_' . $arItem2['team']['id'];
                    $arResultNew[$key] = $arItem2;
                    $arResultNew[$key]['season_id'] = $arItem['UF_SEASON_ID'];
                    $arResultNew[$key]['group'] = $arItem['UF_ID'];
                    $arResultNew[$key]['id'] = $key;
                }
                self::hl($arResultNew, self::hlTtable,
                    ['UF_SEASON_ID' => $arItem['UF_SEASON_ID'], 'UF_GROUP' => $arItem['UF_ID']]);
            }
        }
        
        return "\Fnl\Api\Update::ttable();";
    }
    
    //Команда. Краткая информация
    public static function team($id, $season_id, $tour_id)
    {
        /*$hl = new HL(self::hlTeam);
        $hl->setFilter(['UF_ID' => $id]);
        $arHl = $hl->getList();
        
        if (empty($arHl)) {*/
        $arGet = [
            'format=json',
            'type=info',
            'team_id=' . $id,
        ];
        
        $arResult = Request::request('teams', $arGet);
        
        if ( ! empty($arResult['team'])) {
            $arResult['team']['season_id'] = $season_id;
            $arResult['team']['tour_id'] = $tour_id;
            $arResult = [$id => $arResult['team']];
            self::hl($arResult, self::hlTeam);
        }
        //}
    }
    
    //Команда. Краткая информация
    public static function teams()
    {
        $hl = new HL(self::hlTeam);
        $arHl = $hl->getList();
        
        if ( ! empty($arHl)) {
            foreach ($arHl as $arItem) {
                $arGet = [
                    'format=json',
                    'type=info',
                    'team_id=' . $arItem['UF_ID'],
                ];
                
                $arResult = Request::request('team', $arGet);
                
                if ( ! empty($arResult['team'])) {
                    $arResult = [$arResult['team']['id'] => $arResult['team']];
                    self::hl($arResult['team'], self::hlTeam);
                }
            }
        }
        
        return "\Fnl\Api\Update::teams();";
    }
    
    //Команда. Детальная в сезоне
    public static function teamSeason()
    {
        $hl = new HL(self::hlTeam);
        $arTeams = $hl->getList();
        $hl = new HL(self::hlSeasons);
        $arSeason = $hl->getList();
        
        $arPlayers = [];
        
        foreach ($arTeams as $arItem) {
            foreach ($arSeason as $season) {
                $arGet = [
                    'format=json',
                    'type=info',
                    'team_id=' . $arItem['UF_ID'],
                    'season_id=' . $season['UF_ID'],
                ];
                $arResult = Request::request('teams', $arGet);
                
                if ( ! empty($arResult['team'])) {
                    
                    foreach ($arResult['team']['players'] as $player) {
                        $arPlayers[$player['playerid']] = $player;
                    }
                    $arResult = [$arResult['team']['id'] => $arResult['team']];
                    
                    self::hl($arResult, self::hlTeam);
                }
            }
        }
        
        if ( ! empty($arPlayers)) {
            self::playersAdd($arPlayers);
        }
        
        return "\Fnl\Api\Update::teamSeason();";
    }
    
    //Игры. Список
    public static function games()
    {
        $hl = new HL(self::hlTours);
        $arHl = $hl->getList();
        $payload = [];
        
        foreach ($arHl as $arItem) {
            if (empty($arItem['UF_SEASON_ID']) or empty($arItem['UF_ID'])) {
                continue;
            }
            $arGet = [
                'format=json',
                'type=list',
                'season_id=' . $arItem['UF_SEASON_ID'],
                'tour_id=' . $arItem['UF_ID']
            ];
            
            $arResult = Request::request('games', $arGet);
            
            if ( ! empty($arResult['games'])) {
                foreach ($arResult['games'] as $arGame) {
                    if ( ! empty($arGame['teams']['home'])) {
                        self::team($arGame['teams']['home'], $arItem['UF_SEASON_ID'], $arItem['UF_ID']);
                    }
                    if ( ! empty($arGame['teams']['away'])) {
                        self::team($arGame['teams']['away'], $arItem['UF_SEASON_ID'], $arItem['UF_ID']);
                    }
                }
                self::hl($arResult['games'], self::hlGame);
            }
        }
        
        return "\Fnl\Api\Update::games();";
    }
    
    //Игры. Информация о игре
    public static function gamesInfo()
    {
        $hl = new HL(self::hlGame);
        $arHl = $hl->getList();
        $payload = [];
        
        foreach ($arHl as $arItem) {
            $arGet = [
                'format=json',
                'type=info',
                'game_id=' . $arItem['UF_ID']
            ];
            
            $arResult = Request::request('games', $arGet);
            if ( ! empty($arResult['id'])) {
                $arInfo = [$arResult['id'] => $arResult];
                self::hl($arInfo, self::hlGame);
            }
        }
        
        return "\Fnl\Api\Update::gamesInfo();";
    }
    
    public static function playersList()
    {
        $hl = new HL(self::hlPlayers);
        $arHl = $hl->getList();
        
        return $arHl;
    }
    
    //Добавить игрока
    private static function playersAdd($arResult = [])
    {
        $hl = new HL(self::hlPlayers);
        $arHl = $hl->getList();
        
        foreach ($arHl as $arItem) {
            $arIsset[$arItem['UF_ID']] = $arItem;
        }
        
        foreach ($arResult as $arItem) {
            if (empty($arIsset[$arItem['playerid']])) {
                $arItem['id'] = $arItem['playerid'];
                $arInfo = [$arItem['id'] => $arItem];
                self::hl($arInfo, self::hlPlayers);
            }
        }
    }
    
    //Игрок. Краткая информация
    public static function players()
    {
        $hl = new HL(self::hlPlayers);
        $arHl = $hl->getList();
        $payload = [];
        
        foreach ($arHl as $arItem) {
            $arGet = [
                'format=json',
                'type=info'
            ];
            
            $arResult = Request::request('players', $arGet);
            if ( ! empty($arResult['id'])) {
                $arInfo = [$arResult['id'] => $arResult];
                self::hl($arInfo, self::hlPlayers);
            }
        }
        
        return "\Fnl\Api\Update::players();";
    }
    
    //Игрок. Детальная информация по сезону
    public static function playersSeason()
    {
        $hl = new HL(self::hlPlayers);
        $arPlayer = $hl->getList();
        
        $hl = new HL(self::hlSeasons);
        $arSeason = $hl->getList();
        
        foreach ($arPlayer as $arItem) {
            foreach ($arSeason as $season) {
                if (empty($arItem['UF_ID']) or empty($season['UF_ID'])) {
                    continue;
                }
                $arGet = [
                    'format=json',
                    'type=info',
                    'player_id=' . $arItem['UF_ID'],
                    'season_id=' . $season['UF_ID'],
                ];
                
                $arResult = Request::request('players', $arGet);
                if ( ! empty($arResult['player']['id'])) {
                    $arInfo = [$arResult['player']['id'] => $arResult['player']];
                    self::hl($arInfo, self::hlPlayers);
                }
                if ( ! empty($arResult['stat'])) {
                    foreach ($arResult['stat']['games'] as $gameStat) {
                        foreach ($gameStat['events'] as $arEvent) {
                            $arInfo = [
                                $gameStat['id'] => [
                                    'id'            => $gameStat['id'],
                                    'time_on_grass' => $arResult['stat']['time_on_grass'],
                                    'games_count'   => $arResult['stat']['games_count'],
                                    'game_id'       => $gameStat['id'],
                                    'teamid'        => $gameStat['teamid'],
                                    'player_id'     => $arItem['UF_ID'],
                                    'season_id'     => $season['UF_ID'],
                                    'event_id'      => $arEvent['event_id'],
                                    'event_count'   => $arEvent['count'],
                                ]
                            ];
                            
                            self::hl($arInfo, self::hlPlayerStat, [
                                'UF_PLAYER_ID' => $arItem['UF_ID'],
                                "UF_SEASON_ID" => $season['UF_ID'],
                                "UF_GAME_ID"   => $gameStat['id'],
                                "UF_EVENT_ID"  => $arEvent['event_id'],
                            ]);
                        }
                    }
                }
            }
        }
        
        return "\Fnl\Api\Update::playersSeason();";
    }
    
    public static function arrayFormat($arResult = [])
    {
        $newArray = [];
        foreach ($arResult as $id => $arItem) {
            $newArray[$id] = ['UF_ID' => $id];
            foreach ($arItem as $code => $value) {
                $ufCode = 'UF_' . mb_strtoupper($code);
                
                if ($ufCode == 'UF_DATE' or $ufCode == 'UF_BIRTHDAY') {
                    $objDateTime = DateTime::createFromPhp(new \DateTime($value));
                    $value = $objDateTime->toString();
                }
                
                if (is_array($value)) {
                    if ($code == 'lineup' or $code == 'events' or $code == 'games_events') {
                        $value = json_encode($value);
                        $newArray[$id][$ufCode] = $value;
                    }
                    if ($code == 'playerid') {
                        foreach ($value as $player) {
                            $newArray[$id][$ufCode][] = $player['playerid'];
                        }
                    } elseif ($code == 'players') {
                        foreach ($value as $player) {
                            $newArray[$id][$ufCode][] = $player['playerid'];
                        }
                    } elseif ($code == 'games') {
                        foreach ($value as $player) {
                            $newArray[$id][$ufCode][] = $player['id'];
                        }
                    } else {
                        foreach ($value as $code2 => $value2) {
                            if ( ! is_array($value2)) {
                                $newArray[$id][$ufCode . '_' . mb_strtoupper($code2)] = $value2;
                            } else {
                                foreach ($value2 as $code3 => $value3) {
                                    $newArray[$id][$ufCode . '_' . mb_strtoupper($code3)] = $value3;
                                }
                                continue;
                            }
                        }
                        $newArray[$id][$ufCode] = json_encode($value);
                    }
                } else {
                    $newArray[$id][$ufCode] = $value;
                }
            }
        }
        return $newArray;
    }
    
    public static function hl($arResult, $hlID, $arFilter = [])
    {
        $hl = new HL($hlID);
        if ( ! empty($arFilter)) {
            $hl->setFilter($arFilter);
        }
        $arHl = $hl->getList();
        
        $arResult = self::arrayFormat($arResult);
        
        foreach ($arHl as $itemHl) {
            $id = $itemHl['ID'];
            $arItemNew = $arResult[$itemHl['UF_ID']];
            unset($itemHl['ID']);
            
            if ($arItemNew) {
                if (array_unique([$itemHl, $arItemNew])) {
                    $hl->update($id, $arItemNew);
                    unset($arResult[$itemHl['UF_ID']]);
                }
            }
        }
        
        foreach ($arResult as $arItemNew) {
            $result = $hl->add($arItemNew);
            if ( ! $result->isSuccess()) {
                pre($hlID);
                pre($arItemNew);
                echo '<pre>';
                var_dump($result->getErrors());
                echo '</pre>';
            }
        }
    }
    
    public static function hlAddField($arParams)
    {
        
        if ($arParams['HL_ID'] == false or $arParams['CODE'] == false) {
            return false;
        }
        $userTypeEntity = new \CUserTypeEntity();
        
        $userTypeData = array(
            'ENTITY_ID'         => 'HLBLOCK_' . $arParams['HL_ID'],
            'FIELD_NAME'        => $arParams['CODE'],
            'USER_TYPE_ID'      => 'string',
            'XML_ID'            => '',
            'SORT'              => 500,
            'MULTIPLE'          => 'N',
            'MANDATORY'         => 'N',
            'SHOW_FILTER'       => 'N',
            'SHOW_IN_LIST'      => '',
            'EDIT_IN_LIST'      => '',
            'IS_SEARCHABLE'     => 'N',
            'SETTINGS'          => array(
                'DEFAULT_VALUE' => '',
                'SIZE'          => '20',
                'ROWS'          => '1',
                'MIN_LENGTH'    => '0',
                'MAX_LENGTH'    => '0',
                'REGEXP'        => '',
            ),
            'EDIT_FORM_LABEL'   => array(
                'ru' => '',
                'en' => '',
            ),
            'LIST_COLUMN_LABEL' => array(
                'ru' => '',
                'en' => '',
            ),
            'LIST_FILTER_LABEL' => array(
                'ru' => '',
                'en' => '',
            ),
            'ERROR_MESSAGE'     => array(
                'ru' => '',
                'en' => '',
            ),
            'HELP_MESSAGE'      => array(
                'ru' => '',
                'en' => '',
            ),
        );
        $userTypeId = $userTypeEntity->Add($userTypeData);
    }
}