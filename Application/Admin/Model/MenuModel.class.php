<?php
// +----------------------------------------------------------------------
// | OneThink [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013 http://www.onethink.cn All rights reserved.
// +----------------------------------------------------------------------
// | Author: 凡星
// +----------------------------------------------------------------------
namespace Admin\Model;

use Think\Model;

/**
 * 插件模型
 *
 * @author 凡星
 */
class MenuModel extends Model {
	protected $tableName = 'menu';
	protected $_validate = array (
			array (
					'title',
					'require',
					'标题必须填写' 
			) 
	);
	
	/* 自动完成规则 */
	protected $_auto = array (
			array (
					'title',
					'htmlspecialchars',
					self::MODEL_BOTH,
					'function' 
			) 
	);
}