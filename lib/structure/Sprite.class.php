<?php
/**
 * 1つのMCが保有しているモーションを持つ
 * 
 * 1モーションの定義は空白フレームを挟む１ブロック
 * モーション名の定義は該当ブロック内で最初に設定されたラベル名
 *
 * @copyright Copyright (C) 2009 KAYAC Inc.
 * @author Kensaku Araga <araga-kensaku@kayac.com>
 * @package swfmill
 * @since  2009/07/30
 * @version $Id: Sprite.class.php 1241 2010-01-25 09:43:55Z araga-kensaku $
 */
class Sprite
{
  protected 
    $_name,
    $_objectID,
    $_motions,
    //$_objects,
    $_endElement;
  
  /**
   * construct
   * 
   * @param DOMElement $DefineSprite
   * @param $name
   */
  public function __construct(DOMElement $DefineSprite, $name = '')
  {
    $this->_name = $name;
    $this->_motions = array();
    //$this->_objects = array();
    
    $this->parse($DefineSprite);
  }
  
  /**
   * Spriteのインスタンス名
   * @return String
   */
  public function name()
  {
    return $this->_name;
  }
  
  /**
   * SpriteのObjectID
   * @return integer
   */
  public function objectID()
  {
    return $this->_objectID;
  } 

  public function hasMotion($motion_name)
  {
    if (isset($this->_motions[$motion_name])) 
    {
      return true;
    }
    return false;
  }
  
  /**
   * labelのmotionFramesを取得する
   *
   * @param String $motion_name
   * @return Motion
   */
  public function getMotion($motion_name)
  {
    if ($this->hasMotion($motion_name)) 
    {
      return $this->_motions[$motion_name];
    }
    return null;
  }
  
  public function addMotion(Motion $motion)
  {
    $this->_motions[$motion->name()] = $motion;
  } 
  
  public function removeMotion($motion_name)
  {
    unset($this->_motions[$motion_name]);
  }
  
  /**
   * 最後尾のエレメントにアクセスする. 
   * 
   * <End /> です
   *
   * @return DOMElement
   */
  public function getEndElement($include_margin = false)
  {
    return $this->endFrame->currentNode($include_margin);
  }
  
  /**
   * Spriteが保有するMotionの内objectIDを使用しているMotionの数
   * 
   * @param $objectId
   * @return integer
   */
  public function getObjectUsedNum($objectId)
  {
    $i=0;
    foreach ($this->_motions as $motion) 
    {
      if ($motion->useObject($objectId)) $i++;
    }
    return $i;
  }
  
  private function parse(DOMElement $DefineSprite)
  {
    $this->_objectID = $DefineSprite->getAttribute('objectID');
    
    $motion = new Motion();
    $tags = $DefineSprite->getElementsByTagName('tags')->item(0);
    foreach ($tags->childNodes as $node) 
    {
      if ($node instanceof DOMText) 
      {
        continue;
      }
      $motion->addElement($node);
      
      if ($motion->isComplete()) 
      {
        $this->addMotion($motion);
        $motion = new Motion();
      } 
      elseif ($node->tagName == 'ShowFrame') 
      {
        // マージン中
        //echo "trace show frame";
      } 
      elseif ($motion->isEnd()) 
      {
        $this->_endElement = $node;
        break;
      }
    }
    $motion = null;
  }
}
