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
    $configs         = array(
      'swfmill_command' => 'swfmill',
    );
  
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
    $process = proc_open(self::$configs['swfmill_command']." ".$opt." xml2swf stdin", $descriptorspec,$pipes);
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
    $command = self::$configs['swfmill_command']." ".$opt." swf2xml stdin";
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

  public static function setConfig($configs)
  {
    self::$configs = array_merge($configs, self::$configs);
  }
}
