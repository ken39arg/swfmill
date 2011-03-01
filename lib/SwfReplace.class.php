<?php
class SwfReplace
{
  protected 
    $template;

  public function __construct($template = null, $dummyImages = array())
  {
    $this->setTemplate($template, $dummyImages);
  }

  public function setTemplate($template, $dummyImages = array())
  {
    if (is_file($template))
    {
      $this->template = FlashLiteToolkit::generateTemplate($template, $dummyImages = array());
    }
    else
    {
      $this->template = $template;
    }
  }

  public function setImages($imageFiles)
  {
    foreach ($imageFiles as $name => $imageFile)
    {
      $this->setImage($name, $imageFile);
    }
  }

  public function setImage($name, $imageFile)
  {
    $this->template = FlashLiteToolkit::changeImage($this->template, $imageFile, $name);
  }

  public function replaceStrings($strings)
  {
    foreach ($strings as $name => $value)
    {
      $this->replaceString($name, $value);
    }
  }

  public function replaceString($name, $value)
  {
    $this->template = str_replace($name, $value, $this->template);
  }

  public function getSwfBinary()
  {
    try 
    {
      $contents = FlashLiteToolkit::xml2swf($this->template, " -e cp932");
    } 
    catch (Exception $e) 
    {
      $contents = FlashLiteToolkit::xml2swf($this->template);
    }
    return $contents;
  }
  
  /**
   * SWFファイルとして保存する
   *
   * @param String $filename 保存ファイル名
   */
  public function saveAsSwf($filename)
  {
    file_put_contents($filename, $this->getSwfBinary());
  }

  /**
   * Flashとしてブラウザに出力します
   *
   * @param Array $headers 追加HTTPヘッダ
   */
  public function putAsSwf($headers=array())
  {
    $headers = array_merge(array('Content-type: application/x-shockwave-flash'), $headers);
    foreach ($headers as $header) 
    {
      header($header);
    }
    echo $this->getSwfBinary();
  }
}
