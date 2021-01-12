<?php

namespace Drupal\nylotto_data_import\Historical;

class Pick10HistoricalImport
{
    //use DependencySerializationTrait;
    /**
     * Provides the data importing service.
     *
     * @var \Drupal\nylotto_data_import\ImportDataprovidesthedataimportservice
     */
    protected $historicalImport;


    public function pick10DrawImport($content, &$context)
    {
        $historical_data = \Drupal::service('nylotto.historical_data');
        $message = 'Historical Import...';
        $results = array();
        $file_name = end($content);
        array_pop($content);
        foreach ($content as $drawKey => $drawValue) {
            $ids = \Drupal::entityQuery('node')
              ->condition('type', 'game')
              ->condition('field_game_id', 'pick10')
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
              'bonus_ball' => '',
              'multiplier' => '',
              'draw_time' => '',
              'collect_time' => '',
              'jackpot' => '',
              'next_draw_date' => '',
              'next_jackpot' => '',
              'jackpot_winners' => $drawValue['winners'],
              'total_prizes' => $drawValue['total_prizes'],
              'draw_number' => $drawValue['draw_number'],
              'file_name' => $file_name,
            ], $drawid);

            $historical_data->addWinnersData($entity, [
              'prize_label' => 'First',
              'winners' => $drawValue['local_winners'][0]['winners'],
              'wager_type' => '',
              'amount' => '',
            ], false, false, $drawid);

            $historical_data->addWinnersData($entity, [
               'prize_label' => 'Second',
               'winners' => $drawValue['local_winners'][1]['winners'],
               'wager_type' => '',
               'amount' => '',
            ], false, false, $drawid);

            $historical_data->addWinnersData($entity, [
               'prize_label' => 'Third',
               'winners' => $drawValue['local_winners'][2]['winners'],
               'wager_type' => '',
               'amount' => '',
            ], false, false, $drawid);

            $historical_data->addWinnersData($entity, [
               'prize_label' => 'Fourth',
               'winners' => $drawValue['local_winners'][3]['winners'],
               'wager_type' => '',
               'amount' => '',
            ], false, false, $drawid);

            $historical_data->addWinnersData($entity, [
               'prize_label' => 'Fifth',
               'winners' => $drawValue['local_winners'][4]['winners'],
               'wager_type' => '',
               'amount' => '',
            ], false, false, $drawid);

            $historical_data->addWinnersData($entity, [
               'prize_label' => 'Sixth',
               'winners' => $drawValue['local_winners'][5]['winners'],
               'wager_type' => '',
               'amount' => '',
            ], false, false, $drawid);
        }
        $context['message'] = $message;
        $context['results'] = $results;
    }
}
