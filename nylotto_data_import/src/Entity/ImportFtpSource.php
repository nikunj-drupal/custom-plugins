<?php

namespace Drupal\nylotto_data_import\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the FTP Source entity.
 *
 * @ConfigEntityType(
 *   id = "import_ftp_source",
 *   label = @Translation("FTP Source"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\nylotto_data_import\ImportFtpSourceListBuilder",
 *     "form" = {
 *       "add" = "Drupal\nylotto_data_import\Form\ImportFtpSourceForm",
 *       "edit" = "Drupal\nylotto_data_import\Form\ImportFtpSourceForm",
 *       "delete" = "Drupal\nylotto_data_import\Form\ImportFtpSourceDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\nylotto_data_import\ImportFtpSourceHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "import_ftp_source",
 *   admin_permission = "administer site configuration",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "canonical" = "/admin/content/data/import_ftp_source/{import_ftp_source}",
 *     "add-form" = "/admin/content/data/import_ftp_source/add",
 *     "edit-form" = "/admin/content/data/import_ftp_source/{import_ftp_source}/edit",
 *     "delete-form" = "/admin/content/data/import_ftp_source/{import_ftp_source}/delete",
 *     "collection" = "/admin/content/data/import_ftp_source"
 *   }
 * )
 */
class ImportFtpSource extends ConfigEntityBase implements ImportFtpSourceInterface
{

  /**
   * The FTP Source ID.
   *
   * @var string
   */
    protected $id;

    /**
     * The FTP Source label.
     *
     * @var string
     */
    protected $label;

    /**
     * The FTP Source server.
     *
     * @var string
     */
    public $server;

    /**
     * The FTP Source port.
     *
     * @var string
     */
    public $port;

    /**
     * The FTP Source user.
     *
     * @var string
     */
    public $user;

    /**
     * The FTP Source password.
     *
     * @var string
     */
    public $password;

    /**
     * The FTP Source path.
     *
     * @var string
     */
    public $path;

    /**
     * The FTP Source cron_type ie daily or weekly.
     *
     * @var string
     */
    public $cron_type;

    /**
     * The FTP Source import_schedule.
     *
     * @var string
     */
    public $import_schedule;
}
