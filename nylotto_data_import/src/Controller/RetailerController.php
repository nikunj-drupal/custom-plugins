<?php

namespace Drupal\nylotto_data_import\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Queue\QueueFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;

/**
 * Class RetailerController.
 */
class RetailerController extends ControllerBase
{
    /**
     * Drupal\Core\Messenger\MessengerInterface definition.
     *
     * @var \Drupal\Core\Messenger\MessengerInterface
     */
    protected $messenger;
    /**
     * Symfony\Component\DependencyInjection\ContainerAwareInterface definition.
     *
     * @var \Symfony\Component\DependencyInjection\ContainerAwareInterface
     */

    protected $queueFactory;
    /**
     * GuzzleHttp\ClientInterface definition.
     *
     * @var \GuzzleHttp\ClientInterface
     */

    protected $client;

    /**
     * Inject services.
     */
    public function __construct(MessengerInterface $messenger, QueueFactory $queue1, ClientInterface $client)
    {
        $this->messenger = $messenger;
        $this->queueFactory = $queue1;
        $this->client = $client;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container)
    {
        return new static(
            $container->get('messenger'),
            $container->get('queue'),
            $container->get('http_client')
        );
    }

    /**
     * Delete the queue 'exqueue_import'.
     */
    public function deleteTheQueue()
    {
        $this->queueFactory->get('exqueue_import')->deleteQueue();
        return [
          '#type' => 'markup',
          '#markup' => $this->t('The queue "exqueue_import" has been deleted'),
        ];
    }

    /**
     * Getdata from external source and create a item queue for each data.
     *
     * @return array
     *   Return string.
     */
    public function getData()
    {
        $data = $this->getDataFromRestApi();
        if (!$data) {
            return [
              '#type' => 'markup',
              '#markup' => $this->t('No data found'),
            ];
        }
        $queue1 = $this->queueFactory->get('exqueue_import');
        $totalItemsBefore = $queue1->numberOfItems();
        foreach ($data as $element) {
            $object = $this->parseRow($element);
            if ($object) {
                $queue1->createItem($object);
            }
        }

        $totalItemsAfter = $queue1->numberOfItems();

        $finalMessage = $this->t(
            'The Queue had @totalBefore items. We should have added @count items in the Queue. Now the Queue has @totalAfter items.',
            [
              '@count' => count($data),
              '@totalAfter' => $totalItemsAfter,
              '@totalBefore' => $totalItemsBefore,
            ]
        );
        return [
          '#markup' => $finalMessage,
        ];
    }

    /**
     * Generate an array of objects from RestApi.
     *
     * @return array|bool
     *   Return an array or false
     */
    protected function getDataFromRestApi()
    {
        $uri = 'https://api.nylservices.net/retailers/all';
        try {
            $response = $this->client->get($uri, [
            'headers' => ['x-api-key' => 'kAA3paUZQQvebd0Fws6e44s4FQ2vbGc74piJfJC2'], ['access-token' => 'eyJraWQiOiI4SXJKaFpycVhXXC9LNXlSTUZJNnorYzk2NURPY3hBZGFHTXBcL2NobkRzeWc9IiwiYWxnIjoiUlMyNTYifQ.eyJzdWIiOiI0YzFiNTAzNy0wMTQxLTQxMDYtYmRlNC04YjhlOGZhN2I2NDIiLCJldmVudF9pZCI6ImExZGI1OTQyLTQ4MjItNDA2NC05ZjExLTE4ZmY0MmExNmYzNCIsInRva2VuX3VzZSI6ImFjY2VzcyIsInNjb3BlIjoiYXdzLmNvZ25pdG8uc2lnbmluLnVzZXIuYWRtaW4iLCJhdXRoX3RpbWUiOjE1NjA5ODA2NzIsImlzcyI6Imh0dHBzOlwvXC9jb2duaXRvLWlkcC51cy1lYXN0LTEuYW1hem9uYXdzLmNvbVwvdXMtZWFzdC0xX1ZRMUJzZjFhbiIsImV4cCI6MTU2MDk4NDI3MiwiaWF0IjoxNTYwOTgwNjcyLCJqdGkiOiI5YTA2MzNlZS0zNmNmLTRmODItYTRlYS1mZGJiNTkyZDg3M2IiLCJjbGllbnRfaWQiOiI1dGQycW02aGE3ZDB1aHFpZmlxbjZzNDlkYyIsInVzZXJuYW1lIjoiNGMxYjUwMzctMDE0MS00MTA2LWJkZTQtOGI4ZThmYTdiNjQyIn0.XEp1ArpVbKFlAYw0mUpfZYUSYhe3QNgoysys72UtF4_QEc105QzZTHoa0YxgiB7RVgdVdBii69FJu6KQGlTmun4xgvQu4xp8fCF4si0EVuBl44sV5RNgrmnkcFvAPPaCHY2eg4MrXs2D6jJ3P3-uHh7cdaMhkle3xVJKpcrzK7o3XmDpOgMW-yz-1wBNPaObI52Ol747wijq_j6Xm2wqQ84LONn0-9XYvENDZ95he4E26vFxuFSL0ELZKTtYpKG9K9dKTI9knjoE_6Mn8E6Ca6jJdoJaBELbSkaGrjIklJgE6LUcYq5aYT3-nkE1enkgjgNJ3fal7HPKQYonpI3YxA'], [
              'client-id' => '32hktrve04jqbd8jg677s2fska
                  ',
            ],
            ]);
            $data = (string) $response->getBody();
            if (empty($data)) {
                return false;
            }
        } catch (RequestException $e) {
            return false;
        }
        $contents = explode("\n", $data);
        $content = [];
        foreach ($contents as $child) {
            $result = explode(",", $child);
            $content[] = $result;
        }
        if (empty($content)) {
            return false;
        }
        return $content;
    }

    /**
     * Parse the data row and import into drupal.
     */
    public function parseRow($element)
    {
        $object = [];
        if (!empty($element[0])) {
            // Create an object.
            $item = new \stdClass();
            $item->internalid = $element[0];
            $item->name = $element[1];
            $item->street = $element[2];
            $item->city = $element[3];
            $item->state = $element[4];
            $item->zip = $element[4];
            $item->isqd = $element[5];
            $item->latitude = $element[6];
            $item->longitude = $element[7];

            $object[] = $item;
        }
        return $object;
    }
}
