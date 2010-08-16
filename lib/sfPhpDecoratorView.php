<?php
/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of sfPhpDecoratorView
 *
 * @author joshi
 */
class sfPhpDecoratorView extends sfPhpView
{
  public function decorateContent($content)
  {
    $this->setDecorator(true);
    $this->decorate($content);
  }
}