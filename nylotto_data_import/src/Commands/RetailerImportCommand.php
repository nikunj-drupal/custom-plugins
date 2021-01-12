<?php

namespace Drupal\nylotto_data_import\Commands;

/**
 * @package Drupal\nylotto_data_import\Commands
 */
class RetailerImportCommand
{
    /**
     * @var string
     */
    protected const API_ACCESS_TOKEN = 'eyJraWQiOiI4SXJKaFpycVhXXC9LNXlSTUZJNnorYzk2NURPY3hBZGFHTXBcL2NobkRzeWc9IiwiYWxnIjoiUlMyNTYifQ.eyJzdWIiOiI0YzFiNTAzNy0wMTQxLTQxMDYtYmRlNC04YjhlOGZhN2I2NDIiLCJldmVudF9pZCI6ImExZGI1OTQyLTQ4MjItNDA2NC05ZjExLTE4ZmY0MmExNmYzNCIsInRva2VuX3VzZSI6ImFjY2VzcyIsInNjb3BlIjoiYXdzLmNvZ25pdG8uc2lnbmluLnVzZXIuYWRtaW4iLCJhdXRoX3RpbWUiOjE1NjA5ODA2NzIsImlzcyI6Imh0dHBzOlwvXC9jb2duaXRvLWlkcC51cy1lYXN0LTEuYW1hem9uYXdzLmNvbVwvdXMtZWFzdC0xX1ZRMUJzZjFhbiIsImV4cCI6MTU2MDk4NDI3MiwiaWF0IjoxNTYwOTgwNjcyLCJqdGkiOiI5YTA2MzNlZS0zNmNmLTRmODItYTRlYS1mZGJiNTkyZDg3M2IiLCJjbGllbnRfaWQiOiI1dGQycW02aGE3ZDB1aHFpZmlxbjZzNDlkYyIsInVzZXJuYW1lIjoiNGMxYjUwMzctMDE0MS00MTA2LWJkZTQtOGI4ZThmYTdiNjQyIn0.XEp1ArpVbKFlAYw0mUpfZYUSYhe3QNgoysys72UtF4_QEc105QzZTHoa0YxgiB7RVgdVdBii69FJu6KQGlTmun4xgvQu4xp8fCF4si0EVuBl44sV5RNgrmnkcFvAPPaCHY2eg4MrXs2D6jJ3P3-uHh7cdaMhkle3xVJKpcrzK7o3XmDpOgMW-yz-1wBNPaObI52Ol747wijq_j6Xm2wqQ84LONn0-9XYvENDZ95he4E26vFxuFSL0ELZKTtYpKG9K9dKTI9knjoE_6Mn8E6Ca6jJdoJaBELbSkaGrjIklJgE6LUcYq5aYT3-nkE1enkgjgNJ3fal7HPKQYonpI3YxA';

    /**
     * @var string
     */
    protected const API_KEY = 'kAA3paUZQQvebd0Fws6e44s4FQ2vbGc74piJfJC2';

    /**
     * @var string
     */
    protected const CLIENT_ID = '32hktrve04jqbd8jg677s2fska';

    /**
     * @return void
     */
    public function __construct()
    {
        if ($data = $this->__makeRequest())
        {
            foreach ($data as $item) {
                $this->importRetailer( $this->parseRow($item) );
            }
        }
    }

    /**
     * Generate an array of objects from RestApi.
     *
     * @return array\bool Return an array or false
     */
    protected function __makeRequest()
    {
        $uri = 'https://api.nylservices.net/retailers/all';

        try {
            $response = \Drupal::httpClient()->get($uri, [
                'headers' => [
                    'x-api-key' => self::API_KEY,
                ],
                [
                    'access-token' => self::API_ACCESS_TOKEN,
                ],
                [
                    'client-id' => self::CLIENT_ID,
                ],
            ]);

            $data = explode("\r\n", $response->getBody());

            if (empty($data))
                \Drupal::logger('nylotto_data_import')->error("Could not get data from Retailer API");
            else
                return array_map('str_getcsv', $data);

        } catch (Exception $e) {
            watchdog_exception('nylotto_data_import', $e);
        }
    }

    /**
     * @param object $item
     */
    protected function importRetailer($item)
    {
        try {

            if ( !isset($item->name) || $item->name == 'name' ) return;

            $ids = \Drupal::entityQuery('node')
                ->condition('type', 'retailer')
                ->condition('field_internal_id', $item->internalid)
                ->execute();

            if (!empty($ids)) $node = entity_load('node', array_shift($ids));

            //
            if (empty($node)) {
                $storage = \Drupal::entityTypeManager()->getStorage('node');

                $node = $storage->create([
                    'type'                 => 'retailer',
                    'title'                => $item->name,
                    'field_internal_id'    => $item->internalid,
                    'field_isqd'           => ($item->isqd == '') ? 'n' : 'y',
                    'field_street_address' => $item->street,
                    'field_city'           => $item->city,
                    'field_state'          => $item->state,
                    'field_zipcode'        => $item->zip,
                ]);

            } else {
                $node->id();
                $node->set('title'               , $item->name);
                $node->set('field_isqd'          , $item->isqd);
                $node->Set('field_street_address', $item->street);
                $node->set('field_city'          , $item->city);
                $node->set('field_state'         , $item->state);
                $node->set('field_zipcode'       , $item->zip);
            }

            //
            $point = array(
                'lat' => $item->longitude,
                'lon' => $item->latitude,
            );

            $lat_lon = \Drupal::service('geofield.wkt_generator')->WktBuildPoint($point);

            $node->field_geofield->setValue([$lat_lon]);
            $changed = $node->getChangedTime();
            $node->setNewRevision(FALSE);
            $node->setChangedTime($changed);
            $node->setRevisionLogMessage('Data Feed Import for ' . $item->internalid);
            $node->setRevisionUserId(1);
            $node->save();

        } catch (\Exception $e) {
            watchdog_exception('RetailerImport', $e);
        }
    }

    /**
     * Parse the data row and import into drupal
     *
     * @param  array $item
     * @return object
     */
    protected function parseRow($element)
    {
        if ($element[0])
        {
            foreach ($element as $delta => $value) {
                $element[$delta] = trim($value, "\n");
            }

            // Create an object.
            $item = (object) array(
                'internalid' => $element[0],
                'name'       => $element[1],
                'street'     => $element[2],
                'city'       => $element[3],
                'state'      => $element[4],
                'zip'        => $element[5],
                'isqd'       => $element[6],
                'latitude'   => $element[7],
                'longitude'  => $element[8],
            );
        }

        return $item;
    }
}
