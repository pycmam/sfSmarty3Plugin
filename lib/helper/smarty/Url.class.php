<?php
class HelperSmartyUrl
{
  public static function urlFor()
  {
    use_helper('Url');

    $args = func_get_arg(0);

    if(array_key_exists('url', $args))
    {
      $absolute = false;

      if(array_key_exists('absolute', $args))
      {
        $absolute = $args['absolute'];
      }

      return url_for($args['url'], $absolute);
    }

    throw new sfException('Invalid arguments');
  }
}
