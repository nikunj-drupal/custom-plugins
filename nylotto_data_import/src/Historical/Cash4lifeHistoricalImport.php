<?php

namespace Drupal\nylotto_data_import\Historical;

class Cash4lifeHistoricalImport
{
    //use DependencySerializationTrait;
    /**
     * Provides the data importing service.
     *
     * @var \Drupal\nylotto_data_import\ImportDataprovidesthedataimportservice
     */
    protected $historicalImport;


    public function cash4lifeDrawImport($content, &$context)
    {
        $historical_data = \Drupal::service('nylotto.historical_data');
        $message = 'Historical Import...';
        $results = array();
        $file_name = end($content);
        array_pop($content);
        foreach ($content as $drawKey => $drawValue) {
            $ids = \Drupal::entityQuery('node')
              ->condition('type', 'game')
              ->condition('field_game_id', 'cash4life')
              ->execute();
            $nid = array_shift($ids);
            $datestamp = strtotime($drawValue['date']);
            $results[] = $drawValue;
            $drawingdate = \Drupal::service('date.formatter')->format($datestamp, 'custom', 'Y-m-d');
            $query = \Drupal::entityQuery('drawing')
              ->condition('game', $nid)
              ->condition('field_draw_date', $drawingdate);
            $drawid = '';
            $drawids = $query->execute();
            if (!empty($drawids)) {
                $drawid = array_shift($drawids);
            }
            $entity = $historical_data->addDrawingData($nid, [
              'draw_date' => $drawingdate,
              'winning_numbers' => $drawValue['winning_numbers'],
              'bonus_ball' => $drawValue['bonus_ball'],
              'multiplier' => '',
              'draw_time' => '',
              'collect_time' => '',
              'jackpot' => $drawValue['jackpot'],
              'next_draw_date' => '',
              'next_jackpot' => '',
              'jackpot_winners' => $drawValue['winners'],
              'total_prizes' => '',
              'draw_number' => '',
              'file_name' => $file_name,
            ], $drawid);

            /**
             * There are 9 levels here. All local, and none have locations.
             */
            $historical_data->addWinnersData($entity, [
             'prize_label' => 'First',
             'winners' => $drawValue['local_winners'][0]['winners'],
             'amount' => $drawValue['local_winners'][0]['amount'],
             'wager_type' => ''
            ], false, false, $drawid);

            $historical_data->addWinnersData($entity, [
              'prize_label' => 'Second',
              'winners' => $drawValue['local_winners'][1]['winners'],
              'amount' => $drawValue['local_winners'][1]['amount'],
              'wager_type' => ''
            ], false, false, $drawid);

            $historical_data->addWinnersData($entity, [
              'prize_label' => 'Third',
              'winners' => $drawValue['local_winners'][2]['winners'],
              'amount' => $drawValue['local_winners'][2]['amount'],
              'wager_type' => ''
            ], false, false, $drawid);

            $historical_data->addWinnersData($entity, [
              'prize_label' => 'Fourth',
              'winners' => $drawValue['local_winners'][3]['winners'],
              'amount' => $drawValue['local_winners'][3]['amount'],
              'wager_type' => ''
            ], false, false, $drawid);

            $historical_data->addWinnersData($entity, [
              'prize_label' => 'Fifth',
              'winners' => $drawValue['local_winners'][4]['winners'],
              'amount' => $drawValue['local_winners'][4]['amount'],
              'wager_type' => ''
            ], false, false, $drawid);

            $historical_data->addWinnersData($entity, [
              'prize_label' => 'Sixth',
              'winners' => $drawValue['local_winners'][5]['winners'],
              'amount' => $drawValue['local_winners'][5]['amount'],
              'wager_type' => ''
            ], false, false, $drawid);

            $historical_data->addWinnersData($entity, [
              'prize_label' => 'Seventh',
              'winners' => $drawValue['local_winners'][6]['winners'],
              'amount' => $drawValue['local_winners'][6]['amount'],
              'wager_type' => ''
            ], false, false, $drawid);

            $historical_data->addWinnersData($entity, [
              'prize_label' => 'Eighth',
              'winners' => $drawValue['local_winners'][7]['winners'],
              'amount' => $drawValue['local_winners'][7]['amount'],
              'wager_type' => ''
            ], false, false, $drawid);

            $historical_data->addWinnersData($entity, [
              'prize_label' => 'Ninth',
              'winners' => $drawValue['local_winners'][8]['winners'],
              'amount' => $drawValue['local_winners'][8]['amount'],
              'wager_type' => ''
            ], false, false, $drawid);

            $local_winners = $drawValue['local_winners'];
            foreach ($local_winners as $local_winners_key => $local_winners_value) {
                $winning_location = $local_winners_value['winning_locations'];
                $pluginId = 'cash_4_life';
                $identify = 0;
                foreach ($winning_location as $winning_location_key => $winning_location_value) {
                    $prize_label = '';
                    if ($local_winners_value['prize'] == 'Jackpot') {
                        $prize_label = 'First';
                    } else {
                        $prize_label = $local_winners_value['prize'];
                    }
                    $winnerDataParagraph = $historical_data->getWinnerDataParagraph($entity->id(), $prize_label);
                    $identify++;
                    $locatwinning_identify = $identify.'_'.$pluginId;
                    // now we can add our location data
                    $historical_data->addLocationData($winnerDataParagraph, [
                      'address' => $winning_location_value['retailer_address'],
                      'name' => $winning_location_value['retailer_name'],
                      'city' => $winning_location_value['retailer_city'],
                      'play_type' => $winning_location_value['play_type'],
                      'winning_county' => $winning_location_value['winning_county'],
                      'winning_identify' => $locatwinning_identify
                    ]);
                }
            }

            $firstState = $drawValue['national_winners'][0]['state'];
            $secondState = $drawValue['national_winners'][1]['state'];
            $historical_data->addWinnersData($entity, [
             'prize_label' => 'First - National',
             'winners' => $drawValue['national_winners'][0]['winners'],
             'amount' => '',
             'state' => $historical_data->usStates($firstState),
             'wager_type' => ''
            ], true, false, $drawid);

            $historical_data->addWinnersData($entity, [
             'prize_label' => 'Second - National',
             'winners' => $drawValue['national_winners'][1]['winners'],
             'amount' => '',
             'state' => $historical_data->usStates($secondState),
             'wager_type' => ''
            ], true, false, $drawid);
        }
        $context['message'] = $message;
        $context['results'] = $results;
    }
}
