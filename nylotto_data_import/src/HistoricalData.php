<?php

namespace Drupal\nylotto_data_import;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\nylotto_drawing\Entity\Drawing;

/**
 * Prepares the salutation to the world.
 */
class HistoricalData
{
    use StringTranslationTrait;
    /**
     * Call this function win adding drawing data.
     * It will check for existing paragraphs and either update or add new ones.
     */
    public function addDrawingData($nid, $data, $pid)
    {
        if ($pid != '') {
            $entity = entity_load('drawing', $pid);
            return $this->updateDrawingData($nid, $entity, $data);
        } else {
            return $this->createDrawingData($nid, $data);
        }
    }

    /**
     * Returns the paragraph for drawing data.
     */
    public function getDrawingDataParagraph($nid, $date, $time = '')
    {
        // $drawDate = new \DateTime();
        // $drawDate->setTimeStamp(strtotime($date));
        // Check for a drawing data paragraph for this node.
        $query = \Drupal::entityQuery('drawing')
          ->condition('game', $nid)
          ->condition('field_draw_date', $date);

        if ($time !== '') {
            $query->condition('field_draw_time', $time);
        }
        $pids = $query->execute();
        return entity_load('drawing', array_values($pids)[0]);
    }

    /**
     * Update Drawing Data Paragraph plugin
     */
    public function updateDrawingData($nid, $entity, $data)
    {
        $entity->set('field_winning_numbers_txt', $data['winning_numbers']);
        $entity->set('field_bonus_ball', $data['bonus_ball']);
        //$entity->set('field_multiplier', $data['multiplier']);
        $entity->set('field_draw_time', $data['draw_time']);
        $entity->set('field_total_prizes', $data['total_prizes']);
        $entity->set('field_collect_time', $data['collect_time']);
        $entity->set('field_jackpot', $data['jackpot']);
        $entity->set('field_next_draw_date', $data['next_draw_date']);
        $entity->set('field_next_jackpot', $data['next_jackpot']);
        $entity->set('field_draw_number', $data['draw_number']);
        $entity->set('field_jackpot_winners', $data['jackpot_winners']);
        $entity->set('field_verified', 1);
        $entity->save();

        return $entity;
    }

    /**
     * Create Drawing Data Paragraph plugin
     */
    public function createDrawingData($nid, $data)
    {
        $entity = Drawing::create([
          'type' => 'drawing_data',
          'field_draw_date' => $data['draw_date'],
          'field_winning_numbers_txt' => $data['winning_numbers'],
          'field_bonus_ball' => $data['bonus_ball'],
          'field_multiplier' => $data['multiplier'],
          'field_draw_time' => $data['draw_time'],
          'field_collect_time' => $data['collect_time'],
          'field_jackpot' => $data['jackpot'],
          'field_draw_number' => $data['draw_number'],
          'field_total_prizes' => $data['total_prizes'],
          'field_next_draw_date' => $data['next_draw_date'],
          'field_next_jackpot' => $data['next_jackpot'],
          'field_jackpot_winners' => $data['jackpot_winners'],
          'field_verified' => 1,
          'name' => $data['file_name'],
          'game' =>[['target_id' => $nid]],
        ]);
        $entity->save();
        return $entity;
    }

    /**
     * Add Winner data to a drawing paragraph. It will check if there is existing
     * winners and update them or create new entries.
     */
    public function addWinnersData($entity, $data, $national, $multiplier, $pid)
    {
        if ($pid != '') {
            $paragraph = $this->getWinnerDataParagraph($entity->id(), $data['prize_label'], $data['wager_type']);
            if (empty($paragraph)) {
                return $this->createWinnersData($entity, $data, $national, $multiplier);
            } else {
                return $this->updateWinnersData($entity, $paragraph, $data, $national, $multiplier);
            }
        } else {
            return $this->createWinnersData($entity, $data, $national, $multiplier);
        }
    }

    /**
     * Returns the paragraph for winner data.
     */
    public function getWinnerDataParagraph($pid, $level, $wager = '')
    {
        $query = \Drupal::entityQuery('paragraph')
          ->condition('type', 'winners_data')
          ->condition('field_prize_label', $level)
          ->condition('parent_id', $pid)
          ->condition('parent_type', 'drawing');
        if (!empty($wager)) {
            $query->condition('field_wager_type', $wager);
        }
        $id = $query->execute();
        return entity_load('paragraph', array_shift($id));
    }

    /**
     * Add Winner data to drawing data.
     */
    public function createWinnersData($entity, $data, $national = false, $multiplier = false)
    {
        $paragraph = Paragraph::create([
          'type' => 'winners_data',
          'field_prize_label' => $data['prize_label'],
          'field_prize_winners' => $data['winners'],
          'field_prize_amount' => $this->Amount($data['amount']),
          'field_wager_type' => $data['wager_type'],
          'parent_id' => $entity->id(),
        ]);

        if (!empty($data['state'])) {
            foreach ($data['state'] as $statekey => $statevalue) {
                $paragraph->field_state[] = ['target_id' => $statevalue, 'target_revision_id' => $statevalue];
            }
        }

        $paragraph->save();
        if ($national) {
            if ($multiplier) {
                $entity->field_multiplier_national_winner[] = ['target_id' => $paragraph->id(), 'target_revision_id' => $paragraph->getRevisionId()];
            } else {
                $entity->field_national_winners[] = ['target_id' => $paragraph->id(), 'target_revision_id' => $paragraph->getRevisionId()];
            }
        } else {
            if ($multiplier) {
                $entity->field_multiplier_local_winners[] = ['target_id' => $paragraph->id(), 'target_revision_id' => $paragraph->getRevisionId()];
            } else {
                $entity->field_winners[] = ['target_id' => $paragraph->id(), 'target_revision_id' => $paragraph->getRevisionId()];
            }
        }

        $entity->save();
        return $paragraph;
    }

    /**
     * Update Winner data for drawing data
     */
    public function updateWinnersData($entity, $winnerParagraph, $data)
    {
        $winnerParagraph->set('field_prize_winners', $data['winners']);
        $winnerParagraph->set('field_prize_amount', $this->Amount($data['amount']));
        $winnerParagraph->save();
        return $winnerParagraph;
    }

    /**
     * Check for winning location.
     */
    public function addLocationData($winnerParagraph, $data)
    {
        if ($winnerParagraph) {
            if ($paragraph = $this->getLocationDataParagraph(
                $winnerParagraph->id(),
                $data['winning_identify']
            )) {
                return $this->updateLocationData($winnerParagraph, $paragraph, $data);
            } else {
                return $this->createLocationData($winnerParagraph, $data);
            }
        }
    }

    /**
     * Returns the paragraph for location data.
     */
    public function getLocationDataParagraph($pid, $winning_identify)
    {
        $id = \Drupal::entityQuery('paragraph')
          ->condition('type', 'winning_location')
          ->condition('field_winning_loc_identify', $winning_identify)
          ->condition('parent_id', $pid)
          ->condition('parent_type', 'paragraph')
          ->execute();
        return entity_load('paragraph', array_values($id)[0]);
    }

    /**
     * Create Winning Location.
     */
    public function createLocationData($winnerParagraph, $data)
    {
        $paragraph = Paragraph::create([
          'type' => 'winning_location',
          'field_ticket_type' => $data['play_type'],
          'field_retailer_address' => $data['address'],
          'field_retailer_name' => $data['name'],
          'field_retailer_city' => $data['city'],
          'field_winning_county' => $data['winning_county'],
          'field_retailer_verified' => 1,
          'field_winning_loc_identify' => $data['winning_identify'],
          'parent_id' => $winnerParagraph->id()
        ]);
        $paragraph->save();
        $winnerParagraph->field_winning_locations[] = ['target_id' => $paragraph->id(), 'target_revision_id' => $paragraph->getRevisionId()];
        $winnerParagraph->save();
        return $paragraph;
    }

    /**
     * Update Winning location.
     */
    public function updateLocationData($winnerParagraph, $locationParagraph, $data)
    {
        $locationParagraph->set('field_ticket_type', $data['play_type']);
        $locationParagraph->set('field_retailer_address', $data['address']);
        $locationParagraph->set('field_retailer_name', $data['name']);
        $locationParagraph->set('field_retailer_city', $data['city']);
        $locationParagraph->set('field_retailer_verified', 1);
        $locationParagraph->set('field_winning_loc_identify', $data['winning_identify']);
        $locationParagraph->set('field_winning_county', $data['winning_county']);
        $locationParagraph->save();
        return $locationParagraph;
    }

    /**
     * Us states.
     */
    public function usStates($winners_states = '')
    {
        $states = array(
          'Alabama'=>'AL',
          'Alaska'=>'AK',
          'Arizona'=>'AZ',
          'Arkansas'=>'AR',
          'California'=>'CA',
          'Colorado'=>'CO',
          'Connecticut'=>'CT',
          'Delaware'=>'DE',
          'Florida'=>'FL',
          'Georgia'=>'GA',
          'Hawaii'=>'HI',
          'Idaho'=>'ID',
          'Illinois'=>'IL',
          'Indiana'=>'IN',
          'Iowa'=>'IA',
          'Kansas'=>'KS',
          'Kentucky'=>'KY',
          'Louisiana'=>'LA',
          'Maine'=>'ME',
          'Maryland'=>'MD',
          'Massachusetts'=>'MA',
          'Michigan'=>'MI',
          'Minnesota'=>'MN',
          'Mississippi'=>'MS',
          'Missouri'=>'MO',
          'Montana'=>'MT',
          'Nebraska'=>'NE',
          'Nevada'=>'NV',
          'New Hampshire'=>'NH',
          'New Jersey'=>'NJ',
          'New Mexico'=>'NM',
          'New York'=>'NY',
          'North Carolina'=>'NC',
          'North Dakota'=>'ND',
          'Ohio'=>'OH',
          'Oklahoma'=>'OK',
          'Oregon'=>'OR',
          'Pennsylvania'=>'PA',
          'Rhode Island'=>'RI',
          'South Carolina'=>'SC',
          'South Dakota'=>'SD',
          'Tennessee'=>'TN',
          'Texas'=>'TX',
          'Utah'=>'UT',
          'Vermont'=>'VT',
          'Virginia'=>'VA',
          'Washington'=>'WA',
          'West Virginia'=>'WV',
          'Wisconsin'=>'WI',
          'Wyoming'=>'WY'
        );

        $usStates = [];
        if (!empty($winners_states)) {
            $arraStates = explode(',', $winners_states);
            foreach ($arraStates as $arraStateskey => $arraStatesvalue) {
                $state = array_search($arraStatesvalue, $states);
                $usStates = \Drupal::entityQuery('taxonomy_term')
                  ->condition('name', $state)
                  ->condition('vid', 'us_states')
                  ->execute();
                $usStatesIds[] = array_shift($usStates);
            }
        }
        
        return $usStatesIds;
    }

    /**
     * Amount.
     */
    public function Amount($amount)
    {
        if (is_numeric($amount)) {
            $rightAmount = floatval($amount);
        // if (strpos($amount, '000') > -1) {
            //     $rightAmount = intval($amount);
            // }
        } else {
            $rightAmount = $amount;
        }
        return $rightAmount;
    }
}
