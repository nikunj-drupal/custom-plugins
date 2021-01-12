<?php

namespace Drupal\nylotto_data_import\Historical;

class NumbersHistoricalImport
{
    //use DependencySerializationTrait;
    /**
     * Provides the data importing service.
     *
     * @var \Drupal\nylotto_data_import\ImportDataprovidesthedataimportservice
     */
    protected $historicalImport;


    public function numbersDrawImport($content, &$context)
    {
        $historical_data = \Drupal::service('nylotto.historical_data');
        $message = 'Historical Import...';
        $results = array();
        $file_name = end($content);
        array_pop($content);
        foreach ($content as $drawKey => $drawValue) {
            $ids = \Drupal::entityQuery('node')
              ->condition('type', 'game')
              ->condition('field_game_id', 'numbers')
              ->execute();
            $nid = array_shift($ids);
            $datestamp = strtotime($drawValue['date']);
            $results[] = $drawValue;
            $drawingdate = \Drupal::service('date.formatter')->format($datestamp, 'custom', 'Y-m-d');
            if ($drawValue['draw_time'] == 'midday') {
                $drawingtime = 'Midday';
            }
            if ($drawValue['draw_time'] == 'evening') {
                $drawingtime = 'Evening';
            }
            $query = \Drupal::entityQuery('drawing')
              ->condition('game', $nid)
              ->condition('field_draw_date', $drawingdate)
              ->condition('field_draw_time', $drawingtime);
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
              'draw_time' => $drawingtime,
              'collect_time' => '',
              'jackpot' => '',
              'next_draw_date' => '',
              'next_jackpot' => '',
              'jackpot_winners' => $drawValue['winners'],
              'total_prizes' => $drawValue['total_prizes'],
              'draw_number' => $drawValue['draw_number'],
              'file_name' => $file_name,
            ], $drawid);

            // 7 levels of winners
            $local_winners = $drawValue['local_winners'];
            foreach ($local_winners as $local_winners_key => $local_winners_value) {
                if ($local_winners_value['wager_type'] == 'STRAIGHT PLAY' && $local_winners_value['prize'] == 'N/A') {
                    $historical_data->addWinnersData($entity, [
                        'prize_label' => 'N/A',
                        'winners' => $local_winners_value['winners'],
                        'amount' => '0',
                        'wager_type' => 'Straight Play',
                      ], false, false, $drawid);
                }
                if ($local_winners_value['wager_type'] == 'BOX PLAY' && $local_winners_value['prize'] == 'N/A') {
                    $historical_data->addWinnersData($entity, [
                      'prize_label' => 'N/A',
                      'winners' => $local_winners_value['winners'],
                      'amount' => '0',
                      'wager_type' => 'Box Play',
                    ], false, false, $drawid);
                }
                if ($local_winners_value['wager_type'] == 'PAIR PLAYS' && $local_winners_value['prize'] == 'FRONT PAIR') {
                    $historical_data->addWinnersData($entity, [
                      'prize_label' => 'Front Pair',
                      'winners' => $local_winners_value['winners'],
                      'amount' => '0',
                      'wager_type' => 'Pair Play',
                    ], false, false, $drawid);
                }
                if ($local_winners_value['wager_type'] == 'PAIR PLAYS' && $local_winners_value['prize'] == 'BACK PAIR') {
                    $historical_data->addWinnersData($entity, [
                      'prize_label' => 'Back Pair',
                      'winners' => $local_winners_value['winners'],
                      'amount' => '0',
                      'wager_type' => 'Pair Play',
                    ], false, false, $drawid);
                }
                if ($local_winners_value['wager_type'] == 'STRAIGHT / BOX' && $local_winners_value['prize'] == 'A. EXACT HIT') {
                    $historical_data->addWinnersData($entity, [
                      'prize_label' => 'Exact',
                      'winners' => $local_winners_value['winners'],
                      'amount' => '0',
                      'wager_type' => 'Straight/Box',
                    ], false, false, $drawid);
                }
                if ($local_winners_value['wager_type'] == 'STRAIGHT / BOX' && $local_winners_value['prize'] == 'B. BOX HIT') {
                    $historical_data->addWinnersData($entity, [
                      'prize_label' => 'Box',
                      'winners' => $local_winners_value['winners'],
                      'amount' => '0',
                      'wager_type' => 'Straight/Box',
                    ], false, false, $drawid);
                }
                if ($local_winners_value['wager_type'] == 'COMBINATION' && $local_winners_value['prize'] == 'N/A') {
                    $historical_data->addWinnersData($entity, [
                      'prize_label' => 'N/A',
                      'winners' => $local_winners_value['winners'],
                      'amount' => '0',
                      'wager_type' => 'Combination',
                    ], false, false, $drawid);
                }
            }
        }
        $context['message'] = $message;
        $context['results'] = $results;
    }
}
