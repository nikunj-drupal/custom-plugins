(function ($, Drupal) {

  Drupal.behaviors.game_edit = {
    initialized: false,
    updateDisplayedFields: function(settings) {
      var game_options = settings.nylotto_data.game_options;

      var gameOptionFields  = [
        {
          selector: '.field--name-field-draw-time',
          required: [ game_options.draw_time ]
        },
        {
          selector: '.field--name-field-draw-number',
          required: [ game_options.draw_number ]
        },
        {
          selector: '.field--name-field-bonus-ball',
          required: [ game_options.bonus_number ]
        },
        {
          selector: '.field--name-field-multiplier',
          required: [ game_options.multiplier_number ]
        },
        {
          selector: '.field--name-field-jackpot, .field--name-field-jackpot-amount',
          required: [ game_options.jackpot ]
        },
        {
          selector: '.field--name-field-jackpot-winners',
          required: [ game_options.jackpot_winners ]
        },
        {
          selector: '.field--name-field-next-jackpot',
          required: [ game_options.next_jackpot ]
        },
        {
          selector: '.field--name-field-next-draw-date',
          required: [ game_options.next_drawing ]
        },
        {
          selector: '.field--name-field-collect-time',
          required: [ game_options.collect_time ]
        },
        {
          selector: '.field--name-field-national-winners',
          required: [ game_options.natioinal_winners ]
        },
        {
          selector: '.field--name-field-national-locations',
          required: [ game_options.national_winner_location ]
        },
        {
          selector: '.field--name-field-winners',
          required: [ game_options.local_winners ]
        },
        {
          selector: '.field--name-field-winning-locations',
          required: [ game_options.local_winner_location ]
        },
        {
          selector: '.field--name-field-winning-numbers-txt',
          required: [ game_options.winning_numbers ]
        },
        {
          selector: '.field--name-field-wager-type',
          required: [ game_options.wager_type ]
        },
        {
          selector: '.field--name-field-multiplier-local-winners',
          required: [ game_options.local_winners, game_options.multiplier_number]
        },
        {
          selector: '.field--name-field-multiplier-national-winner',
          required: [ game_options.natioinal_winners, game_options.multiplier_number]
        },
        {
          selector: '.field--name-field-total-prizes',
          required: [ game_options.total_prizes ]
        }
      ];

      for (var i in gameOptionFields) {
        var showItem = true;
        for(var j in gameOptionFields[i].required) {
          if ((gameOptionFields[i].required[j] == null || gameOptionFields[i].required[j] == false) && showItem != false) {
            showItem = false;
            break;
          }
        }
        if (showItem == false) {
          $(gameOptionFields[i].selector).hide();
        }
        else {
          $(gameOptionFields[i].selector).show();
        }
      }
    },
    attach: function(context, settings) {

      Drupal.behaviors.game_edit.updateDisplayedFields(settings);
      if (Drupal.behaviors.game_edit.initialized == false) {

        $('#edit-field-game-options input.form-checkbox').on('click', function(){
          console.log('changed game option');
          var id = $(this).val();
          if (this.checked) {
              drupalSettings.nylotto_data.game_options[id] = id;
          }
          else {
            if (drupalSettings.nylotto_data.game_options[id] !== null) {
                drupalSettings.nylotto_data.game_options[id] = false;
            }
          }
          Drupal.behaviors.game_edit.updateDisplayedFields(drupalSettings);
        });
        Drupal.behaviors.game_edit.initialized = true;
      }
    }
  };

})(jQuery, Drupal);
