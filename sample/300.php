<?php
// MC と 画像の入れ替え
require_once 'include.php';

// SWFMILLコマンドの設定
//FlashLiteToolkit::setConfig(array('swfmill_command' => 'swfmill'));

// アイテムの読み込み
// XMLを読み込むことも可
$base    = new Swfmill('swf/300_base.swf');
$item1   = new Swfmill('swf/300_item_1.swf');
$item2   = new Swfmill('swf/300_item_2.swf');
$item3   = new Swfmill('swf/300_item_3.swf');
$eye     = new Swfmill('swf/300_eye_1.swf');
$motion2 = new Swfmill('swf/300_motion_2.swf');
$motion3 = new Swfmill('swf/300_motion_3.swf');
$motion6 = new Swfmill('swf/300_motion_6.swf');

// MCチェンジ
$base->changeInstance('kon/h/h_m', $item1, 'kon/h/h_m');
$base->changeInstance('kon/ll/ll_i', $item1, 'kon/ll/ll_i');
$base->changeInstance('kon/lr/lr_i', $item1, 'kon/lr/lr_i');
$base->changeInstance('kon/ali',   $item2, 'kon/ali');
$base->changeInstance('kon/ari',   $item2, 'kon/ari');
$base->changeInstance('kon/h/h_i', $item3, 'kon/h/h_i');
$base->changeInstance('kon.h.h_e', $eye, 'kon.h.h_e');

// Shapeの色を変更
$base->changeFillColor('kon.b',  '#ff0000');
$base->changeFillColor('kon.al', '#ff0000');
$base->changeFillColor('kon.ar', '#ff0000');

// モーションチェンジ
$base->changeMotion('kon', 'act1', 'a_01', $motion2);
$base->changeMotionLabel('kon', 'a_01', 'act1');

$base->changeMotion('kon', 'act2', 'matrix', $motion3);
$base->changeMotionLabel('kon', 'matrix', 'act2');

$base->changeMotion('kon', 'spl', 'sp_l1', $motion6);
$base->changeMotionLabel('kon', 'sp_l1', 'spl');

// 変数
$base->changeActionVariable(array('cp' => '80', 'fp' => '90', 'hp' => '85', 'fa' => 'spl'));

echo $base->getSwfBinary();
