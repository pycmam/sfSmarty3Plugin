<?php
/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of sfSmartyHelperProxy
 *
 * @author joshi
 */
class sfSmartyHelperProxy
{
  /**
   * @var sfSmartyFactory
   */
  protected $smartyFactory;

  /**
   * Construcor
   * @param sfSmartyFactory $factory
   */
  public function __construct(sfSmartyFactory $factory)
  {
    $this->smartyFactory = $factory;
  }

  /**
   * Proxies smarty function calls to symfony
   * helpers.
   *
   */
  public function __call($funcname, $args)
  {
    // SMARTY FUNCTION CALL
    if(strpos($funcname, 'templateFunction_') === 0)
    {
      $helper_name = substr($funcname, 17);
      return $this->call($helper_name, $args[0]);
    }
    elseif(strpos($funcname, 'modifier_') === 0)
    {
      $helper_name = substr($funcname, 9);
      return $this->call($helper_name, $args);
    }
    throw new sfSmartyHelperProxyException(sprintf('%s is not a valid helper function.', $helper_name));
  }

  /**
   * @param string $helper_name
   * @return mixed
   */
  public function call($helper_name, $args)
  {
    if(function_exists($helper_name))
    {
      return call_user_func_array($helper_name, $args);
    }

    throw new sfSmartyHelperProxyException(sprintf('%s helper function not defined or loaded.', $helper_name));
  }
}
