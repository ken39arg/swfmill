<?php
/**
 * Exception for Swmill library
 * 
 *
 * @copyright Copyright (C) 2009 KAYAC Inc.
 * @author Kensaku Araga <araga-kensaku@kayac.com>
 * @package swfmill
 * @since  2009/08/20
 * @version $Id: SwfmillException.class.php 1241 2010-01-25 09:43:55Z araga-kensaku $
 */
class SwfmillException extends sfException
{
  const DEFAULT_ERROR = 100;
  const NOT_FOUND     = 140;
  const COMMAND_ERROR = 180;
  const SYSTEM_ERROR  = 190;  
  
  public function __construct($message = "", $code = 00) 
  {
    if ($code == 0 || $code == null) 
    {
      $code = SwfmillException::DEFAULT_ERROR;
    }
    parent::__construct($message, $code);
  }
}
