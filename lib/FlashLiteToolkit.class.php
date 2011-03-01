<?php
/**
 * Swfmillを扱う際のユーティリティ
 *
 * @copyright Copyright (C) 2009 KAYAC Inc.
 * @author Kensaku Araga <araga-kensaku@kayac.com>
 * @since  2009/08/12
 * @version $Id: FlashLiteToolkit.class.php 7116 2010-05-12 03:25:28Z araga-kensaku $
 */
class FlashLiteToolkit
{
  const JPEG_PREFIX = "\xFF\xD9\xFF\xD8";

  private static
    $cacheInstance   = null,
    $swfmill_cmd     = null;
  
  // static functions
  public static function swfmill()
  {
    if (self::$swfmill_cmd == null) 
    {
      $config_bin = sfConfig::get('app_flash_generator_swfmill_bin');
      if ($config_bin) 
      {
        if (substr($config_bin, 0, 1) == '/') 
        {
          self::$swfmill_cmd = $config_bin;
        } 
        else 
        {
          self::$swfmill_cmd = SF_ROOT_DIR.DIRECTORY_SEPARATOR.$config_bin;
        }
      } 
      else 
      {
        self::$swfmill_cmd = 'swfmill';
      }
    }
    return self::$swfmill_cmd;  
  }


  /**
  * XML文字列(ファイルじゃない)をSWFバイナリに変換ファイルを生成する訳ではありません。
  *
  * @param  String $xmlstr XML文字列
  * @return String SWFバイナリ文字列
  * @throws Exception : エラーの際は例外を投げます
  */
  public static function xml2swf($xmlstr, $opt = "")
  {
    $result = null;
    $err    = null;
    $descriptorspec = array(
      0 => array("pipe", "r"),
      1 => array("pipe", "w"),
      2 => array("pipe", "w"),
    );
    $process = proc_open(self::swfmill()." ".$opt." xml2swf stdin", $descriptorspec,$pipes);
    if (is_resource($process))
    {
      fwrite($pipes[0], $xmlstr);
      fclose($pipes[0]);

      $result = stream_get_contents($pipes[1]);
      fclose($pipes[1]);

      $err = stream_get_contents($pipes[2]);
      fclose($pipes[2]);
      proc_close($process);
    }
    if (strlen($result) > 0)
    {
      return $result;
    }

    if (strlen($err) > 0) 
    {
      throw new SwfmillException("swfmill error => ".$err, SwfmillException::COMMAND_ERROR);
    }

    throw new SwfmillException("Unknown Error at Swfmill::generateSwf", SwfmillException::COMMAND_ERROR);
  }

  /**
   * SWＦ文字列をswfmillを使用してxmlに変換する。
   *
   * @param String $swf SWF文字列
   * @return String XML文字列
   */
  public static function swf2xml($swf, $opt = "")
  {
    $result = null;
    $err    = null;
    $descriptorspec = array(
      0 => array("pipe", "r"),
      1 => array("pipe", "w"),
      2 => array("pipe", "w"),
    );
    $command = self::swfmill()." ".$opt." swf2xml stdin";
    $process = proc_open($command, $descriptorspec, $pipes);
    if (is_resource($process)) 
    {
      fwrite($pipes[0], $swf);
      fclose($pipes[0]);

      $result = stream_get_contents($pipes[1]);
      fclose($pipes[1]);

      $err = stream_get_contents($pipes[2]);
      fclose($pipes[2]);
      proc_close($process);
    }
    if (strlen($result) > 0)
    {
      return $result;
    }
    if (strlen($err) > 0) 
    {
      throw new SwfmillException("swfmill error => ".$err."; command => ".$command, SwfmillException::COMMAND_ERROR);
    }

    throw new SwfmillException("Unknown Error at Swfmill::swf2xml", SwfmillException::COMMAND_ERROR);
  }

  /**
   * flashのpathパスからtemplateXML文字列を取得する. 
   *
   * @params String $swfFile     swfコンテンツのパス
   * @params Array  $dummyImages 埋め込んでいる代替画像のパスarrayのキーで置換出来ます
   * @return String テンプレートXML
   */
  public static function generateTemplate($swfFile, $dummyImages = array())
  {
    $template = self::getCacheInstance()->get($swfFile);
    if (!$template)
    {
      $template = self::generatePersistentTemplate($swfFile, $dummyImages);
      self::getCacheInstance()->set($swfFile, $template);
    }
    return $template;
  }

  private static function generatePersistentTemplate($swfFile, $dummyImages = array())
  {
    $cacheDir = sfConfig::get('app_flash_generator_cache_dir');
    $currentMask = umask(0000);

    if (!file_exists($swfFile))
    {
      throw new SwfmillException("{FlashLiteToolkit}No swf file ".$swfFile);
    }

    $cacheDir = $cacheDir.DIRECTORY_SEPARATOR.ltrim(str_replace(sfConfig::get('sf_root_dir'), '', dirname($swfFile)));
    if (!file_exists($cacheDir))
    {
      mkdir($cacheDir, 0777, true);
    }
    if (!is_readable($cacheDir))
    {
      throw new SwfmillException("{FlashLiteToolkit} no permission ".$cacheDir);
    }

    $cacheFile = $cacheDir.DIRECTORY_SEPARATOR.basename($swfFile).'.xml';

    if (!file_exists($cacheFile) || filemtime($cacheFile) < filemtime($swfFile)) 
    {
      $swfString = file_get_contents($swfFile);
      try 
      {
        $template = FlashLiteToolkit::swf2xml($swfString, ' -e cp932'); 
      }
      catch (SwfmillException $e)
      {
        $template = FlashLiteToolkit::swf2xml($swfString); 
      }

      if (!is_array($dummyImages) && $dummyImages != '')
      {
        $dummyImages = array(0 => $dummyImages);
      }
      if (0 < count($dummyImages))
      {
        foreach ($dummyImages as $name => $dummyImage)
        {
          $imageBinary = file_get_contents($dummyImage);
          $imageDummy  = base64_encode(self::JPEG_PREFIX.$imageBinary);
          $template    = str_replace($imageDummy, '[%image_'.$name.'%]', $template, $count);  
          if ($count == 0)
          {
            $template   = str_replace(base64_encode($imageBinary), '[%image_'.$name.'%]', $template, $count);  
          }
          $imageBinary = $imageDummy = null;
          $count = 0;
        }
      }

      file_put_contents($cacheFile, $template);
      chmod($cacheFile, 0666);
    }
    else
    {
      $template = file_get_contents($cacheFile);
    }

    umask($currentMask);
    return $template;
  }

  public static function changeImage($template, $imageFile, $name = 0)
  {
    $imageBinary = file_get_contents($imageFile);
    $imageString = base64_encode(self::JPEG_PREFIX.$imageBinary);
    $template    = str_replace('[%iamge_'.$name.'%]', $imageString, $template);
    return $template;
  }

  public static function getCacheInstance()
  {
    if (self::$cacheInstance === null)
    {
      $class = sfConfig::get('app_flash_generator_cache_class', 'sfNoCache');
      $param = sfConfig::get('app_flash_generator_cache_param');

      if (!class_exists($class))
      {
        throw new sfException("{FlashLiteToolkit} Unable to load cache class: $class");
      }
      
      $instance = new $class($param);

      if ($instance instanceof sfCache)
      {
        self::$cacheInstance = $instance;
      }
      else
      {
        throw new sfException('{FlashLiteToolkit} Please setting sfCache');
      }
    }

    return self::$cacheInstance;
  }  
}
