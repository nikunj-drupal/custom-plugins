<?php

namespace Drupal\nylotto_data_import\Controller;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Menu\MenuLinkTree;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Mail\MailManager;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Url;
use Drupal\Core\FileTransfer\FTPExtension;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use FtpClient\FtpClient;
use Drupal\Core\Entity\EntityInterface;
use Drupal\views\Views;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Class CustomEndpoints.
 */
class DataImport extends ControllerBase
{

  /**
    * Stores the request stack.
    *
    * @var RequestStack
    */
    protected $requestStack;

    /**
     * Stores the configuration factory for use in our callback.
     *
     * @var \Drupal\Core\Config\ConfigFactorystorestheconfigurationfactory
     */
    protected $configFactory;

    /**
     * Stores the basice page settings for our callback.
     *
     * @var objectstoresconfigurationsforbasicpagesettings
     */
    protected $basicPageSettings;

    /**
     * Stores the service for the menu tree.
     *
     * @var MenuLinkTree
     */
    protected $menuLinkTree;

    /**
     * Stores the service for mail manager.
     *
     * @var MailManager.
     */
    protected $mailManager;

    /**
     * Constructs a CustomEndpoints object.
     */
    public function __construct(RequestStack $request_stack, ConfigFactory $configFactory, MenuLinkTree $menuLinkTree, MailManager $mailManager)
    {
        $this->requestStack = $request_stack;
        $this->configFactory = $configFactory;
        $this->basicPageSettings = $this->configFactory->get('nylotto_custom_json.page.settings');
        $this->menuLinkTree = $menuLinkTree;
        $this->mailManager = $mailManager;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container)
    {
        return new static(
            $container->get('request_stack'),
            $container->get('config.factory'),
            $container->get('menu.link_tree'),
            $container->get('plugin.manager.mail')
        );
    }

    /**
     * Delete the queue 'exqueue_import'.
     */
    public function ftpTest()
    {
        $source = entity_load('import_ftp_source', 'test');
        $service = \Drupal::service('nylotto.data');
        $service->downloadFTPFiles($source);
        return [
          '#markup' => 'hello'
        ];
    }

    /**
     * Delete the queue 'exqueue_import'.
     */
    public function payment_verification_list(EntityInterface $node)
    {
        $view = Views::getView('drawing_data_payout_verification');
        $view->setDisplay('page_1');
        // contextual relationship filter
        $view->setArguments([$node->id()]);
        $render_view = $view->render();
        return $render_view;
    }

    /**
     * Delete the queue 'exqueue_import'.
     */
    public function payment_verification(EntityInterface $node, EntityInterface $paragraph)
    {
        $form = \Drupal::service('entity.form_builder')->getForm($paragraph);
        $form['title'] = [
          '#type' => 'markup',
          '#markup' => '<h2>' . $node->label() . '</h2><hr>',
          '#weight' => -100,
        ];
        return $form;
    }

    /**
     * Delete the queue 'exqueue_import'.
     */
    public function payment_verification_delete(EntityInterface $node, EntityInterface $paragraph)
    {
        global $base_url;
        if ($paragraph) {
            $paragraph_id = $paragraph->id();
            if ($paragraph_id) {
                $entity = \Drupal::entityTypeManager()->getStorage('paragraph')->load($paragraph_id);
                if ($entity) {
                    $entity->delete();
                }
            }
        }
        $path = $base_url.'/';
        if ($node) {
            $node_id = $node->id();
            $path = $base_url.'/node/'.$node_id.'/payment_verification';
        }
        $response = new RedirectResponse($path);
        $response->send();
    }


    /**
     * Delete the queue 'exqueue_import'.
     */
    public function drawingGameVerificationAccess(AccountInterface $account, EntityInterface $node, $permission)
    {
        return AccessResult::allowedIf($account->hasPermission($permission) && $node->bundle() == 'game');
    }
}
