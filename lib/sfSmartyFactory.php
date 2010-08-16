<?php
/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of sfSmartyFactory
 *
 * @author joshi
 */
class sfSmartyFactory
{
  /**
   * @var sfContext
   */
  protected $context;

  /**
   * @var sfSmartyFactory
   */
  protected static $instance;

  /**
   * @var Smarty
   */
  protected $smarty;

  /**
   * Array of standard helpers to load
   * on template initialization
   * @var array
   */
  protected $standard_helpers = array();

  /**
   * Logged loaded functions.
   * @var array
   */
  protected $loaded_helper_functions = array();

  /**
   * Collection of functions that are callable
   * throughout smarty.
   * @var sfSmartyHelperProxy
   */
  protected $helperProxy;

  /**
   * Smarty helper dirs
   * 
   * @var array
   */
  protected $smartyHelperDirs;

  /**
   * Constructor
   * 
   * @param Smarty $smarty
   * @param sfContext $context
   */
  protected function __construct(Smarty $smarty = null, sfContext $context = null)
  {
    $this->context = $context;
    
    $this->initialize($smarty);
  }

  /**
   * Initializer, called on construct.
   */
  protected function initialize(Smarty $smarty = null)
  {
    $this->helperProxy = new sfSmartyHelperProxy($this);

    $smarty_class = sfConfig::get('sf_smarty_smarty_class', 'sfSmarty');
    
    $this->smarty = null === $smarty ? new $smarty_class() : $smarty;
    
    $this->smarty->register->compilerFunction('use', array($this, 'compilerFunctionUseHelper'));
    $this->smarty->register->compilerFunction('use_smarty', array($this, 'compilerFunctionUseSmartyHelper'));
    $this->smarty->register->preFilter(array($this, 'preCompile'));
  }

  /**
   * Returns the singleton instance for
   * this factory.
   * 
   * @return Smarty $smarty
   */
  public static function getInstance(Smarty $smarty = null, sfContext $context = null)
  {
    if(null === self::$instance)
    {
      self::$instance = new sfSmartyFactory($smarty, $context);
    }
    return self::$instance;
  }

  /**
   * @return sfContext $context
   */
  public function getContext()
  {
    if(null === $this->context)
    {
      $this->context = sfContext::getInstance();
    }
    return $this->context;
  }

  /**
   * Loads core and standard helpers.
   * Called before template rendering
   * from withing the view class.
   * This one is tricky: Some standard helpers
   * MUST be included before the view is going
   * to be rendered. This is mandatary especially
   * for the Escaping helper, which defines constants
   * that are beeing re-used in the OutputEscapingDecorator
   * Classes.
   * So we must minimum pre-load this helper to provide the
   * defines. That means that we have to register all functions
   * included in this helper manually so that the associated
   * method calls are cached within the templates.
   * 
   * @see sfSmartyView
   */
  public function loadCoreAndStandardHelpers()
  {
    static $loaded = 0;

    if($loaded)
    {
      return;
    }
    
    $loaded = 1;
    
    $helpers = array_unique(array_merge(array('Helper', 'Url', 'Asset', 'Tag'),sfConfig::get('sf_standard_helpers')));
    
    // remove default Form helper if compat_10 is false
    if (!sfConfig::get('sf_compat_10') && false !== $i = array_search('Form', $helpers))
    {
      unset($helpers[$i]);
    }

    // PRELOAD FOR VIEW-CLASS AND CONTROLLER, NOT YET REGISTERED
    $this->standard_helpers = $helpers;

    // This should only be called on compile time because it's expansive.
    //$default_helpers = $this->loadHelpersGetFunctions(array('Escaping'));
    $this->getContext()->getConfiguration()->loadHelpers(array('Helper', 'Escaping'));

    foreach($default_helpers_functions = array('esc_entities', 'esc_specialchars', 'esc_raw', 'esc_js', 'esc_js_no_entities') AS $function)
    {
      $this->registerHelperFunction($function);
    }
  }

  /**
   * Returns an array of function names for all (public) functions
   * that are included by the given helper file names.
   * The Result of this method is cached in the main template
   * so this method should only be called on compile.
   * 
   * @param array $helpers
   * @return array
   */
  public function loadHelpersGetFunctions(array $helpers)
  {
    $defined_functions = get_defined_functions();

    $defined_functions = $defined_functions['user'];

    $this->getContext()->getConfiguration()->loadHelpers($helpers);

    $newly_defined_functions = get_defined_functions();

    $newly_defined_functions = $newly_defined_functions['user'];

    $newly_defined_helpers = array_filter(array_diff($newly_defined_functions, $defined_functions), create_function('$helper', "return (strpos(\$helper, '_') !== 0) || strpos(\$helper, '__') === 0;"));

    return $newly_defined_helpers;
  }

  /**
   * Pre-Compile hook
   * @param string $tpl_string
   * @param Smarty $smarty
   * @return string
   */
  public function preCompile($tpl_string, Smarty $smarty)
  {
    $str = '';
    
    if(count($this->standard_helpers) > 0)
    {
      $str .= '{use helper=['.implode(',', $this->standard_helpers).']}';
    }

    return $str . $tpl_string;
  }

  /**
   * Registers a smarty helper function
   *
   * @var array
   * @var Smarty_Internal_TemplateCompilerBase
   * @return string $phpcode
   */
  public function compilerFunctionUseSmartyHelper(array $args, Smarty_Internal_TemplateCompilerBase $compiler = null, $compiler_class = 'Smarty_Internal_SmartyTemplateCompiler')
  {
    $compiler === null && $compiler = new $compiler_class(null, null, $this->smarty);

    // PARSE THE ARGUMENTS
    eval('$helpers = ' . $args['helper'] . ';');

    if(!is_array($helpers))
    {
      $helpers = explode(',', trim($helpers));
      $helpers = array_map('trim', $helpers);
    }

    $module_name = $this->getContext()->getModuleName();
    $helper_dirs = $this->getSmartyHelperDirs($module_name);

    $php = '';

    foreach($helpers AS $helper)
    {
      $helper_class_name  = 'HelperSmarty' . sfInflector::classify($helper);
      $helper_class_filename = sfInflector::classify($helper) . '.class.php';
      
      foreach($helper_dirs AS $helper_dir)
      {
        $path_to_class = $helper_dir . '/' . $helper_class_filename;

        if(file_exists($path_to_class))
        {
          $php .= '<?php require_once(\''.$path_to_class.'\');?>';
          
          if(count($class_methods = get_class_methods($helper_class_name)) < 1)
          {
            continue;
          }
          
          $class_methods = array_filter($class_methods, create_function('$helper', "return (strpos(\$helper, '_') !== 0) || strpos(\$helper, '__') === 0;"));

          if(count($class_methods) < 1)
          {
            continue;
          }

          // $php .= '<?php $___smarty_factory=sfSmartyFactory::getInstance();';
          
          foreach($class_methods AS $class_method)
          {
            // SMARTY MAGIC COMPILES THE TEMPLATE CORRECTLY ... IF THE CLASS
            // EXISTS AT COMPILE TIME
            $this->getSmarty()->register->templateFunction($class_method, array($helper_class_name, $class_method));
            //$php .= '$___smarty_factory->registerSmartyHelperFunction(\'' . $class_method . '\', array(\'' . $helper_class_name . '\', \'' . $class_method . '\'), $_smarty_tpl->smarty);';
          }

          // $php .= '? >';
        }
      }
    }
    return $php;
  }

  /**
   * The use_helper pendant
   * @var array
   * @var Smarty_Internal_TemplateCompilerBase
   * @return string $phpcode
   */
  public function compilerFunctionUseHelper(array $args, Smarty_Internal_TemplateCompilerBase $compiler = null, $compiler_class = 'Smarty_Internal_SmartyTemplateCompiler')
  {
    $compiler === null && $compiler = new $compiler_class(null, null, $this->smarty);

    // PARSE THE ARGUMENTS
    eval('$helpers = ' . $args['helper'] . ';');

    if(!is_array($helpers))
    {
      $helpers = explode(',', trim($helpers));
      $helpers = array_map('trim', $helpers);
    }

    $newly_defined_functions = $this->loadHelpersGetFunctions($helpers);
    
    $str = '';

    if(count($newly_defined_functions) > 0)
    {
      $str .= '<?php ' . "\n" . 'use_helper(\'' . implode('\', \'', $helpers) . '\');'
           . "\n"  . '$___smarty_factory=sfSmartyFactory::getInstance();';

      foreach($newly_defined_functions AS $function)
      {
        // REGISTER THE HELPER, ONLY ON BUILD!
        $this->registerHelperFunction($function);
        
        // REGISTER FOR BUILD EVALUATION
        $str .= "\n" . '$___smarty_factory->registerHelperFunction(\'' . $function . '\', $_smarty_tpl->smarty);';
      }
      
      $str .= '?>';
    }
    
    return $str;
  }

  /**
   * @param string $function
   * @param array $callable: A valid php callable
   * @param Smarty
   */
  public function registerSmartyHelperFunction($function, array $callable, $smarty = null)
  {
    $smarty === null && $smarty = $this->smarty;
  }

  /**
   * Registers a helper function as a function and modifier
   * in smarty.
   * 
   * @param string $function
   */
  public function registerHelperFunction($function, Smarty $smarty = null)
  {
    if(in_array($function, $this->loaded_helper_functions))
    {
      return;
    }
    
    $smarty === null && $smarty = $this->getSmarty();
    
    $smarty->register->templateFunction($function, array($this->helperProxy, 'templateFunction_' . $function));
    $smarty->register->modifier($function, array($this->helperProxy, 'modifier_' . $function));

    $this->loaded_helper_functions[] = $function;
  }

  /**
   * @return Smarty $smarty
   */
  public function getSmarty()
  {
    return $this->smarty;
  }

  /**
   * @return sfSmartyHelperProxy
   */
  public function getHelperProxy()
  {
    return $this->helperProxy;
  }
  
  /**
   * Gets the smarty helper directories for a given module name.
   *
   * @param  string $moduleName The module name
   * @return array  An array of directories
   */
  public function getSmartyHelperDirs($moduleName = '')
  {
    if(null !== $this->smartyHelperDirs)
    {
      return $this->smartyHelperDirs;
    }
    
    $dirs = array();

    if ($moduleName)
    {
      $dirs[] = sfConfig::get('sf_app_module_dir').'/'.$moduleName.'/lib/helper/smarty'; // module

      $dirs = array_merge($dirs, $this->getContext()->getConfiguration()->getPluginSubPaths('/modules/'.$moduleName.'/lib/helper/smarty'));
    }

    $this->smartyHelperDirs = array_merge(
      $dirs,
      array(
        sfConfig::get('sf_app_lib_dir').'/helper/smarty',         // application
        sfConfig::get('sf_lib_dir').'/helper/smarty',             // project
      ),
      $this->getContext()->getConfiguration()->getPluginSubPaths('/lib/helper/smarty'),             // plugins
      array($this->getContext()->getConfiguration()->getSymfonyLibDir().'/helper/smarty')           // symfony
    );

    return $this->smartyHelperDirs;
  }
}