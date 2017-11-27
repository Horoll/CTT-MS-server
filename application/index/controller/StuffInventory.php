<?php
/**
 * Created by PhpStorm.
 * User: Horol
 * Date: 2017/11/21
 * Time: 9:53
 */

namespace app\index\controller;
use think\Request;
use think\Db;

class StuffInventory extends Base
{
    public function __construct()
    {
        parent::__construct();
        //查询$authList中是否有该操作的权限
        if($this->authList->stuff_inventory == 0){
            die(json_encode(['state'=>'warning','message'=>'没有查看材料发放记录权限'],JSON_UNESCAPED_UNICODE));
        }
        //尝试实例化StuffOutRecord的模型类和验证器类，并且赋值给$model和$validate
        //若这两个类不存在，则抛出异常，返回错误信息
        try {
            $this->model = new \app\index\model\StuffOutRecord();
            $this->validate = new \app\index\validate\StuffOutRecord();
        }catch (Exception $e){
            die($e->getMessage());
        }
    }

    //根据inventory表的信息，查询stuff_in
    private function stuffIn(){

    }

    //根据inventory表的信息，查询stuff_out
    private function stuffOut(){

    }

    //根据inventory表的信息，查询stuff_leave
    private function stuffLeave(){

    }


    //材料盘存
    public function check($storehouse=null,$startDate=null,$endDate=null,$query=null){
        if(empty($storehouse))
            $storehouse = $this->user['storehouse'];
        $res = Db::table('inventory')
            ->where('storehouse',$storehouse)
            ->distinct(true)
            ->field('stuff_id')
            ->select();
        $data = [];
        foreach ($res as $itern){
            $stuffId = $itern['stuff_id'];
            $li['stuff_id']=$stuffId;
            $li['storehouse'] = $storehouse;

            //查询stuff表中的材料名称和单位
            $stuffInfo =
                Db::table('stuff')
                    ->where('id',$stuffId)
                    ->find();
            $li['stuff_name'] = $stuffInfo['stuff_name'];
            $li['unit'] = $stuffInfo['unit'];

            //查询当前存量
            $nowQuantity = Db::table('inventory')
                ->where('stuff_id',$stuffId)
                ->sum('quantity');
            $li['now_quantity'] = $nowQuantity;

            //查询时间段内，入库的数量
            $stuffInId = Db::table('inventory')
                ->where('stuff_id',$stuffId)
                ->column('stuff_in_record_id');
            $inQuantity = 0;
            foreach ($stuffInId as $id){
                $inQuantity +=
                    Db::table('stuff_in_record')
                    ->where('id',$id)
                    ->where('stuff_in_date','between',[$startDate,$endDate])
                    ->value('quantity');
            }
            $li['in_quantity'] = $inQuantity;

            //查询时间段内，调离和发放的数量
            $outQuantity = 0;
            $inventoryId = Db::table('inventory')
                ->where('stuff_id',$stuffId)
                ->column('id');
            foreach ($inventoryId as $id){
                $outQuantity +=
                    Db::table('stuff_out_record')
                    ->where('inventory_id',$id)
                    ->where('out_date','between',[$startDate,$endDate])
                    ->where('is_out',5)
                    ->sum('out_quantity');
                $outQuantity +=
                    Db::table('stuff_leave_record')
                    ->where('inventory_id',$id)
                    ->where('send_date','between',[$startDate,$endDate])
                    ->where('is_receive',1)
                    ->sum('leave_quantity');
            }
            $li['out_quantity'] = $outQuantity;
            $li['before_quantity'] = $nowQuantity-$inQuantity+$outQuantity;

            array_push($data,$li);
        }
        dump($data);
    }
}