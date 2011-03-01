<?php
/**
 * FlashDictionary.
 * 
 *　DefineObjectのプレースホルダー兼インスタンス名名前解決
 * 
 * @copyright Copyright (C) 2009 KAYAC Inc.
 * @author Kensaku Araga <araga-kensaku@kayac.com>
 * @package swfmill
 * @since  2009/08/19
 * @version $Id: Dictionary.class.php 21081 2010-11-04 09:58:55Z araga-kensaku $
 */
class Dictionary
{
  /**
   * インスタンスシンタックスのデリミタ
   * @var String
   */
  const INSTANCE_DELIMITER = '/[\/.:]/';
  const MAX_OBJECT_ID      = 65535;
  
  /**
   * @var Array {objectID: DOMElement, objectID: DOMElement, ...}
   */
  protected $dictionary        = array();
  
  /**
   * @var Array 
   *      {
   *        objectID: {
   *          0:[objectID,objectID,...] ,            // 使用している全てのobjectID
   *          1:{name:objectID, name:objectID, ...}  // 名前付きObjectのマップ
   *        }, ...
   *      }
   */
  protected $instance_registry = array();
  protected $finalObjectID     = 0;
  
  /**
   * オブジェクトを追加する
   * @param  Integer    $objectId
   * @param  DOMElement $element
   * @return void
   */
  public function addObject($objectId, DOMElement $element)
  {
    if (self::MAX_OBJECT_ID <= $objectId) 
    {
      throw new SwfmillException('objectID is larger than max id '.$objectId);  
    }
    if ($this->finalObjectID < $objectId) 
    {
      $this->finalObjectID = $objectId;
    }
    $this->dictionary[$objectId] = $element;
  }
  
  /**
   * オブジェクトを削除する
   * @param  Integer  $objectId
   * @return void
   * @todo 削除する項目が自分が子になっているオーナーから自分を除外
   */
  public function removeObject($objectId)
  {
    if (isset($this->dictionary[$objectId])) 
    {
      unset($this->dictionary[$objectId]);
    }
    if (isset($this->instance_registry[$objectId])) 
    {
      unset($this->instance_registry[$objectId]);
    }
  }
  
  /**
   * オブジェクトの主従関係を登録する
   * @param Integer $ownerId   保有するオブジェクトのID(親)
   * @param Integer $objectId  使われるオブジェクトのID(子)
   * @param String  $name      使われるオブジェクトのインスタンス名(ownerからの相対name)
   * @return void
   */
  public function registerOwner($ownerId, $objectId, $name = '')
  {
    if (self::MAX_OBJECT_ID <= $objectId) 
    {
      //throw new SwfmillException('objectID is larger than max id '.$objectId);
      return;
    }
    if (!isset($this->instance_registry[$ownerId])) 
    {
      $this->instance_registry[$ownerId] = array(array(), array());
    }
    if (!in_array($objectId, $this->instance_registry[$ownerId][0])) 
    {
      $this->instance_registry[$ownerId][0][] = $objectId;
    }
    if ($name != '' && !array_key_exists($name, $this->instance_registry[$ownerId][1])) 
    {
      $this->instance_registry[$ownerId][1][$name] = $objectId;
    }
  }

  /**
   * objectIDのObjectを取り出す
   * @param  Instance $objectId
   * @return DOMElement
   */
  public function getObject($objectId)
  {
    if (isset($this->dictionary[$objectId])) 
    {
      return $this->dictionary[$objectId];
    }
    
    throw new SwfmillException("no such object on dictionary : ".$objectId, SwfmillException::NOT_FOUND);
  }
  
  /**
   * ユニークな新しいObjectIDを発効する
   * @return integer
   */
  public function getNewObjectId()
  {
    $this->finalObjectID += 1;
    return $this->finalObjectID;
  } 
  
  /**
   * ownerがobjectIDを使用しているかどうか
   * @param  integer $objectId
   * @param  integer $ownerId  (default: rootオブジェクト)
   * @return boolean
   */
  public function hasObjectIdAtOwner($objectId, $ownerId = 0)
  {
    if (!isset($this->instance_registry[$ownerId])) 
    {
      return false;
    }
    if (!in_array($objectId, $this->instance_registry[$ownerId][0])) 
    {
      return false;
    }
    return true;
  }
  
  /**
   * $objectIDインスタンスを使用しているobjectのIDを取得する
   * @param $objectId
   * @return Array
   */
  public function getOwnersByObjectID($objectId)
  {
    $owners = array();
    foreach ($this->instance_registry as $ownerID => $data) 
    {
      if (in_array($objectId, $data[0])) 
      {
        $owners[] = $ownerID;
      }
    }
    return $owners;
  }
  
  /**
   * ownerが所有する全てのオブジェクトID
   * @param  integer $ownerId  (default: rootオブジェクト)
   * @return array
   */
  public function getAllObjectIdAtOwner($ownerId = 0)
  {
    if (!isset($this->instance_registry[$ownerId])) 
    {
      return null;
    }
    return $this->instance_registry[$ownerId][0];
  }

  /**
   * ownerの保有する全てのオブジェクトをDOMElementのスタックで返す
   * @param $ownerId
   * @return array [DOMElement, DOMElement ,...]
   */
  public function getAllObjectsAtOwner($ownerId = 0)
  {
    $ids_arr = $this->getAllObjectIdAtOwner($ownerId);
    if ($ids_arr == null) 
    {
      return array();
    }
    $objects = array();
    foreach ($ids_arr as $id) 
    {
      $objects[] = $this->getObject($id);
    }
    return $objects;
  }
  
  /**
   * ownerの保有する全ての名前付きインスタンスを返す
   * @param $ownerId
   * @return array {name:objectID, name:objectID,...}
   */
  public function getAllInstanceAtOwner($ownerId)
  {
    if (!isset($this->instance_registry[$ownerId])) 
    {
      return null;
    }
    return $this->instance_registry[$ownerId][1];
  }
  
  /**
   * ownerの保有するnameというインスタンスのobjectIDを返す
   * @param $name
   * @param $ownerId
   * @return Integer
   */
  public function getObjectIdAtOwner($name, $ownerId = 0)
  {
    if (!isset($this->instance_registry[$ownerId])) 
    {
      return false;
    }
    if (!array_key_exists($name, $this->instance_registry[$ownerId][1])) 
    {
      return false;
    }
    return $this->instance_registry[$ownerId][1][$name];
  }
  
  /**
   * インスタンスのobjectIDを取得する
   * @param  String $instance 絶対パスインスタンス名, デリミタは[/.:]
   * @return integer objectID
   */
  public function getObjectIdByInstance($instance)
  {
    $chain = preg_split(Dictionary::INSTANCE_DELIMITER, $instance);
    $id = 0;
    foreach ($chain as $name) 
    {
      $id = $this->getObjectIdAtOwner($name, $id);
      if ($id === false) 
      {
        return false;
      }
    }
    return $id;
  }
  
  /**
   * インスタンスのオブジェクト(DOMElement)を取得する
   * @param  String $instance 絶対パスインスタンス名, デリミタは[/.:]
   * @return DOMElement
   */
  public function getObjectByInstance($instance)
  {
    $objectId = $this->getObjectIdByInstance($instance);
    if ($objectId) 
    {
      return $this->getObject($objectId);
    }
    throw new SwfmillException("no such instance on dictionary : ".$instance, SwfmillException::NOT_FOUND);
  }  

  /**
   * 子のBitmapのobjectIdリストを返す 
   * 
   * @param Integer $objectId 
   * @return Array 
   */
  public function getChildBitmapIds($objectId)
  {
    $result = array();
    $children = $this->getAllObjectIdAtOwner($objectId);
    if (is_array($children))
    {
      foreach ($children as $childObjectId)
      {
        if ($this->isBitmap($childObjectId))
        {
          $result[] = $childObjectId;
        }
        elseif ($_ids = $this->getChildBitmapIds($childObjectId))
        {
          $result = array_merge($result, $_ids);
        }
      }
    }
    return array_unique($result);
  }

  /**
   * ビットマップオブジェクトかどうか 
   * 
   * @param Int $objectId 
   * @return Boolean 
   */
  public function isBitmap($objectId)
  {
    if ($o = $this->getObject($objectId))
    {
      switch ($o->tagName)
      {
        case 'DefineBitsJPEG':
        case 'DefineBitsJPEG2':
        case 'DefineBitsJPEG3':
          // jpeg
        case 'DefineBitsLossless':
        case 'DefineBitsLossless2':
          // png|gif 
          return true;
      }
    }
    return false;
  }
}
