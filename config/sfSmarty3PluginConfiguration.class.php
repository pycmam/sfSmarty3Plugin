<?php
/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of pluginConfiguration
 *
 * @author joshi
 */
class sfSmarty3PluginConfiguration  extends sfPluginConfiguration
{
  /**
   * Before autoload
   */
  public function configure()
  {
    define('SMARTY_RESOURCE_CHAR_SET', sfConfig::get('sf_smarty_resource_charset', 'utf-8'));
    define('SMARTY_RESOURCE_DATE_FORMAT', sfConfig::get('sf_smarty_resource_date_format', '%b %e, %Y'));
    define('SMARTY_SPL_AUTOLOAD', 0);
  }
}
