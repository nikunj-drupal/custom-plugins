<?php

namespace Drupal\nylotto_data_import\Historical;

use Drupal\node\Entity\Node;
use Drupal\nylotto_drawing\Entity\Drawing;

class QuickDrawHistoricalImport
{
    public static function quickDrawImport($content, &$context)
    {
        $message = 'Historical Import...';
        $results = array();
        $ids = \Drupal::entityQuery('node')
          ->condition('type', 'game')
          ->condition('title', 'Quick Draw')
          ->execute();
        $nid = array_shift($ids);
        foreach ($content as $drawKey => $drawValue) {
            $datestamp = strtotime($drawValue['draw_date']);
            $results[] = $drawValue;
            if ($drawValue['bonus'] == 'NULL') {
                $drawBonus = '';
            } else {
                $drawBonus = $drawValue['bonus'];
            }
            if ($drawValue['total_prizes'] == 'NULL') {
                $jackpot = '';
            } else {
                $jackpot = $drawValue['total_prizes'];
            }
            if ($drawValue['total_winners'] == 'NULL') {
                $jackpotWinners = '';
            } else {
                $jackpotWinners = $drawValue['total_winners'];
            }
            $drawingdate = \Drupal::service('date.formatter')->format($datestamp, 'custom', 'Y-m-d');
            $drawingtime = \Drupal::service('date.formatter')->format($datestamp, 'custom', 'H:i:s');
            $query = \Drupal::entityQuery('drawing')
              ->condition('game', $nid)
              ->condition('field_draw_date', $drawingdate)
              ->condition('field_draw_time', $drawingtime);
            $pid = '';
            $pids = $query->execute();
            if (!empty($pids)) {
                $pid = array_shift($pids);
            }
            if (empty($pid) || $pid == '') {
                $entity = Drawing::create([
                  'type' => 'drawing_data',
                  'field_draw_date' => $drawingdate,
                  'field_winning_numbers_txt' => $drawValue['winning_numbers'],
                  'field_multiplier' => $drawValue['extra'],
                  'field_bonus_ball' => $drawBonus,
                  'field_draw_number' => $drawValue['draw_number'],
                  'field_jackpot' => $jackpot,
                  'field_jackpot_winners' => $jackpotWinners,
                  'field_draw_time' => $drawingtime,
                  'field_file_name' => $drawValue['file_name'],
                  'game' =>[['target_id' => $nid]],
                ]);
                $entity->save();
            } else {
                $entity = entity_load('drawing', $pid);
                $entity->set('field_winning_numbers_txt', $drawValue['winning_numbers']);
                $entity->set('field_bonus_ball', $drawBonus);
                $entity->set('field_multiplier', $drawValue['extra']);
                $entity->set('field_draw_date', $drawingdate);
                $entity->set('field_draw_number', $drawValue['draw_number']);
                $entity->set('field_jackpot', $jackpot);
                $entity->set('field_jackpot_winners', $jackpotWinners);
                $entity->set('field_draw_time', $drawingtime);
                $entity->set('field_file_name', $drawValue['file_name']);
                $entity->save();
            }
        }
        $context['message'] = $message;
        $context['results'] = $results;
    }

    public function entityImportFinishedCallback($success, $results, $operations)
    {
        // The 'success' parameter means no fatal PHP errors were detected. All
        // other error management should be handled using 'results'.
        if ($success) {
            $message = \Drupal::translation()->formatPlural(
                count($results),
                'One post processed.',
                '@count posts processed.'
            );
        } else {
            $message = t('Finished with an error.');
        }
        drupal_set_message($message);
    }
}
