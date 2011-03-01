<?php
/**
 * Motionを構成するDomElementの集合
 *
 * @copyright Copyright (C) 2009 KAYAC Inc.
 * @author Kensaku Araga <araga-kensaku@kayac.com>
 * @package swfmill
 * @since  2009/08/13
 * @version $Id: Motion.class.php 1241 2010-01-25 09:43:55Z araga-kensaku $
 */
class Motion
{
  //protected
  protected
    $_name,
    $_depth_stack,
    $_objects,
    $_frames,
    $_parent_frame,
    $_complete,
    $_is_end,
    $_pre_margin,
    $_post_margin;

  public function __construct()
  {
    $this->_frames      = array();
    $this->_objects     = array();
    $this->_depth_stack = array();
    $this->_complete    = false;
    $this->_is_end      = false;
    $this->_pre_margin  = array();
    $this->_post_margin = array();
  }

  /**
   * 要素を追加する
   * @param DOMElement $element
   * @return void
   */
  public function addElement(DOMElement $element)
  {
    //echo $element->tagName."\n";
    $this->_parent_frame[] = $element;
    switch ($element->tagName) 
    {
      case "FrameLabel":
        $this->setName($element->getAttribute('label'));
        break;
      case "End":
        $this->setEnd(true);
        break;
      case "RemoveObject":
      case "RemoveObject2":
        $this->removeObject($element->getAttribute('depth'));
        break;
      case "PlaceObject":
      case "PlaceObject2":
        if ($element->hasAttribute('objectID')) 
        {
          $this->setObject($element->getAttribute('depth'),
          $element->getAttribute('objectID'));
        }
        break;
      case "ShowFrame":
        $this->commitFrame();
        break;
    }
  }

  protected function setEnd($v)
  {
    $this->_is_end   = $v;
    $this->_frames[] = $this->_parent_frame;
    $this->_complete = true;
  }

  protected function commitFrame()
  {
    if ($this->_name == null) 
    {
      // label名がセットされていないのは無所属motion
      $this->_pre_margin[] = $this->_parent_frame;
    } 
    elseif ($this->_complete)
    {
      $this->_post_margin[] = $this->_post_margin;
    } 
    else 
    {
      $this->_frames[] = $this->_parent_frame;
      if (count($this->_depth_stack) == 0) 
      {
        // depthに何も無くなったらこのモーションは終了
        $this->_complete = true;
      }
    }
    $this->_parent_frame = array();
  }

  protected function setName($name)
  {
    if (!isset($this->_name)) 
    {
      $this->_name = $name;
    }
  }

  protected function removeObject($depth)
  {
    if (isset($this->_depth_stack[$depth])) 
    {
      unset($this->_depth_stack[$depth]);
    }
  }

  protected function setObject($depth, $objectID)
  {
    if ( !isset($this->_depth_stack[$depth]) ) 
    {
      $this->_depth_stack[$depth] = $objectID;
    }
      
    if (!in_array($objectID, $this->_objects)) 
    {
      $this->_objects[] = $objectID;
    }
  }

  /**
   * Motionの名前
   * @return String
   */
  public function name()
  {
    return $this->_name;
  }

  /**
   * アクティブフレームのフレーム数
   * @return Integer
   */
  public function frameLength()
  {
    return count($this->_frames);
  }

  /**
   * 完結しているMotionかどうか
   * 
   * 簡潔とはmargin_frameからmargin_frameまで達した事
   * 
   * @return boolean
   */
  public function isComplete()
  {
    return $this->_complete;
  }

  /**
   * このMotionがEndMotionかどうか
   * @return unknown_type
   */
  public function isEnd()
  {
    return $this->_is_end;
  }

  /**
   * 使用しているObjectのIDリストを取得
   * @return Array[1,2,...]
   */
  public function getObjects()
  {
    return $this->_objects;
  }

  /**
   * $objectIDを使用してるかどうか
   * @param $objectID
   * @return Boolean
   */
  public function useObject($objectID)
  {
    if (in_array($objectID, $this->_objects)) 
    {
      return true;
    }
    return false;
  }

  /**
   * Frameの二次元配列を取得する
   * @return Array[
   *            [DOMElement,DOMElement,...], // 最後のNodeは必ずShowFrame
   *            [DOMElement,DOMElement,...],...
   *         ]
   */
  public function getFrames()
  {
    return $this->_frames;
  }

  /**
   * 最初のDOMNode
   *
   * @return DOMElement
   */
  public function currentNode($include_margin = false)
  {
    if ($include_margin) 
    {
      return $this->_pre_margin[0][0];
    }
    return $this->_frames[0][0];
  }

  /**
   * 最初のFrame
   * @return Array[DOMElement,DOMElement,...]
   */
  public function currentFrame($include_margin = false)
  {
    if ($include_margin) 
    {
      return $this->_pre_margin[0];
    }
    return $this->_frames[0];
  }

  /**
   * アクティブレコードの前のマージンサイズ
   * @return Inteer
   */
  public function preMarginLength()
  {
    return count($this->_pre_margin);
  }
  
  /**
   * アクティブレコード後のマージンサイズ
   * 
   * 基本的にpreにマージンが入るので空のはず
   * 
   * @return Integer
   */
  public function postMarginLength()
  {
    return count($this->_post_margin);
  }
  
  public function getPreMargin()
  {
    $ret = array();
    foreach ($this->_pre_margin as $m) 
    {
      $ret = array_merge($ret, $m);
    }
    return $ret;
  }

  public function getPostMargin()
  {
    $ret = array();
    foreach ($this->_post_margin as $m) 
    {
      $ret = array_merge($ret, $m);
    }
    return $ret;
  }  
}
