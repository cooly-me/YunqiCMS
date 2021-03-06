<?php defined('CORE_DIR') || exit('入口错误'); ?>
<?php
/**
 * ctl_gtype
 *
 * @uses adminPage
 * @package
 * @version $Id: ctl.gtype.php 1928 2008-04-25 02:13:05Z alex $
 * @copyright 2003-2007 ShopEx
 * @author Wanglei <flaboy@zovatech.com>
 * @license Commercial
 */
include_once('objectPage.php');
class ctl_gtype extends objectPage{

    var $workground = 'goods';
    var $object = 'goods/gtype';
    var $finder_action_tpl = 'product/gtype/finder_action.html';
    var $allowImport = true;
    var $finder_default_cols = '_cmd,name,is_physical';
    var $allowExport = false;
    var $ioType = 'gtype';
    var $filter=array('schema_id'=>'custom');
    var $filterUnable = true;

    function index($params1,$params2){
        parent::index(array('params'=>array('is_def'=>'false')));
    }

    function finder_pager(){
        $_POST['is_def']='false';
        parent::finder_pager();
    }

    function save_cell_value($id,$key){
        if(empty($_POST['data'])){
            $this->begin('index.php?ctl=goods/gtype&act=index');
            trigger_error(__('类型名称为必填项'),E_USER_ERROR);
            $this->end();
        }
        if( $key == 'name'){
            $otype = $this->system->loadModel('goods/gtype');
            if($otype->checkTypeByName( $_POST['data'],$id ) ){
                $otype->nameSave($id, $_POST['data'] );
                parent::save_cell_value($id,$key);
            }
        }

     }

    function newType(){
        $this->path[] = array('text'=>__('添加类型'));
        $objGschema = &$this->system->loadModel('goods/schema');
        $aSchema = $objGschema->getList();
        foreach($aSchema as $k=>$item){
            if($item['is_def']){
                unset($aSchema[$k]);
            }
        }
        $this->pagedata['schemas'] = &$aSchema;
        $this->title=__('new goods type');
        $this->page('product/gtype/newType.html');
    }

    function toAdd($schema, $tag){
        if($tag == 'commit'){
            $_SESSION['gtype']['setting'] = $_POST['setting'];
            $_SESSION['gtype']['is_physical'] = $_POST['is_physical'];
            if ( $_SESSION['gtype']['alias'] == 'a:1:{i:0;s:0:"";}' ){
                unset($_SESSION['gtype']['alias']);
            }
            $this->editType();
        }else{
            unset($_SESSION['gtype']);
            $_SESSION['gtype']['schema_id'] = $schema;
            $objGschema = &$this->system->loadModel('goods/schema');
            $objGschema->dialog($schema);
        }
    }

    function toEdit($typeid){
        if($typeid){
            $gtype = &$this->system->loadModel('goods/gtype');
            $aType = $gtype->getTypeDetail($typeid, true);
            $_SESSION['gtype'] = $aType;
            $_POST['setting'] = $aType['setting'];
            $_POST['is_physical'] = $aType['is_physical'];
        }

        $objGschema = &$this->system->loadModel('goods/schema');
        $objGschema->dialog($aType['schema_id']);
    }

    function editType(){
        $this->path[] = array('text'=>__('类型编辑'));

        $aType = $_SESSION['gtype'];
        $this->pagedata['gtype'] = $aType;
        $brand = &$this->system->loadModel('goods/brand');
        foreach($brand->getAll() as $rows){
            $aTmpList[$rows['brand_id']] = $rows['brand_name'];
        }
        $this->pagedata['brands'] = $aTmpList;
        $this->page('product/gtype/workpage.html');
    }

    function toSave(){
        if (empty($_POST['name'])){
            $this->begin('index.php?ctl=goods/gtype&act=newType');
            trigger_error(__('类型名称不能为空'),E_USER_ERROR);
            $this->end();
        }
        $this->begin('index.php?ctl=goods/gtype&act=index');
        $objGtype = &$this->system->loadModel('goods/gtype');
        if(isset($_SESSION['gtype']['is_physical'])) $_POST['is_physical'] = $_SESSION['gtype']['is_physical'];
        if(isset($_SESSION['gtype']['schema_id'])) $_POST['schema_id'] = $_SESSION['gtype']['schema_id'];
        if(isset($_SESSION['gtype']['setting'])) $_POST['setting'] = $_SESSION['gtype']['setting'];
        if(isset($_SESSION['gtype']['dly_func'])) $_POST['dly_func'] = $_SESSION['gtype']['dly_func'];
        if(isset($_SESSION['gtype']['ret_func'])) $_POST['ret_func'] = $_SESSION['gtype']['ret_func'];
        if(isset($_SESSION['gtype']['reship'])) $_POST['reship'] = $_SESSION['gtype']['reship'];
        if(isset($_SESSION['gtype']['disabled'])) $_POST['disabled'] = $_SESSION['gtype']['disabled'];
        if(isset($_SESSION['gtype']['is_def'])) $_POST['is_def'] = $_SESSION['gtype']['is_def'];
        
         //对比保存前后属性 by zhoulei 2011-10-12
        $aType = $_SESSION['gtype']['props'];
        $bType = $_POST['props']['name'];

        //保存后的数组key+1
        foreach($bType as $k=>$v){
            $bType[$k+1] = $v;
        }
        unset($bType[0]);
        $updateKey = $tempKey = $updatestr = array();
        foreach ($aType as $ak=>$av){
            $bk = array_search($av['name'],$bType);
            //删除的
            if ($bk === false){
                $updateKey[] = $ak;
            }elseif(in_array($_POST['props']['type'][$bk-1],array(0,1))){
                $updateKey[] = $ak;
            }else{//保留的
                $tempKey[] = $ak;
                $bind[$ak] = $bk-1;
            }
        }
        
        $num = count($bType);
        //重排保留下的属性从p_1开始
        foreach ($tempKey as $k=>$tk){
            if($objGtype->getTypeSearch($aType[$tk]['type'],$aType[$tk]['search']) != $_POST['props']['type'][$bind[$tk]]){
                $updatestr[] = ' p_'.($k+1).' = NULL ';
            }else{
                $updatestr[] = ' p_'.($k+1).' = p_'.$tk.' ';
            }
        }

        //置空最大属性值 个数为删除个数
        if($num < count($aType)){
            $max_key = count($aType);
            for($i=0;$i<count($updateKey);$i++){
                $updatestr[] = ' p_'.($max_key-$i).' = NULL ';
            }
        }
        $type_id = $_POST['type_id'];
        //更新商品属性
        if($updatestr){
            $objGtype->setGoodsProp($updatestr,$type_id);
        }
        
        
        /**
        * 属性值在在删除和修改顺序时判断后面属性是否有关联商品，如果有禁止修改
        **/
        //扩展属性值更新处理 2013-06-17 by Fu
        $old_Type = $_SESSION['gtype']['props'];
        $new_Type = $_POST['props'];

        foreach($old_Type as $o_k=>$o_v){           
            foreach($new_Type['name'] as $n_k=>$n_v){
                
                if($o_v['name'] == $n_v){
                    $n_options = explode(',',trim($new_Type['options'][$n_k]));
                    $o_options = $o_v['options'];
                    //属性删除处理
                    if(count($n_options) < count($o_options)){
                        $ochaji = array_diff($o_options,$n_options);
                        foreach($ochaji as $k=>$v){
                            for($i=$k;$i<=count($o_options)-1;$d[]=$i,$i++);
                            //统计数据库中已使用数量
                            $otype_count = $objGtype->countGoodsProp($o_k,$type_id,$d);
                            if($otype_count['count'] > 0){
                                $this->end(false,__('很抱歉！修改失败！您此处的修改会导致'.$otype_count['count'].'个商品的扩展属性关联错误！'));
                                exit;
                            }
                        }
                    }elseif(count($n_options) > count($o_options)){
                        //属性新增处理
                        $nchaji = array_diff($n_options,$o_options);
                        if($nchaji){
                            foreach($nchaji as $k=>$v){
                                for($i=$k;$i<=count($o_options)-1;$d[]=$i,$i++);
                                $d_count = $objGtype->countGoodsProp($o_k,$type_id,$d);
                            }
                            if($d_count['count'] > 0){
                                $option = array_merge($old_Type[$o_k]['options'],$nchaji);
                                $_POST['props']['options'][$o_k-1] = implode(',',$option);  
                            }                           
                        }
                    }else{
                        //属性顺序调整处理
                        $nchaji = array_diff($n_options,$o_options);                        
                        if( empty($nchaji) ){
                            $aa = 0;
                            foreach($n_options as $n_k=>$n_v){
                                if($n_v !== $o_options[$n_k]){
                                    $aa = 1;
                                }
                                if($aa == 1){
                                    for($i=$n_k;$i<=count($o_options)-1;$e[]=$i,$i++);
                                    $otype_count_ = $objGtype->countGoodsProp($o_k,$type_id,$e);
                                    if($otype_count_['count'] > 0){
                                        $this->end(false,__('很抱歉！您所修改属性值已在使用，修改属性值顺序会影响商品关联的扩展属性，请不要随意修改！'));
                                        exit;
                                    } 
                                }
                            }                            
                        }
                    }                    
                }
            }
        }
        
        $this->end($objGtype->toSave($_POST), __('保存成功'));
    }

    function delete(){
        $this->begin('index.php?ctl=goods/gtype&act=index');
        $objType = &$this->system->loadModel('goods/gtype');
        if(is_array($_REQUEST['type_id']))
            foreach($_REQUEST['type_id'] as $id){
                $objType->toRemove($id);
            }
        $objType->checkDefined();
        $this->end_only(true);
        echo __('删除成功');
    }

    function recycle(){
        $objType = &$this->system->loadModel('goods/gtype');
        $varGoto = 1;
        foreach($_REQUEST['type_id'] as $type_id){
            if(!$objType->checkDelete($type_id, $result)){
                if($result == 1){
                    echo __('通用商品类型为系统默认类型，不能删除');
                }
                if($result == 2){
                    echo __('类型下存在与之关联的商品，无法删除');
                }
                $varGoto = 0;
                break;
            }
        }
        if($varGoto){
            parent::recycle();
            $objType = &$this->system->loadModel('goods/gtype');
            $objType->checkDefined();
        }
    }

    function fetchProtoTypes($link,$querystring=''){
        header('Content-Type: text/html;charset=utf-8');

        $net = $this->system->network();
        $cert = $this->system->getConf('certificate.id');
        $token = $this->system->getConf('certificate.token');
        $sc = md5('goostypefeed'.$cert.$token);
        $url = 'http://feed.shopex.cn/goodstype/'.$link.'?certificate='.$cert.'&sc='.$sc.($querystring?'&'.$querystring:'');
        $net->fetch($url);
        $result = $net->results;
        if($result && false!==substr($result,'shopexfeed')){
            echo $result;
        }else{
            echo '<div style="width:300px;height:80px;"><BR><BR>'.__('因网络连接或其它原因，暂时无法获取系统默认类型信息。<BR>请稍候再试...错误信息').$net->responseCode.'</div><div style="clear:both">';
        }
    }

    function getXml($id){
        $o = &$this->system->loadModel('goods/gtype');
        $xml = &$this->system->loadModel('utility/xml');
        $xmlpart = $xml->array2xml($o->getTypeObj($id,$name),'goodstype');
        $charset = &$this->system->loadModel('utility/charset');
        download($name.'.typ',$xmlpart);
    }

    function saveSpec(){
        $this->begin('index.php?ctl=goods/gtype&act=index');
        $objType = &$this->system->loadModel('goods/gtype');
        $this->end($objType->saveSpec($_POST['type_id'], $_POST['specs']), __('保存成功'));
    }

    function fetchSave(){
        $this->begin('index.php?ctl=goods/gtype&act=index',array(300001=>'index.php?ctl=goods/gtype&act=fetchProtoTypes&p[0]=gtype.php&p[1]=id='.$_POST['param_id']));
        $xml = &$this->system->loadModel('utility/xml');
        $map =  $xml->xml2array($_POST['xml']);
$gtype = $map['goodstype'];
        $gtype['name'] = $_POST['gtypename'];
        $o = &$this->system->loadModel('goods/gtype');
        $this->end($o->fetchSave($gtype), __('类型导入成功'));
    }
    function import(){
        $dataio = &$this->system->loadModel('system/dataio');
        $dataio->privateImport = true;
        $this->pagedata['importer'] = $dataio->importer($this->ioType);
        $this->pagedata['ctl'] = 'goods/gtype';
        $this->pagedata['optionsView'] = $this->importOptions;
        $this->page('finder/import.html');
    }

    function checkTypeNameExists(){
        $o = $this->system->loadModel('goods/gtype');
        if($o->getList('type_id',array('name'=>$_POST['gtypename']))){
            echo '<script>alert("本类型名在系统中已存在，请更名");</script>';
        }else{
            echo '<script>alert("本类型名在系统中不存在，可正常添加");</script>';
        }
    }
    function importer(){
        $this->begin('index.php?ctl=goods/gtype&act=index');
        $dataio = &$this->system->loadModel('system/dataio');
        $gtype = &$this->system->loadModel('goods/gtype');
        if(substr($_FILES['upload']['name'],-4)!='.typ'){
            trigger_error(__('文件格式有误'),E_USER_ERROR);
            exit;
        }

        $content = file_get_contents($_FILES['upload']['tmp_name']);
        if(substr($content,0,3)=="\xEF\xBB\xBF")
            $content = substr($content,3);
        $data = $dataio->import_rows($_POST['type'],$content);
        $imported = false;
        foreach($data as $type){
            if($type['name']){
                $type['name'] = $type['name'].time();
                $gtype->fetchSave($type);
                $imported = true;
            }
        }
        if($imported){
            $this->end(true,__('类型数据导入成功,请修改自动生成的类型名'));
        }else{
            trigger_error(__('由于格式错误或上传问题，类型数据无法导入'),E_USER_ERROR);
        }
    }

    function typeTransformCheck($type){
        $oGtype = $this->system->loadModel('goods/gtype');
        $_POST['goods']['spec_desc'] = unserialize(urldecode($_POST['goods']['spec_desc']));
        if($type == 'cat'){
            $oCat = $this->system->loadModel('goods/productCat');
            $aTmp = $oCat->getFieldById($_POST['goods']['cat_id'],array('type_id'));
            $_POST['goods']['type_id'] = $aTmp['type_id'];
        }
        if( $oGtype->typeTransformCheck($_POST['oldTypeId'],$_POST['goods']['type_id'],$_POST['goods']) ){
            echo '1';
        }else{
            $errorMsg = ' ';
            foreach( $oGtype->specTransformError as $sv ){
                $errorMsg .= $sv['spec_name'].' (';
                $errorMsg .= implode('、',array_values($sv['spec_value']));
                $errorMsg .= ') ';
            }
            echo '该商品的'.$errorMsg.'找不到匹配项';
        }

    }
}
