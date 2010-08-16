<?php
/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of sfSmarty
 *
 * @author joshi
 */
class sfSmarty extends Smarty
{
  public $merge_compiled_includes = true;

  public $use_sub_dirs = false;

  public $auto_literal = true;
  
  public $direct_access_security = false;

  public $debugging = false;

  public $_file_perms = 0777;
  public $_dir_perms = 0777;

  /**
   * Class constructor
   */
  public function __construct()
  {
    parent::__construct();

    $this->setConfigDir(sfConfig::get('sf_smarty_config_directories', array(
      sfConfig::get('sf_app_config_dir') . '/smarty',
      sfConfig::get('sf_config_dir') . '/smarty'
    )));

    if(($is_debug = sfConfig::get('sf_debug')) && sfConfig::get('sf_smarty_display_debug_console'))
    {
      $this->debugging = true;
    }

    $this->compile_dir          = sfConfig::get('sf_smarty_compile_directory', sfConfig::get('sf_app_cache_dir') . '/smarty/templates_c');
    $this->cache_dir            = sfConfig::get('sf_smarty_cache_directory', sfConfig::get('sf_app_cache_dir') . '/smarty/cache');

    $this->allow_php_tag        = sfConfig::get('sf_smarty_allow_php_tag', false); // OLD STYLE
    $this->php_handling         = sfConfig::get('sf_smarty_php_handling', SMARTY_PHP_ALLOW); // NEW STYLE
    $this->allow_php_templates  = sfConfig::get('sf_smarty_allow_php_templates', true);
    $this->auto_literal         = sfConfig::get('sf_smarty_auto_literal', true);
    $this->error_unassigned     = sfConfig::get('sf_smarty_error_unassigned', $is_debug);
    $this->use_sub_dirs         = sfConfig::get('sf_smarty_use_subdirs', false);

    $this->left_delimiter       = sfConfig::get('sf_smarty_left_delimiter', '}');
    $this->right_delimiter      = sfConfig::get('sf_smarty_right_delimiter', '{');

    // ENGAGE THE SMARTY CACHE --- OR DON'T, DEPENDING ON SYMFONY VIEW CACHE SETTINGS
    $this->cache_lifetime       = sfConfig::get('sf_smarty_cache_lifetime', 3600);

    $this->force_cache        = sfConfig::get('sf_smarty_force_cache', false);
    $this->compile_check      = sfConfig::get('sf_smarty_compile_check', false);

    if(sfConfig::get('sf_cache'))
    {
      $this->caching            = sfConfig::get('sf_smarty_caching', true);
      $this->force_compile      = sfConfig::get('sf_smarty_force_compile', false);
    }
    else
    {
      $this->caching            = sfConfig::get('sf_smarty_caching', false);
      $this->force_compile      = sfConfig::get('sf_smarty_force_compile', true);
    }
  }

  /**
   * Adds a configuration directory to
   * search for config files ine.
   */
  public function addConfigDir($config_dir)
  {
    if(!is_array($this->config_dir))
    {
      $this->config_dir = array($this->config_dir);
    }

    if(!in_array($config_dir, $this->config_dir))
    {
      $this->config_dir[] = $config_dir;
    }
  }

  /**
   * Sets a configuration directory to
   * search for config files ine.
   *
   * @param string | array $config_dir
   */
  public function setConfigDir($config_dir)
  {
    $this->config_dir = is_array($config_dir) ? $config_dir : array($config_dir);
  }
}
