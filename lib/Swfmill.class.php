<?php
/**
 * swfmillを使用して、swfのインスタンスを操作するswfmillラッパークラス
 * 
 * - インスタンスのシンタックスセパレータは[./:]が有効
 * 
 * @update
 *   8/20 パフォーマンスを大幅改善&不具合を修正
 *
 * @copyright Copyright (C) 2008 KAYAC Inc.
 * @author Kensaku Araga <araga-kensaku@kayac.com>
 * @package swfmill
 * @since  2008-11-23
 * @version $Id: Swfmill.class.php 21079 2010-11-04 09:12:47Z araga-kensaku $
 * @todo 未実装部分
 */
class Swfmill
{
  /** @var DOMDocument */
  private $dom_doc; // public or private の方がパフォーマンスが良い
  //protected $dom_doc; // public or private の方がパフォーマンスが良い
  
  /** @var DOMXPath */
  private $xpath;

  /** @var SwfVersion */
  private $version;
  
  /** @var Dictionary */
  private $dictionary;
  
  /** @var Hash{instance:Sprite, instance:Sprite, ... */
  private $sprites = array();
  
  /**
   * コンストラクタ
   *
   * @param mixed $parameter file or SWF文字列 or XML文字
   */
  public function __construct($parameter)
  {
    if (is_null($parameter)) 
    {
      throw new SwfmillException('$pram');
    } 
    elseif ($parameter instanceof DOMDocument) 
    {
      $this->importDOMDocument($parameter);
    } 
    elseif (is_file($parameter)) 
    {
      $this->importFile($parameter);
    } 
    else 
    {
      $checsam = substr($parameter, 0, 3);
      if ($checsam == 'FWS' || $checsam == 'CWS') 
      {
        $this->importSwfBinary($parameter);
      } 
      else 
      {
        $this->importXML($parameter);
      }
    }
  }

  // public functions
  /**
  * DOMDocumentを取得する. 
  * 生成コストを下げるため共有化
  *
  * @return DOMDocument
  */
  public function getDOMDocument() 
  {
    return $this->dom_doc;
  }

  /**
   * SWFMILLXMLを取得する
   *
   * @param  bool   $format trueの場合formatOutputをtrueにする 
   * @return String XML
   */
  public function getXml($format = false)
  {
    if ($format) 
    {
      $this->dom_doc->formatOutput = true;
    }
    return $this->dom_doc->saveXML();
  }

  /**
   * SWFのバージョンを取得する
   *
   * @return Integer
   */
  public function getVersion()
  {
    if (!$this->version) 
    {
      $this->version = $this->dom_doc->getElementsByTagName('swf')->item(0)->getAttribute("version");
    }
    return $this->version;
  }

  /**
   * Swfをバイナリで取得する
   * @return String
   */
  public function getSwfBinary()
  {
    try 
    {
      $contents = FlashLiteToolkit::xml2swf($this->getXml(), " -e cp932");
    } 
    catch (Exception $e) 
    {
      $contents = FlashLiteToolkit::xml2swf($this->getXml());
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

  /**
   * インスタンスが存在するかどうか確認する
   * @param $instance
   * @return Boolean 
   */
  public function hasInstance($instance)
  {
    try 
    {
      $objectID = $this->getDictionary()->getObjectIdByInstance($instance);
      
      if ($objectID > 0) 
      {
        return true;
      }
      return false;
    } 
    catch (SwfmillException $e) 
    {
      // インスタンスが存在しない例外は
      if ($e->getCode() == SwfmillException::NOT_FOUND) 
      {
        return false;
      }
      throw $e;
    }
  }
  
  /**
   * シンタックスで表されるインスタンスを別のSwfのインスタンスと入れ替える
   *
   * @param String  $instance Flash上でのrootからのドットシンタックス
   * @param Swfmill $newSwf   他のSWFのSwfmillインスタンス
   * @param String  $targetsyntax 変換するインスタンスのシンタックスが異なる場合は設定する
   */
  public function changeInstance($instance, Swfmill $newSwf, $targetInstance = '')
  {
    if ($instance == '') 
    {
      return true;
    }
    if ($targetInstance == '') 
    {
      $targetInstance = $instance;
    }
    
    try 
    {
      $oldObject = $this->getDictionary()->getObjectByInstance($instance);
      $newObject = $newSwf->getDictionary()->getObjectByInstance($targetInstance);
    } 
    catch (SwfmillException $e) 
    {
      // インスタンスが存在しない例外は
      if ($e->getCode() == SwfmillException::NOT_FOUND) 
      {
        return false;
      }
      throw $e;
    }
    if (!$oldObject || !$newObject) 
    {
      return false;
    }
    
    return $this->replaceInstanceRecursive($newSwf, $newObject, $oldObject);
  }

  /**
   * インスタンスを削除する
   *
   * @param String $instance
   */
  public function removeInstance($instance)
  {
    $objectID = $this->getDictionary()->getObjectIdByInstance($instance);
    if ($objectID) 
    {
      return $this->removeDefineObject($objectID);
    }
    return false;
  }
  
  /**
   * シンタックスで表されるインスタンスの色を変える. 
   *
   * <p>全てのShapeの色を変更させます。細かくは出来ないのでFlashを作る際に工夫してください</p>
   * <p>MCの場合は直下のグラフィックオブジェクト</p>
   *
   * @param String $instance  Flash上でのrootからのドットシンタックス
   * @param String $color 新しい色
   */
  public function changeFillColor($instance, $color)
  {
    $objectID = $this->getDictionary()->getObjectIdByInstance($instance);
    if (!$objectID) 
    {
      return false;
    }
    $elements = $this->getDictionary()->getAllObjectsAtOwner($objectID);

    if (!$elements || count($elements) == 0) 
    {
      return false;
    }
    foreach ($elements as $element) 
    {
      if (strpos($element->tagName, 'Sprite') === false) 
      {
        $this->changeColor($element, $color, "styles/StyleList/fillStyles/*/");
      }
    }
    return true;
  }

  /**
   * 背景色を変える
   *
   * @param String $color
   */
  public function changeBackGroundColor($color)
  {
    $this->changeColor($this->dom_doc, $color, "//SetBackgroundColor/");
  }

  /**
   * (未実装)シンタックスで表されるインスタンスの画像を差し替える. 
   *
   * @param String $syntax  Flash上でのrootからのドットシンタックス
   * @param String $newImage 画像へのパス
   * 
   * @todo 実装
   */
  public function changeImage($syntax, $newImage)
  {
    throw new Exception("this method is not possible to use.");
  }

  /**
   * (未実装)シンタックスで表されるインスタンスのテキストを差し替える. 
   *
   * <p>日本語を使用する場合はKlabの日本語パッチを当てたswfmillを使用する必要があります</p>
   *
   * @param String $syntax  Flash上でのrootからのドットシンタックス
   * @param String $newText 新しい文字列
   * @param String $color   色を変える場合
   * @todo フォント
   */
  public function changeText($syntax, $newText, $color=null)
  {
    throw new SwfmillException("this method is not possible to use.");
  }

  /**
   * ROOTのアクションにある変数をセットします. 
   * 
   * 一応チェンジですが、追加も可能
   * よほどのことが無い限り、replaceStringを使った方が良い
   * 
   * @param  HashMap $variables  {変数名: 値, ...}
   * @param  Boolean $create_var もし存在しない場合追加するかどうか
   * @return boolean
   */
  public function changeActionVariable($variables, $create_var = false)
  {
    // TODO とりあえずFlash ersion 7 は逃げる
    if (7 <= $this->getVersion()) 
    {
        return false;
    }
    $xpath = $this->getXPath();
    $query = '//Header/tags/DoAction/actions';
    $nodes = $xpath->query($query);
    $actions = $nodes->item(0);
    $default_vars = array();
    $key = '';
    foreach ($actions->childNodes as $node) 
    {
      if ($node instanceof DOMText) 
      {
        continue;
      }
      switch ($node->tagName) 
      {
        case 'PushData':
          if ($key=='') 
          {
            //$key = $node->textContent;
            $key = trim($node->getElementsByTagName('StackString')->item(0)->getAttribute('value'));
          } 
          else 
          {
            $default_vars[$key] = $node->getElementsByTagName('StackString')->item(0);
          }
          break;
        case 'SetVariable':
        case 'Pop':
          $key='';
          break;
        case 'EndAction':
          $endAction = $node;
          break;
      }
    }
    
    foreach ($variables as $key => $value) 
    {
      if (array_key_exists($key, $default_vars) ) 
      {
        $default_vars[$key]->setAttribute('value', $value);
        continue;
      }
      if (!$create_var) 
      {
        continue;
      }
      
      // 新しく作る
      $PushData = '<PushData><items><StackString value="%s"/></items></PushData>';
      
      $new_doc = new DOMDocument();
      $new_doc->loadXML(sprintf($PushData, $key));
      $new_node = $this->dom_doc->importNode($new_doc->getElementsByTagName('PushData')->item(0), true);
      $actions->insertBefore($new_node,$endAction);
      
      $new_doc = new DOMDocument();
      $new_doc->loadXML(sprintf($PushData, $value));
      $new_node = $this->dom_doc->importNode($new_doc->getElementsByTagName('PushData')->item(0), true);
      $actions->insertBefore($new_node,$endAction);
      
      $new_doc = new DOMDocument();
      $new_doc->loadXML('<SetVariable/>');
      $new_node = $this->dom_doc->importNode($new_doc->getElementsByTagName('SetVariable')->item(0), true);
      $actions->insertBefore($new_node,$endAction);
    }
    return true;
  }
  
  /**
   * XML内に含まれるダミー文字列をまとめて置換する
   * @param $map array / 置換える文字列をペアにした連想配列
   * @return string 置換されたテンプレート文字列
   */
  public function replaceString($map = array()) 
  {
    $replaced = $this->getXml();
    reset($map);
    while (list($key, $value) = each($map)) 
    {
      $replaced = str_replace($key, $value, $replaced);
    }
    $this->importXML($replaced);
  }

  /**
   * モーションを入れ替える（元のモーションはなくなります）
   *
   * @param String $instance rootからのドットシンタックス
    * @param String $from_label 元のSWFが持つ元のモーショん
   * @param String $to_label   対象SWFが持つ入れ替え後のモーション
   * @param Swfmill $targetSwf　対象(追加するMotionを持つ)のSWF
   * @return Boolean true:成功 / false:失敗
   */
  public function changeMotion($instance, $from_label, $to_label, Swfmill &$targetSwf)
  {
    // TODO 綺麗にする
    if (!$this->addMotion($instance, $to_label, $targetSwf, $from_label)) 
    {
      // error
    }
    if (!$this->removeMotion($instance, $from_label)) 
    {
      // error
    }
  }

  /**
   * FrameLabelを変更する
   * 
   * @param $instance
   * @param $from_label
   * @param $new_label
   * @return boolean
   */
  public function changeMotionLabel($instance, $from_label, $new_label)
  {
    $xpath = $this->getXPath();
    $object = $this->getDictionary()->getObjectByInstance($instance);
    if (!$object) 
    {
      return false;
    }
    
    $nodes = $xpath->query('tags/FrameLabel[attribute::label="'.$from_label.'"]', $object);
    foreach ($nodes as $node) 
    {
      $node->setAttribute('label', $new_label);
    }
    return true;
  }
  
  /**
   * モーションを追加する
   *
   * @param String $instance rootからのドットシンタックス
   * @param String $motion_name　ラベル名
   * @param Swfmill $target_swfobj　対象(追加するMotionを持つ)のSWF
   * @param String $place_label 追加する直後のモーション　default:最後尾に追加
   * @return Boolean true:成功 / false:失敗
   * 
   * @todo 同じモーション名だとダメかも
   */
  public function addMotion($instance, $motion_name, Swfmill $target_swfobj, $place_label = null)
  {
    ## 0. init
    $dom_doc = $this->getDOMDocument();
    // Sprite 追加するモーションのもとのownerMC
    $target_sprite = $target_swfobj->getSprite($instance);
    if (!$target_sprite) 
    {
      throw new SwfmillException("no such instance {$instance} on target", SwfmillException::NOT_FOUND);
    }
    // Motion 追加するモーションObject
    $target_motion = $target_sprite->getMotion($motion_name);
    if (!$target_motion) 
    {
      throw new SwfmillException("no such motion {$motion_name} on {$instance}", SwfmillException::NOT_FOUND);
    }
    // Sprite 追加先Sprite
    $my_sprite     = $this->getSprite($instance);
    if (!$my_sprite) 
    {
      throw new SwfmillException("no such instance {$instance} on this", SwfmillException::NOT_FOUND);
    }
    // Array {instance_name:objectID, instance_name:objectID, instance_name:objectID, ...}
    // 追加元のSpriteの持つインスタンス名
    $target_instances = $target_swfobj->getDictionary()->getAllInstanceAtOwner($target_sprite->objectID());
    if ($target_instances == null) 
    {
      $target_instances = array();
    }
    // Array {instance_name:objectID, instance_name:objectID, instance_name:objectID, ...}
    // 追加先のSpriteの持つインスタンス名
    $my_instances     = $this->getDictionary()->getAllInstanceAtOwner($my_sprite->objectID());
    if ($my_instances == null) 
    {
      $my_instances = array();
    }

    ## 1. objectIDマッピングデータを作成する
    // 繰り越しobjectIDのマッピングデータ
    $shift_objectID_map = array();
    foreach ($target_instances as $target_ins_name => $target_ins_objectID) 
    {
      if (array_key_exists($target_ins_name, $my_instances)) 
      {
        $shift_objectID_map[$target_ins_objectID] = $my_instances[$target_ins_name];
      }
    }
    
    ## 2. オリジナルのインスタンスを追加して、全てのobjectIDの移行データを作る
    $my_object = $this->getDictionary()->getObject($my_sprite->objectID());
    // $target_use_objects = $target_swfobj->getDictionary()->getAllObjectsAtOwner($target_sprite->objectID());
    $target_use_objects = $target_motion->getObjects();
    foreach ($target_use_objects as $target_use_objectID) 
    {
      if (!array_key_exists($target_use_objectID, $shift_objectID_map)) 
      {
        $shift_objectID_map[$target_use_objectID] = $this->dictionary->getNewObjectId();
        $this->addChiledObjects($target_swfobj, 
                    $target_swfobj->getDictionary()->getObject($target_use_objectID), 
                    $my_object, $shift_objectID_map[$target_use_objectID]);
      }
    }
    
    ## 3. 追加位置目安のノードを取得する
    if ($place_label != '' && $my_sprite->hasMotion($place_label) ) 
    {
      $place_node = $my_sprite->getMotion($place_label)->currentNode(true);
    } 
    else 
    {
      $place_node = $my_sprite->getEndElement(true);
    }
    
    ## 4. 追加を開始する
    # マージンの追加
    $new_motion = new Motion();
    foreach ($target_motion->getPreMargin() as $node) 
    {
      $new_node  = $dom_doc->importNode($node->cloneNode(true), true);
      $place_node->parentNode->insertBefore($new_node, $place_node);
      $new_motion->addElement($new_node);
    }
    
    # アクティブフレームの追加
    foreach ($target_motion->getFrames() as $frame) 
    {
      foreach ($frame as $layar) 
      {
        $new_node  = $dom_doc->importNode($layar->cloneNode(true), true);
        if ($new_node->hasAttribute('objectID')) 
        {
          $new_node->setAttribute('objectID', $shift_objectID_map[$new_node->getAttribute('objectID')]);
        }
        $place_node->parentNode->insertBefore($new_node, $place_node);
        $new_motion->addElement($new_node);
      }
    }
    # 末尾マージン
    foreach ($target_motion->getPostMargin() as $node) 
    {
      $new_node  = $dom_doc->importNode($node->cloneNode(true), true);
      $place_node->parentNode->insertBefore($new_node, $place_node);
      $new_motion->addElement($new_node);
    }
    
    $my_sprite->addMotion($new_motion);
    return true;
  }
  
  /**
   * MCのタイムライン上に存在するラベル名のモーションデータを削除する. 
   * 
   * @param String $instance rootからのドットシンタックス
   * @param String $motion_name　ラベル名
   * @return Boolean true:成功 / false:失敗
   */
  public function removeMotion($instance, $motion_name)
  {
    $sprite = $this->getSprite($instance);
    
    if ($sprite == null) 
    {
      throw new SwfmillException(sprintf("Not found Sprite syntax name is %s", $instance), SwfmillException::NOT_FOUND);
    }
    
    $motion = $sprite->getMotion($motion_name);
    
    if ($motion == null) 
    {
      throw new SwfmillException(sprintf("Not found motion name is %s in %s", $motion_name, $instance), SwfmillException::NOT_FOUND);
    }
    
    //$objects = $motion->getObjects();
    foreach ($motion->getObjects() as $objectID) 
    {
      $owners = $this->getDictionary()->getOwnersByObjectID($objectID);
      if (count($owners) == 0 || 1 < count($owners) ) 
      {
        continue;
      }
      
      $ii = $sprite->getObjectUsedNum($objectID);
      
      if ($ii == 0 || 1 < $ii) 
      {
        continue;
      }
            
      // 自分以外では使っていないオブジェクトは削除する
      $this->removeDefineObject($objectID);
    }
    
    // モーションの削除
    foreach ($motion->getPreMargin() as $node) 
    {
      $node->parentNode->removeChild($node);
    }
    foreach ($motion->getFrames() as $frame) 
    {
      foreach ($frame as $layar) 
      {
        $layar->parentNode->removeChild($layar);
      }
    }
    foreach ($motion->getPostMargin() as $node) 
    {
      $node->parentNode->removeChild($node);
    }
    $sprite->removeMotion($motion_name);
  }
  
  /**
   * Dictionaryを取得する
   * @param $force
   * @return Dictionary
   */
  public function getDictionary($force = false)
  {
    if (!$this->dictionary || $force) 
    {
      $this->loadObjects();
    }
    return $this->dictionary;
  }
  
  // protected functions
  /**
   * DOMXPathを取得. 
   * 生成コストを下げるため共有化
   *
   * @return DOMXPath
   */
  protected function getXPath()
  {
    if ($this->xpath == null) 
    {
      $this->xpath = new DOMXPath($this->dom_doc);
    }
    return $this->xpath;
  }

  /**
   * instanceのSpriteオブジェクトを取得する
   *
   * @param  String $instance 絶対パスインスタンス名
   * @return Sprite
   */
  protected function getSprite($instance)
  {
    if ( !isset($this->sprites[$instance]) ) 
    {
      $object = $this->getDictionary()->getObjectByInstance($instance);
      if (!$object) 
      {
        return null;
      }
      $this->sprites[$instance] = new Sprite($object, $instance);
    }
    return $this->sprites[$instance];
  }
  
  /**
   * インスタンスの持つ $motion_nameのMotionを取得
   * 
   * @param String $instance rootからのドットシンタックス
   * @param String $motion_name
   * @return Motion 
   */
  protected function getMotion($instance, $motion_name)
  {
    $sprite = $this->getSprite($instance);
    
    if (!$sprite) 
    {
      throw new SwfmillException(sprintf("Not found Sprite syntax name is %s", $instance), SwfmillException::NOT_FOUND);
    }
    
    return  $sprite->getMotion($motion_name);
  }  
  
  /**
   * 新しいDefineObjectを追加する
   *
   * @param String $ownerInstance
   * @param DOMElement $newObject
   * @param Swfmill  $targetSwf
   * @return int 新しく割り当てられたobjectID
   */
  protected function addDefineObject($ownerInstance, DOMElement $newObject, Swfmill $targetSwf)
  {
    $owner = $this->getDictionary()->getObjectByInstance($ownerInstance);
    
    $newObjectID = $this->getDictionary()->getNewObjectId();
    
    $this->addChiledObjects($targetSwf, $newObject, $owner, $newObjectID);
    return $newObjectID;
  }
  
  /**
   * オブジェクトを削除する
   *
   * @param  Integer $objectId
   * @return boolean
   */
  protected function removeDefineObject($objectId)
  {
    $object = $this->getDictionary()->getObject($objectId);
    
    if (!$object) 
    {
      return false;
    }
    
    // 子オブジェクトの削除
    $this->removeChiledObjects($object);
    
    $object->parentNode->removeChild($object);
    $this->getDictionary()->removeObject($objectId);
    
    return true;
  }
  
  /**
   * インスタンスを再起的に入れ替える
   *
   * @param Swfmill $targetSwf
   * @param DOMElement $newElement
   * @param DOMElement $oldElement
   */
  protected function replaceInstanceRecursive(Swfmill &$targetSwf, DOMElement $newElement, DOMElement $oldElement )
  {
    if ($newElement == null) 
    {
      return;
    }
    if ($oldElement->parentNode == null) 
    {
      return;
    }
    $idmap = $this->getAndSetNewObjectIdMaps($targetSwf, $newElement);
    
    // old element の小要素を削除
    $this->removeChiledObjects($oldElement);
    
    // 対象同士は純粋に置換する
    $objectID = $oldElement->getAttribute("objectID");
    $new_node = $this->dom_doc->importNode($newElement->cloneNode(true), true);

    $new_node->setAttribute("objectID", $objectID);
    $oldElement->parentNode->replaceChild($new_node, $oldElement);

    // SpriteのもつDefineShapeの更新
    if (is_array($idmap)) 
    {
      foreach ($idmap as $oldObjectId => $newObjectId) 
      {
        $new_child = $targetSwf->getDictionary()->getObject($oldObjectId);
        $this->addChiledObjects($targetSwf, $new_child, $new_node, $newObjectId);
      }
    }    
  }

  /**
   * Element以下に存在する色を変える
   *
   * @param DOMElement $element
   * @param String $color
   */
  protected function changeColor(DOMNode $element, $color, $query = '') 
  {
    $xpath   = $this->getXPath();
    list($red, $green, $blue) = $this->hexColorToColors($color);
    foreach ($xpath->query($query.'color/Color', $element) as $node) 
    {
      $node->setAttribute('red',   $red);
      $node->setAttribute('green', $green);
      $node->setAttribute('blue',  $blue);
    }
  }
  
  // private functions
  /**
  * XML文字列からデータをロードする
  *
  * @param 　String $xml XML文字列
  */
  private function importXML($xml)
  {
    if ($xml == '') 
    {
      throw new SwfmillException('XML is not be null');
    }
    $dom_doc = new DOMDocument();
    $dom_doc->preserveWhiteSpace = false;
      
    if ($xml == '' || !$dom_doc->loadXML($xml) ) 
    {
      throw new SwfmillException("Xml load Error");
    }
    $this->importDOMDocument($dom_doc);
  }

  /**
   * DOMDocumentインスタンスからデータをロードする
   * 
   * @param DOMDocument $dom_doc
   */
  private function importDOMDocument(DOMDocument $dom_doc)
  {
    if ($dom_doc instanceof DOMDocument) 
    {
      $this->dom_doc = $dom_doc;
      $this->xpath   = null;
    } 
    else 
    {
      throw new SwfmillException('this dom_doc is null or not instance of DOMDocument');
    }
  }

  /**
   * SWFバイナリ文字列からデータをロードする
   * @param String $dom_doc
   */
  private function importSwfBinary($swfbinary)
  {
    try 
    {
      $this->importXML(FlashLiteToolkit::swf2xml($swfbinary, " -e cp932"));
    } 
    catch (Exception $e) 
    {
      $this->importXML(FlashLiteToolkit::swf2xml($swfbinary));
    }
  }
  
  /**
   * ファイルからデータをロードする. 
   * 
   * ロード可能なファイルはXML及びSWFのみ
   * 
   * @param $file
   */
  private function importFile($file)
  {
    if (!file_exists($file)) 
    {
      throw new SwfmillException('File not found '.$file);
    }
    $pathinfo = pathinfo($file);
    if ($pathinfo['extension'] == "xml") 
    {
      $this->importXML(file_get_contents($file));
    } 
    elseif ($pathinfo['extension'] == "swf") 
    {
      $this->importSwfBinary(file_get_contents($file));
    } 
    else 
    {
      throw new SwfmillException("Unsupported file type : $file");
    }
  }

  /**
   * データをパースしてdictionaryを作成する
   */
  private function loadObjects()
  {
    $this->dictionary = new Dictionary();
    $xpath = $this->getXPath();

    // Define*系は今のところRoot配下にしか存在しないと思う
    //$query = "//Header/tags/*[attribute::objectID]";
    $query = "//*[attribute::objectID]";
    $nodes = $xpath->query($query);
    foreach ($nodes as $node) 
    {
      // basic patturn Object->tag->PlaceObject 
      // PlaceObjectならObjectIDとインスタンス名のマップを作る
      $objectId = $node->getAttribute('objectID');
      if (strpos($node->tagName, 'Define') === false) 
      {
        $owner = $node->parentNode->parentNode;
        // bitmapは構造体が違う
        while (!$owner->hasAttribute('objectID') && $owner->tagName != 'Header') 
        {
          $owner = $owner->parentNode;
        }
        $ownerId = ($owner->tagName == 'Header') ? 0 : $owner->getAttribute('objectID');

        $this->dictionary->registerOwner($ownerId, $objectId, $node->getAttribute('name'));
        continue;
      } 
      else 
      {
        $this->dictionary->addObject($objectId, $node);
      }
    }
  }
  
  /**
   * 新しいObjectIDをPlaceObjectにせっとしつつマッピングHashを返す
   *
   * @param Swfmill $targetSwf
   * @param DOMElement $element
   * @return Array
   */
  private function getAndSetNewObjectIdMaps( Swfmill &$targetSwf, DOMElement $element )
  {
    $map = array();
    
    if ($element == null
    || ($objectID = $element->getAttribute('objectID')) == null
    || ($olds = $targetSwf->getDictionary()->getAllObjectIdAtOwner($objectID)) == null) 
    {
      return null;
    }
    
    //$target_doc    = $targetSwf->getDOMDocument();
    $target_xpath = $targetSwf->getXPath();
    
    // add map
    foreach ($olds as $id) 
    {
      if ($id >= Dictionary::MAX_OBJECT_ID) 
      {
        continue;
      }
      $map[$id] = $this->getDictionary()->getNewObjectId();
    }
    switch($element->tagName) {
      case 'DefineSprite':
      case 'DefineSprite2':
        $query = 'tags/PlaceObject[attribute::objectID]|tags/PlaceObject2[attribute::objectID]';
        break;
      case 'DefineShape':
      case 'DefineShape2':
      case 'DefineShape3':
        $query = 'styles/StyleList/fillStyles/ClippedBitmap[attribute::objectID]'
               . '|styles/StyleList/fillStyles/ClippedBitmap2[attribute::objectID]';
        break;
      default:
        throw new SwfmillException(sprintf('Unknown tag type %', $element->tagName));
    }
    
    $placeObjects = $target_xpath->query($query, $element);

    if ($placeObjects) 
    {
      foreach ($placeObjects as $placeObject) 
      {
        $prace_objectID = $placeObject->getAttribute('objectID');
        if ($prace_objectID < 0 || Dictionary::MAX_OBJECT_ID <= $prace_objectID) 
        {
          continue;
        } 
        elseif (isset($map[$prace_objectID])) 
        {
          //echo(sprintf("{update objectID} %s -> %s\n", $prace_objectID, $map[$prace_objectID]));
          $placeObject->setAttribute('objectID', $map[$prace_objectID]);
        } 
        else 
        {
          throw new SwfmillException('Error $object relation is broken');
        }
      }
    }
    return $map;
  }
  
  /**
   * 新しいオベジェクトを小要素まで再起的に追加する
   *
   * @param Swfmill    $targetSwf  追加するSwfmillオブジェクト
   * @param DOMElement $element    追加する要素エレメント
   * @param DOMElement $afterNode  追加する対象の要素エレメント
   * @param int        $objectID   オブジェクトID
   */
  private function addChiledObjects( Swfmill &$targetSwf, DOMElement $newElement, DOMElement $afterNode, $objectID )
  {
    if ($newElement == null) 
    {
      return;
    }
    $idmap = $this->getAndSetNewObjectIdMaps($targetSwf, $newElement);

    //echo(sprintf("{addChiledObjects} %s <= %s, %s \n",$newElement->tagName, $afterNode->tagName, $objectID));
    
    // 追加
    $new_node = $this->dom_doc->importNode($newElement->cloneNode(true), true);
    $new_node->setAttribute("objectID", $objectID);
    $afterNode->parentNode->insertBefore($new_node, $afterNode);
    
    if (is_array($idmap)) 
    {
      foreach ($idmap as $oldObjectId => $newObjectId) 
      {
        $new_child = $targetSwf->getDictionary()->getObject($oldObjectId);
        $this->addChiledObjects($targetSwf, $new_child, $new_node, $newObjectId);
      }
    }    
  }
  
  /**
   * 削除するオブジェクトで使用されない子オベジェクトを削除する. 
   * 
   * XPATH queryの回数が多いから、生成後のSWFの容量に応じて実行するかどうか変える
   *
   * @param DOMElement $element
   */
  private function removeChiledObjects( DOMElement $element )
  {
    if (!$element->hasAttribute('objectID')) 
    {
      return 0;
    }
    $removed_num = 0;
    $chiledElements = $this->getDictionary()->getAllObjectsAtOwner($element->getAttribute('objectID'));
    foreach ($chiledElements as $chiledElement) 
    {
      $objectID = $chiledElement->getAttribute('objectID');
      $owners = $this->getDictionary()->getOwnersByObjectID($objectID);
      if (count($owners) == 0 || 1 < count($owners) ) 
      {
        continue;
      }
      //echo(sprintf('{removeChiledObjects} %s [id=%s]'."\n",$chiledElement->tagName, $objectID));
      
      $removed_num++;
      $this->removeChiledObjects($chiledElement);
      
      $chiledElement->parentNode->removeChild($chiledElement);
      $this->dictionary->removeObject($objectID);
    }
    return $removed_num;
  }

  /**
   * 16進数表現の色をswfmill表現の色に変更する. 
   * 
   * #FFFFFF => {255, 255, 255}
   *
   * @param String $color
   * @return Array
   */
  private function hexColorToColors($color)
  {
    $rgb = str_replace("0x", "", str_replace("#","",$color));
    return array(
        (string) hexdec("0x".substr($rgb, 0, 2)),
        (string) hexdec("0x".substr($rgb, 2, 2)), 
        (string) hexdec("0x".substr($rgb, 4, 2))
    );
  }
}
