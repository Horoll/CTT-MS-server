<?php
/**
 * Created by PhpStorm.
 * User: Horol
 * Date: 2017/10/24
 * Time: 21:55
 */

namespace app\index\controller;


class StuffIn extends Base
{
    //检测该用户是否有材料入库权限
    public function __construct()
    {
        parent::__construct();
        //查询$authList中是否有该操作的权限
        if($this->authList->stuff_in == 0){
            die(json_encode(['state'=>'warning','message'=>'没有材料入库权限管理权限'],JSON_UNESCAPED_UNICODE));
        }
        //尝试实例化StuffInRecord的模型类和验证器类，并且赋值给$model和$validate
        //若这两个类不存在，则抛出异常，返回错误信息
        try {
            $this->model = new \app\index\model\StuffInRecord();
            $this->validate = new \app\index\validate\StuffInRecord();
        }catch (Exception $e){
            die($e->getMessage());
        }
    }

    //新增入库记录和库存
    public function stuffIn(){
//        $json = input('json');
//        $data = json_decode($json,true);
        $data = ['stuff_id'=>1,'manufacturer'=>'咪咕','type'=>'小猫','quantity'=>200,'storehouse'=>'丹棱库','stuff_in_date'=>'2017-10-25 21:20'];

        //检测请求数据在数据库中是否真的存在
        if(!dataIsExist('manufacturer','manufacturer',$data['manufacturer']))
            return returnWarning('该生产商不存在!');
        if(!dataIsExist('storehouse','name',$data['storehouse']))
            return returnWarning('该仓库不存在!');

        //检测此用户是否管辖该仓库
        $userStorehouse = db('user')->where('cookie_username',$this->cookieUsername)->value('storehouse');
        if($userStorehouse!=$data['storehouse'])
            return returnWarning('该管理员无权管理该仓库!');

        //通过cookie来找到当前管理员姓名
        $operator = db('user')->where('cookie_username',$this->cookieUsername)->value('name');
        $data['operator'] = $operator;

        //添加记录到StuffInRecord模型
        $res = Manage::add($this->model,$this->validate,$data);
        if($res['state']!='success') return json($res);

        //添加记录到Inventory模型
        $data['stuff_in_record_id'] = $this->model->id;
        return json(Manage::add(new \app\index\model\Inventory(),new \app\index\validate\Inventory(),$data));
    }

    //修改入库记录
    public function change(){
        $json = input('json');
        $data = json_decode($json,true);

        //检查是否在一小时之内
        if(time()-strtotime($data['stuff_in_date'])>3600)
            return returnWarning('已经超出可修改时间范围');
        unset($data['stuff_in_date']);

        //检查是否是本管理员
        $opertor = db('stuff_in_record')->where('id',$data['id'])->value('operator');
        $userName = getUser()['name'];
        if(!($opertor==$userName && $userName==$data['operator']))
            return returnWarning('你不是该入库材料经办人，无权修改');

        //修改stuff_in_record表
        $res = Manage::change($this->model,$this->validate,$data);
        if($res['state']!='success') return json($res);

        //修改inventory表
        return json(Manage::add(new \app\index\model\Inventory(),new \app\index\validate\Inventory(),$data));
    }

    //查看入库记录
    public function check(){
        $json ='{
	"pageinfo":{"curpage":2,"pageinate":3},
	"order":"id desc",//按id的倒序排列
	"condition":{
				//condition 里允许有多个条件，彼此的关系为and，如果要查询不同字段相同条件的or数据，请用|分隔开
	            "where":["on_guard","是"],//查询所有on_guard为是的数据
	            "like":["","%%"],
	            "between":["stuff_in_date",["2017-10-01","2017-10-08"]]
	            }
}';
        $array = json_decode($json,true);
        $pageinfo = $array['pageinfo'];
        unset($array['pageinfo']);
        $limit = $array;
        $userStorehouse = getUser()['storehouse'];
        //查询登录用户所在的仓库的入库记录
        $filed = ['a.*','b.*'];
        //return json(Manage::checkJoin($this->model,'stuff','stuff_id','id',$filed,$pageInfo));
        $result = $this->model
            ->alias('a')
            ->join('stuff b','a.stuff_id = b.id')
            ->where('storehouse',$userStorehouse);
        $result1 = $this->model
            ->alias('a')
            ->join('stuff b','a.stuff_id = b.id')
            ->where('storehouse',$userStorehouse);
        $order = isset($limit['order'])?$limit['order']:'id';
        //若排序条件为normal，则将$oeder赋值为null，默认顺序
        $con = explode(' ',$order);
        if(isset($con[1]) && $con[1]=='normal') $order='id';
        foreach ($limit['condition'] as $keyword=>$value){
            if($keyword=='where'){
                if(isset($value[0])&&isset($value[1])){
                    $result = $result->where($value[0],$value[1]);
                    $result1 = $result1->where($value[0],$value[1]);
                }
                else{
                    $result = $result->where(1);
                    $result1 = $result1->where(1);
                }
            }
            else{
                $result = $result->where($value[0],$keyword,$value[1]);
                $result1 = $result1->where($value[0],$keyword,$value[1]);
            }
        }
        $result = $result ->order($order)->page($pageinfo['curpage'],$pageinfo['pageinate'])->select();
        $dataCount = count($result1->select());
        array_unshift($result,['datacount'=>$dataCount]);
        return json($result);
    }
}