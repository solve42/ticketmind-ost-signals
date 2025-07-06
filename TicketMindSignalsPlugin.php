<?php

require_once dirname(__FILE__) . '/lib/autoload.php';


/**
 * Entry point class to bfx-ost-streamer plugin.
 *
 * @property array $ht
 *   A cached version of the plugin's configuration taken from the database.
 */
class TicketMindSignalsPlugin extends \Plugin {

  /**
   * {@inheritDoc}
   */
  public $config_class = TicketMindSignalsPlugin::class;

  /**
   * The plugin directory path.
   *
   * @var string
   */
  const PLUGIN_DIR = __DIR__;

  /**
   * {@inheritDoc}
   */
  public function bootstrap() {
//    $factory = new UseCaseFactory();
//
//    foreach ($factory->formats() as $name => $label) {
//      if (Helper::isUseCaseEnabled($name)) {
//        $factory->create($name);
//      }
//    }
  }

  /**
   * {@inheritDoc}
   */
  public function init() {
    if (\method_exists(parent::class, 'init') === TRUE) {
      parent::init();
    }

    $this->{'ht'}['name'] = $this->{'info'}['name'];
  }

  /**
   * {@inheritDoc}
   */
  public function isMultiInstance() {
    return FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public function enable() {
    if (($has_requirements = parent::enable()) !== TRUE) {
      return $has_requirements;
    }

    return \db_query(\sprintf("UPDATE %s SET `name` = CONCAT('__', `name`) WHERE `id` = %d AND `name` NOT LIKE '\_\_%%'",
      \DbEngine::getCompiler()->quote(\PLUGIN_TABLE),
      \db_input($this->getId())
    ));
  }

}
