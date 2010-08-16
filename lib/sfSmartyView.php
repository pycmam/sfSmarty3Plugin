<?php
class sfSmartyView extends sfView
{
  /**
   * @var sfPhpView
   */
  protected $phpView;

  /**
   * @var string
   */
  protected $extension = '.tpl';

  /**
   * Configures template.
   *
   * @return void
   */
  public function configure()
  {
    // store our current view
    $this->context->set('view_instance', $this);

    // require our configuration
    require($this->context->getConfigCache()->checkConfig('modules/'.$this->moduleName.'/config/view.yml'));

    // set template directory
    if ( ! $this->directory)
    {
      $this->setDirectory($this->context->getConfiguration()->getTemplateDir($this->moduleName, $this->getTemplate()));
    }
  }

  /**
   * Executes any presentation logic for this view.
   */
  public function execute()
  {

  }

  /**
   * Renders the presentation.
   *
   * @param string $template_directory
   * @param stirng $template_file
   *
   * @return string File content
   */
  protected function renderFile($template_directory, $template_file)
  {
    if (sfConfig::get('sf_logging_enabled'))
    {
      $this->dispatcher->notify(new sfEvent($this, 'application.log', array(sprintf('Render "%s"', $template_directory . '/' . $template_file))));
    }

    $this->loadCoreAndStandardHelpers();

    $smarty = $this->getEngine();

    $smarty->setTemplateDir($template_directory);

    $template = $smarty->createTemplate($template_file, $smarty);
    
    if(sfConfig::get('sf_smarty_auto_config', true))
    {
      $smarty->addConfigDir($template_directory . '/../config/');

      // LOAD CONFIGS
      $config_file_name = sfConfig::get('sf_smarty_auto_config_file_name', 'smarty.conf');

      if(file_exists($template_directory . '/../config/' . $config_file_name))
      {
        $template->configLoad($config_file_name);
      }
    }

    $tpl_vars = $this->attributeHolder->toArray();

    // ASSIGN VARS ONLY IF THE PAGE IS NOT CACHED
    if(!$template->isCached())
    {
      $template->assign($tpl_vars, null, false, SMARTY_LOCAL_SCOPE);
    }
    
    // WORKAROUND FOR THE FACT THAT THE SMARTY DEBUG CONSOLE DOES NOT DISPLAY
    // SCOPED VARIABLED PROPERLY
    if(sfConfig::get('sf_smarty_display_debug_console'))
    {
      $smarty->assign($tpl_vars);
    }
    
    // render
    ob_start();
    ob_implicit_flush(0);

    try
    {
      // NEED TO DISPLAY - SMARTY DEBUG CONSOLE DOES NOT WORK ON  "fetch()"
      $smarty->display($template);
    }
    catch (Exception $e)
    {
      // need to end output buffering before throwing the exception #7596
      ob_end_clean();
      throw $e;
    }
    
    return ob_get_clean();
  }

  /**
   * Executes a basic pre-decorate check to verify all required variables exist
   * and that the template is readable.
   *
   * @throws sfRenderException If the pre-decorate check fails
   */
  protected function preDecorateCheck()
  {
    if (null === $this->getDecoratorTemplate())
    {
      // a template has not been set
      throw new sfRenderException('A decorator template has not been set.');
    }

    if (!is_readable($this->getDecoratorDirectory().'/'.$this->getDecoratorTemplate()))
    {
      throw new sfRenderException(sprintf('The decorator template "%s" does not exist or is unreadable in "%s".', $this->getDecoratorTemplate(), $this->getDecoratorDirectory()));
    }
  }

  /**
   * Loop through all template slots and fill them in with the results of presentation data.
   *
   * @param  string $content  A chunk of decorator content
   *
   * @return string A decorated template
   */
  protected function decorate($content)
  {
    if (sfConfig::get('sf_logging_enabled'))
    {
      $this->dispatcher->notify(new sfEvent($this, 'application.log', array(sprintf('Decorate content with "%s/%s"', $this->getDecoratorDirectory(), $this->getDecoratorTemplate()))));
    }

    // set the decorator content as an attribute
    $attributeHolder = $this->attributeHolder;

    $this->attributeHolder = $this->initializeAttributeHolder(array(
      'sf_content' => new sfOutputEscaperSafe($content)
    ));

    $this->attributeHolder->set('sf_type', 'layout');

    try
    {
      $this->preDecorateCheck();
    }
    catch(sfRenderException $e)
    {
      $view = new sfPhpDecoratorView($this->context, $this->moduleName, $this->actionName, $this->viewName);
      return $view->decorateContent($content);
    }
    catch (Exception $e)
    {
      echo $e;
    }

    // render the decorator template and return the result
    $ret = $this->renderFile($this->getDecoratorDirectory(), $this->getDecoratorTemplate());

    $this->attributeHolder = $attributeHolder;

    return $ret;
  }

  /**
   * Renders the presentation.
   *
   * @return string A string representing the rendered presentation
   */
  public function render()
  {
    $content = null;

    if (sfConfig::get('sf_cache'))
    {
      $viewCache = $this->context->getViewCacheManager();
      $uri = $this->context->getRouting()->getCurrentInternalUri();

      if (!is_null($uri))
      {
        list($content, $decoratorTemplate) = $viewCache->getActionCache($uri);
        if (!is_null($content))
        {
          $this->setDecoratorTemplate($decoratorTemplate);
        }
      }
    }

    // render template if no cache
    if (is_null($content))
    {
      try
      {
        // execute pre-render check
        $this->preRenderCheck();
      }
      catch(sfRenderException $e)
      {
        if(null === $this->template)
        {
          throw new sfRenderException('A template has not been set.');
        }

        $view = new sfPhpView($this->context, $this->moduleName, $this->actionName, $this->viewName);
        
        return $view->render();
      }
      catch(Exception $e)
      {
        throw $e;
      }

      $this->attributeHolder->set('sf_type', 'action');

      // render template file
      $content = $this->renderFile($this->getDirectory(), $this->getTemplate());

      if (sfConfig::get('sf_cache') && !is_null($uri))
      {
        $content = $viewCache->setActionCache($uri, $content, $this->isDecorator() ? $this->getDecoratorDirectory().'/'.$this->getDecoratorTemplate() : false);
      }
    }

    // now render decorator template, if one exists
    if ($this->isDecorator())
    {
      $content = $this->decorate($content);
    }

    return $content;
  }

  public function loadCoreAndStandardHelpers()
  {
    sfSmartyFactory::getInstance(null, $this->context)->loadCoreAndStandardHelpers();
  }

  /**
   * @return Smarty $smarty
   */
  public function getEngine()
  {
    return sfSmartyFactory::getInstance(null, $this->context)->getSmarty();
  }
}
