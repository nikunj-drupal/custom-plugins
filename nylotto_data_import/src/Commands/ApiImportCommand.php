<?php

namespace Drupal\nylotto_data_import\Commands;

use Drupal\node\Entity\Node;

use Drupal\nylotto_drawing\Entity\Drawing;

/**
 * @package Drupal\nylotto_data_import\Commands
 */
class ApiImportCommand
{
    /**
     * API key
     *
     * @var string
     */
    private $api_key;

  /**
     * @var string
     */
    protected $game_name;

    /**
     * @param string $game_name
     */
    public function __construct($game_name, $from_draw_id, $to_draw_id)
    {
        $this->api_key = isset($_ENV['NYL_X_API_KEY']) ? $_ENV['NYL_X_API_KEY'] : 'kAA3paUZQQvebd0Fws6e44s4FQ2vbGc74piJfJC2';
        if (!in_array($game_name, $this->supportedGames()) ) {
            \Drupal::logger('nylotto_importer')->error("$game_name is not defined.");

        } else {
            $game_name = str_replace('_', '', $game_name);
            $updates = Node::loadMultiple( $this->game_ids() );

            foreach ($updates as $i => $obj) {
                $name = $obj->get('field_game_id')->getString();

                if ($name == $game_name) {

                    if ( is_array($rs = $this->__makeRequest($name, $from_draw_id, $to_draw_id) )) {
                        $draws = $rs['data']['draws'];

                        foreach ($draws as $key => $value) {
                            $game_id = $obj->id();

                            $rs_date = $value['resultDate'] / 1000;
                            $drawPeriod = $value['drawPeriod'];
                            $winners_numbers = '';

                            $drawing_date = \Drupal::service('date.formatter')->format($rs_date, 'custom', 'Y-m-d', 'America/New_York');
                            $drawing_time = \Drupal::service('date.formatter')->format($rs_date, 'custom', 'H:i:s', 'America/New_York');

                            // NYLCMS-16 (Date fix for quickdraw games)
                            // Drawings before 4am should have the date changed to be the following day.
                            $config = \Drupal::config('nylotto_custom_json.ftp.settings');
                            if ($game_name == 'quickdraw' && $config->get('quickdraw_fix')) {
                                $drawing_hour = intval(\Drupal::service('date.formatter')->format($rs_date, 'custom', 'H', 'America/New_York'));
                                if ($drawing_hour < 4) {
                                    // Set to following day (add 24 hrs * 60 min * 60 sec)
                                    $rs_date = $rs_date + (24 * 60 * 60);
                                    $drawing_date = \Drupal::service('date.formatter')->format($rs_date, 'custom', 'Y-m-d', 'America/New_York');
                                    \Drupal::logger('nylotto_importer')->notice("Quick Draw time (" . $drawing_time . ") is before 4:00am, changing Drawing date to following day.");
                                }
                            }

                            if ( isset($value['results']) ) {
                                $primary_data = $value['results'][0]['primary'];

                                if ($game_name == 'quickdraw') {
                                  sort($primary_data);
                                }

                                $winners_numbers = implode('|', $primary_data);
                            }

                            if ($game_name == 'numbers' || $game_name == 'win4') {
                                $drawing_time  = '';

                                switch($drawPeriod) {
                                  case 1:
                                    $drawing_time = 'Midday';
                                    break;
                                  case 2:
                                    $drawing_time = 'Evening';
                                    break;
                                }
                            }

                            $drawing = array(
                                'date'           => $drawing_date,
                                'gameName'       => $game_name,
                                'time'           => $drawing_time,
                                'drawNumber'     => $value['drawNumber'],
                                'multiplier'     => $value['results'][0]['multiplier'] ?? '',
                                'winningNumbers' => $winners_numbers,
                                'bonus'          => $drawingValue['results'][0]['secondary'][0] ?? '',
                            );

                                // Check for a drawing data paragraph for this node.
                                if (!in_array($game_name, array('win4', 'numbers', 'quickdraw') )) {
                                    $pid = \Drupal::entityQuery('drawing')
                                        ->condition('game', $game_id)
                                        ->condition('field_draw_date', $drawing_date)
                                        ->execute();

                                } else {
                                    $pid = \Drupal::entityQuery('drawing')
                                        ->condition('game', $game_id)
                                        ->condition('field_draw_date', $drawing_date)
                                        ->condition('field_draw_time', $drawing['time'])
                                        ->execute();
                                }

                                if (empty($pid))
                                    $this->create_drawing($obj, $drawing);
                                else
                                    $this->update_drawing($obj, entity_load('drawing', array_shift($pid)), $drawing);


                                \Drupal::logger('nylotto_importer')->notice("Imported $game_name #" . $drawing['drawNumber'] . " drawing_date: ".$drawing_date . " drawing_time: ".$drawing_time ." drawPeriod: ".$drawPeriod);

                        }
                    }
                }
            }
        }
    }

    /**
     * Generate an array of objects from RestApi.
     *
     * @return array|bool Return an array or false
     */
    protected function __makeRequest($name, $from_draw_id, $to_draw_id)
    {
        $headers = array('headers' => array(
            'x-api-key' => $this->api_key,
        ));

      $url = "https://api.nylservices.net/games/$name/draws";

      if ($from_draw_id && $to_draw_id) {
        $url = "https://api.nylservices.net/games/$name/draws?from-draw-id=$from_draw_id&to-draw-id=$to_draw_id";
      }


      try {
            $request = \Drupal::httpClient()->get($url, $headers);

            return json_decode($request->getBody()->getContents(), true);

        } catch (RequestException $e) {
            watchdog_exception('nyl_data_import_api_import', $e);
            return false;
        }

        exit(1);
    }

    /**
     * Create Drawing Data Paragraph plugin.
     *
     * @param  \Drupal\node\Entity\Node $node
     * @param  array $obj
     * @return \Drupal\nylotto_drawing\Entity\Drawing
     */
    protected function create_drawing($node, $obj)
    {
        $entity = Drawing::create([
            'type' => 'drawing_data',
            'field_draw_date' => $obj['date'],
            'field_draw_time' => $obj['time'],
            'field_winning_numbers_txt' => $obj['winningNumbers'],
            'field_multiplier' => $obj['multiplier'],
            'field_draw_number' => $obj['drawNumber'],
            'field_bonus_ball' => $obj['bonus'],
            'game' => [['target_id' => $node->id()]],
        ]);

        $entity->save(); return $entity;
    }

    /**
     * Update draw games, based on check NYL API field.
     *
     * @return array
     */
    protected function game_ids()
    {
        return \Drupal::entityQuery('node')
            ->condition('type', 'game')
            ->condition('field_check_nyl_api', 1)
            ->execute();
    }

    /**
     * @return array
     */
    protected function supportedGames()
    {
        return array(
            'cash4life',
            'lotto',
            'numbers',
            'mega_millions',
            'pick10',
            'powerball',
            'quick_draw',
            'take5',
            'win4',
        );
    }

    /**
     * Update Drawing Data Paragraph plugin.
     *
     * @param  \Drupal\node\Entity\Node $node
     * @param  \Drupal\nylotto_drawing\Entity\Drawing $drawing
     * @param  array $obj
     * @return \Drupal\nylotto_drawing\Entity\Drawing
     */
    protected function update_drawing($node, $drawing, $obj)
    {
        $drawing->set('field_winning_numbers_txt', $obj['winningNumbers']);
        $drawing->set('field_bonus_ball', $obj['bonus']);
        $drawing->set('field_multiplier', $obj['multiplier']);
        $drawing->set('field_draw_date', $obj['date']);
        $drawing->set('field_draw_number', $obj['drawNumber']);
        $drawing->set('field_draw_time', $obj['time']);
        $drawing->save();

        $node->save();

        return $drawing;
    }
}
