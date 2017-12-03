<?php

namespace Addons\Coupon\Model;

use Think\Model;

/**
 * Coupon模型
 */
class CouponModel extends Model {
	function getInfo($id, $update = false, $data = array()) {
		$key = 'Coupon_getInfo_' . $id;
		$info = S ( $key );
		if ($info === false || $update) {
			$info = ( array ) (empty ( $data ) ? $this->find ( $id ) : $data);
			
			$more_button = wp_explode ( $info ['more_button'] );
			foreach ( $more_button as $v ) {
				$arr = explode ( '|', $v );
				$more_buttonArr [$arr [1]] = $arr [0];
			}
			$info ['more_button_arr'] = $more_buttonArr;
			
			S ( $key, $info, 86400 );
		}
		
		return $info;
	}
	function updateCollectCount($id, $update = false) {
		$key = 'Coupon_updateCollectCount_' . $id;
		$cache = S ( $key );
		
		$info = $this->getInfo ( $id );
		if (! $cache || $cache >= 100 || $update) {
			$info ['collect_count'] = D ( 'Common/SnCode' )->getCollectCount ( $id, 'Coupon' );
			
			// 更新数据库
			$this->where ( 'id=' . $id )->setField ( "collect_count", $info ['collect_count'] );
			
			$cache = 1;
		} else {
			// 更新缓存
			$info ['collect_count'] += 1;
			$cache += 1;
		}
		S ( $key, $cache, 300 );
		$this->getInfo ( $id, true, $info );
	}
	function update($id, $save = array()) {
		$map ['id'] = $id;
		$res = $this->where ( $map )->save ( $save );
		if ($res) {
			$this->getInfo ( $id, true );
		}
		return $res;
	}
	// 通用的清缓存的方法
	function clear($ids, $type = '', $uid = '') {
		is_array ( $ids ) || $ids = explode ( ',', $ids );
		
		foreach ( $ids as $id ) {
			$this->updateCollectCount ( $id, true );
			$this->getInfo ( $id, true );
		}
	}
	
	// 素材相关
	function getSucaiList($search = '') {
		$map ['token'] = get_token ();
		$map ['uid'] = session ( 'mid' );
		empty ( $search ) || $map ['title'] = array (
				'like',
				"%$search%" 
		);
		
		$data_list = $this->where ( $map )->field ( 'id' )->order ( 'id desc' )->selectPage ();
		foreach ( $data_list ['list_data'] as &$v ) {
			$data = $this->getInfo ( $v ['id'] );
			$v ['title'] = $data ['title'];
		}
		
		return $data_list;
	}
	function getPackageData($id) {
		$info = get_token_appinfo ();
		$param ['publicid'] = $info ['id'];
		$param ['id'] = $id;
		$data ['jumpURL'] = addons_url ( "Coupon://Wap/set_sn_code", $param );
		
		$data ['info'] = $this->getInfo ( $id );
		// 店铺地址
		$maps ['coupon_id'] = $id;
		$list = M ( 'coupon_shop_link' )->where ( $maps )->select ();
		$shop_ids = getSubByKey ( $list, 'shop_id' );
		if (! empty ( $shop_ids )) {
			$map_shop ['id'] = array (
					'in',
					$shop_ids 
			);
			$shop_list = M ( 'coupon_shop' )->where ( $map_shop )->select ();
			$data ['shop_list'] = $shop_list;
		}
		return $data;
	}
	// 赠送优惠券
	function sendCoupon($id, $uid) {
		$param ['id'] = $id;
		$info = $this->getInfo ( $id );
		
		$snDao = D ( 'Common/SnCode' );
		$snMap ['target_id'] = $info ['id'];
		$snMap ['addon'] = "Coupon";
		$info ['collect_count'] = $snDao->where ( $snMap )->count ();
		$flat = true;
		if ($info ['is_del']) {
			$flat = false;
		}
		if ($info ['collect_count'] >= $info ['num']) {
			$flat = false;
		} else if (! empty ( $info ['start_time'] ) && $info ['start_time'] > NOW_TIME) {
			$flat = false;
		} else if (! empty ( $info ['end_time'] ) && $info ['end_time'] < NOW_TIME) {
			$flat = false;
		}
		if ($uid <= 0) {
			$flat = false;
		}
		$list = $snDao->getMyList ( $uid, $id, 'Coupon' );
		$my_count = count ( $list );
		
		if ($info ['max_num'] > 0 && $my_count >= $info ['max_num']) {
			$flat = false;
		}
		if (! $flat)
			return false;
		
		$data ['target_id'] = $id;
		$data ['uid'] = $uid;
		$data ['addon'] = 'Coupon';
		$data ['sn'] = uniqid ();
		$data ['cTime'] = NOW_TIME;
		$data ['token'] = $info ['token'];
		
		$sn_id = $snDao->add ( $data );
		return $sn_id;
	}
	function getSelectList() {
		$map ['end_time'] = array (
				array (
						'gt',
						NOW_TIME 
				),
				array (
						'exp',
						'IS NULL' 
				),
				'or' 
		);
		$map ['token'] = get_token ();
		$map ['is_del'] = 0;
		$list = $this->where ( $map )->field ( 'id,title' )->order ( 'id desc' )->select ();
		return $list;
	}
	function getUnCollectWhere($uid, $is_public = null) {
		if ($is_public !== null) {
			$map ['is_public'] = $is_public;
		}
		$map ['token'] = get_token ();
		$map ['is_del'] = 0;
		// 搜索条件
		$map ['end_time'] = array (
				array (
						'gt',
						NOW_TIME 
				),
				array (
						'exp',
						'IS NULL' 
				),
				'or' 
		);
		$map ['start_time'] = array (
				array (
						'lt',
						NOW_TIME 
				),
				array (
						'exp',
						'IS NULL' 
				),
				'or' 
		);
		$map ['collect_count'] = array (
				'exp',
				'<= `num`' 
		);
		
		// 获取用户的会员等级
		$levelInfo = D ( 'Addons://Card/CardLevel' )->getCardMemberLevel ( $uid );
		// 读取模型数据列表
		// dump($map);
		$map ['member'] = array (
				array (
						'LIKE',
						'%,0,%' 
				),
				array (
						'LIKE',
						'%,-1,%' 
				) 
		);
		if ($levelInfo) {
			$levelId = $levelInfo ['id'];
			$map ['member'] [] = [ 
					'LIKE',
					"%,{$levelId},%" 
			];
		}
		$map ['member'] [] = 'or';
		
		$map2 = $conponArr = [ ];
		
		$map2 ['uid'] = $uid;
		$map2 ['addon'] = 'Coupon';
		$snCode = M ( 'sn_code' )->where ( $map2 )->getFields ( 'target_id' );
		if ($snCode) {
			$map ['id'] = array (
					'not in',
					$snCode 
			);
		}
		
		return $map;
	}
}
