<?php
// Exit if accessed directly
if( !defined( 'ABSPATH' ) ) exit;

if(!class_exists('vxg_ccontact_api')){
    
class vxg_ccontact_api extends vxg_ccontact{
  
    public $info='' ; // info
    public $url='https://api.cc.email/v3/';
    public $auth_url='https://authz.constantcontact.com/oauth2/default/v1/token';
    public $api_key='';
    public $error= "";
    public $timeout= "15";

function __construct($info) {
     
    if(isset($info['data'])){ 
       $this->info= $info['data'];
    } 
}
public function get_token(){
    $info=$this->info;
  $users=$this->get_lists(); 
  if(is_array($users) && count($users)>0){
    $info['valid_token']='true';    
    }else{
        $info['error']=$users;
     // unset($info['access_token']);  
      unset($info['valid_token']);  
    }
     $info['_time']=time(); 
    return $info;
}
/**
  * Get New Access Token from infusionsoft
  * @param  array $form_id Form Id
  * @param  array $info (optional) Infusionsoft Credentials of a form
  * @param  array $posted_form (optional) Form submitted by the user,In case of API error this form will be sent to email
  * @return array  Infusionsoft API Access Informations
  */
public function refresh_token($info=""){
  if(!is_array($info)){
  $info=$this->info;
  }
  if(!isset($info['refresh_token']) || empty($info['refresh_token'])){
   return $info;   
  }
  $client=$this->client_info(); 
  ////////it is oauth    
  $body=array("client_id"=>$client['client_id'],"client_secret"=>$client['client_secret'],"redirect_uri"=>$client['call_back'],"grant_type"=>"refresh_token","refresh_token"=>$info['refresh_token']);
  $re=$this->post_crm($this->auth_url,'token',$body);

  if(isset($re['access_token']) && $re['access_token'] !=""){ 
  $info["access_token"]=$re['access_token'];
  if(!empty($re['refresh_token'])){
  $info["refresh_token"]=$re['refresh_token'];
  }
 // $info["org_id"]=$re['id'];
  $info["class"]='updated';
  $info["token_time"]=time(); 
  $info['valid_token']='true'; 
  }else{
      $info['valid_token']=''; 
  $info['error']=isset($re['errorSummary']) ? $re['errorSummary'] : '';
  $info['access_token']="";
   $info["class"]='error';
 // $this->log_msg("Auto Token Error ".$res);
  }
  //api validity check
  $this->info=$info;
  //update infusionsoft info 
  //got new token , so update it in db
  $this->update_info( array("data"=> $info),$info['id']); 
  return $info; 
  }
public function handle_code(){
      $info=$this->info;
      $id=$info['id'];
 
        $client=$this->client_info();
  $log_str=array(); $token=array();
  if(isset($_REQUEST['code'])){
  $code=$this->post('code'); 
  if(!empty($code)){
  $body=array("client_id"=>$client['client_id'],"client_secret"=>$client['client_secret'],"redirect_uri"=>$client['call_back'],"grant_type"=>"authorization_code","code"=>$code);
  $token=$this->post_crm($this->auth_url,'token',$body);
  }
  }
  if(isset($_REQUEST['error'])){
   $token['error_description']=$this->post('error');   
  }

  $info['access_token']=$this->post('access_token',$token);
  $info['client_id']=$client['client_id'];
  $info['_id']=$this->post('id',$token);
  $info['refresh_token']=$this->post('refresh_token',$token);
 // $info['issued_at']=round($this->post('issued_at',$token)/1000);
  $info['signature']=$this->post('signature',$token);
  $info['token_time']=time();
  $info['_time']=time();
  $info['error']=$this->post('error_description',$token);
  $info['api']="api";
  $info["class"]='error';
  $info['valid_token']=''; 
  if(!empty($info['access_token'])){
  $info["class"]='updated';
  $info['valid_token']='true'; 
  }
  $this->info=$info;
 // $info=$this->validate_api($info);
  $this->update_info( array('data'=> $info) , $id); //var_dump($res,$info); die();
  return $info;
  }
    /**
  * Get Infusionsoft Client Information
  * @param  array $info (optional) Infusionsoft Client Information Saved in Database
  * @return array Infusionsoft Client Information
  */
public function client_info(){
      $info=$this->info;
  $client_id='d806eeec-5890-43b3-b380-c8ad701c42e5';
  $client_id='';
  $client_secret='dnJSXk9qFqu9ARIPb7cKqw';
  $call_back="https://www.crmperks.com/nimble_auth/";
  //custom app
  if(is_array($info)){
      if( $this->post('app_id',$info) !="" && $this->post('app_secret',$info) !="" && $this->post('app_url',$info) !=""){
     $client_id=$this->post('app_id',$info);     
     $client_secret=$this->post('app_secret',$info);     
     $call_back=$this->post('app_url',$info);     
      }
  }
  return array("client_id"=>$client_id,"client_secret"=>$client_secret,"call_back"=>$call_back);
  }

public function get_crm_fields(){

$res=array('first_name'=>array('label'=>'First Name','name'=>'first_name','type'=>'text'),
'last_name'=>array('label'=>'Last Name','name'=>'last_name','type'=>'text'),

'email_address'=>array('label'=>'Email','name'=>'email_address','type'=>'email','req'=>'true'),
'permission_to_send'=>array('label'=>'Email Permissions','name'=>'permission_to_send',
'type'=>'choice','options'=>array('implicit'=>'I have implied permission to send email','explicit'=>'I have express permission to send email','unsubscribe'=>'Unsubscribed','suspend'=>"Temporary Hold - Don't email")),

'phone_number'=>array('label'=>'Phone','name'=>'phone_number','type'=>'tel','group'=>'phone_numbers'),
'phone_type'=>array('label'=>'Phone Type','name'=>'phone_type','type'=>'Choice','group'=>'phone_numbers','options'=>array('home'=>'Home','work'=>'Work','fax'=>'Fax','mobile'=>'Mobile','other'=>'Other')),

'job_title'=>array('label'=>'Job Title','name'=>'job_title','type'=>'text'),
'company_name'=>array('label'=>'Company Name','name'=>'company_name','type'=>'text'),


'street'=>array('label'=>'Address - Street','name'=>'street','type'=>'text','group'=>'street_addresses'),
'city'=>array('label'=>'Address - City','name'=>'city','type'=>'text','group'=>'street_addresses'),
'state'=>array('label'=>'Address - State','name'=>'state','type'=>'text','group'=>'street_addresses'),
'postal_code'=>array('label'=>'Address - Postal Code','name'=>'postal_code','type'=>'text','group'=>'street_addresses'),
'country'=>array('label'=>'Address - Country','name'=>'country','type'=>'text','group'=>'street_addresses'),
'kind'=>array('label'=>'Address - Type','name'=>'kind','type'=>'Choice',
'group'=>'street_addresses','options'=>array('home'=>'Home','work'=>'Work','vacation'=>'Vacation','other'=>'Other')),
'list_id'=>array('label'=>'List ID','name'=>'list_id','type'=>'text')

);
$custom=$this->post_crm_arr('contact_custom_fields','get');
if(!empty($custom['custom_fields'])){
    foreach($custom['custom_fields'] as $v){
$res[$v['custom_field_id']]=array('name'=>$v['custom_field_id'],'label'=>$v['label'],'is_custom'=>'true','type'=>$v['type']);
    }
}
return $res;
}

public function get_lists(){ 
$arr=$this->post_crm_arr('contact_lists','get');

$users=array();   
if(!empty($arr['error_message'])){
 $users=$arr['error_message'];   
}else if(!empty($arr['lists'])){
 foreach($arr['lists'] as $k=>$v){
     if(empty($v['list_id'])){ continue; }
     $v['list_id']=(string)$v['list_id'];
  $users[$v['list_id']]=$v['name'];   
 }
}
  return $users;
}

public function push_object($module,$fields,$meta){  
//check primary key
 $extra=array();

  $debug = isset($_GET['vx_debug']) && current_user_can('manage_options');
  $event= isset($meta['event']) ? $meta['event'] : '';
  $id= isset($meta['crm_id']) ? $meta['crm_id'] : '';
  $lists=array();
  if($debug){ ob_start();}
if(isset($meta['primary_key']) && $meta['primary_key']!="" && isset($fields[$meta['primary_key']]['value']) && $fields[$meta['primary_key']]['value']!=""){    
$search=$fields[$meta['primary_key']]['value'];
$q=array('email'=>$search,'include'=>'list_memberships,custom_fields,phone_numbers,street_addresses');
//$q=array('email'=>$search,'include'=>'list_memberships','include_count'=>true,'lists'=>'ce562406-75c2-11ea-ae26-d4ae529a824a');
$search_response=$this->post_crm_arr('contacts','get',$q);
//$search_response=$this->post_crm_arr('contacts','get', array('email'=>'norahgault@hotmail.com','status'=>'explicit', 'include'=>'list_memberships','include_count'=>true) ); //'email'=>'',
//$search_response=$this->post_crm_arr('contacts/3040c318-75cf-11ea-aabc-d4ae52733d3a?include=list_memberships','get');
// var_dump($search_response); die();
if(!empty($search_response['contacts']) && !empty($search_response['contacts'][0]['contact_id']) ){
  $id=$search_response['contacts'][0]['contact_id']; 
  $search_response=array_slice($search_response['contacts'],0,5);
  if(!empty($search_response[0]['list_memberships'])){
    $lists=$search_response[0]['list_memberships'];  
  }
}
//$res=$this->post_crm_arr('contacts/310038b0-2210-11e9-8e55-d4ae52a2c97b','get');
  if($debug){
  ?>
  <h3>Search field</h3>
  <p><?php print_r($field) ?></p>
  <h3>Search term</h3>
  <p><?php print_r($search) ?></p>
    <h3>POST Body</h3>
  <p><?php print_r($body) ?></p>
  <h3>Search response</h3>
  <p><?php print_r($res) ?></p>  
  <?php
  }

      $extra["body"]=$search;
      $extra["response"]=$search_response;
  }


     if(in_array($event,array('delete_note','add_note'))){    
  if(isset($meta['related_object'])){
    $extra['Note Object']= $meta['related_object'];
  }
  if(isset($meta['note_object_link'])){
    $extra['note_object_link']=$meta['note_object_link'];
  }
}

 $status=$action=$method=""; $send_body=true;
 $link=""; $error=""; $arr=array();
 $entry_exists=false;
//var_dump($fields,$meta); die();
$object_url='';
$is_main=false;
$post=array();
if($id == ""){
    //insert new object
$action="Added";  
  if(empty($meta['new_entry'])){ 
$status="1"; $method='post';
$object_url='contacts';
$is_main=true;
$post['create_source']='Contact';
  }else{
      $error='Entry does not exist';
  }
  
}else if(in_array($event,array('delete'))){
     
  $action="Deleted";
  $sales_response=$this->post_crm_arr('contacts/'.$id,"DELETE");
    if(empty($sales_response)){ $status="5"; } 
}else{
 $entry_exists=true;
$action="Updated"; $status="2";
if(empty($meta['update'])){     
 $is_main=true;
$object_url='contacts/'.$id;
 $method='put';
 $post['update_source']='Contact';
 } 
}

if($is_main){

$crm_fields=array();
if(!empty($meta['fields'])){
  $crm_fields=$meta['fields'];  
}

if(is_array($fields) && count($fields)>0){
    $custom_fields=$phone=$address=array();
    foreach($fields as $k=>$v){
        $val=$v['value'];
  if(!empty($crm_fields[$k]['type'])){ 
  if($k == 'email_address'){
 $post['email_address']['address']=$val;
 
  }else if($k == 'permission_to_send'){
 $post['email_address']['permission_to_send']=$val;

  }else if($k == 'list_id'){
      $meta['object']=$val;
      if(is_array($val)){
          if(!is_array($meta['lists'])){
              $meta['lists']=array();
          }
        $val=array_values($val);  
      $meta['object']=$val[0];
      unset($val[0]);
      if(!empty($val)){
      $lists=array_merge($lists,$val);
      }
      }
 
  }else if(!empty($crm_fields[$k]['is_custom'])){   
  if($crm_fields[$k]['type'] == 'date'){ $val=date('m/d/Y',strtotime($val)); }  
  $custom_fields[]=array('custom_field_id'=>$k,'value'=>$val);
  
  }else if(!empty($crm_fields[$k]['group'])){
  $group=$crm_fields[$k]['group'];
  
  if(in_array($k,array('address_type','phone_type'))){
   $k='kind';   
  }
  if($group == 'phone_numbers'){
      $phone[$k]=$val;
  }else{
   $address[$k]=$val;   
  }    
  }else{
   $post[$k]=$val;   
  }     
}
}
if(!empty($phone)){
$post['phone_numbers']=array($phone);
}
if(!empty($address)){
$post['street_addresses']=array($address);
}
if(!empty($custom_fields)){
$post['custom_fields']=$custom_fields;
}
$new_lists=array();
$lists[]=$new_lists[]=$meta['object'];
if(!empty($meta['assign_list']) && !empty($meta['lists'])){
foreach($meta['lists'] as $k=>$v){
 $new_lists[]=$lists[]=$k;  
} }
if(!empty($meta['vx_unsub'])){
$lists=array_diff($lists,$new_lists);
//$post['list_memberships']=array();
}
$post['list_memberships']=array_unique($lists);
//var_dump($post); die();
// if(!empty($meta['status'])){  $post['status']=$meta['status'];  } 
// if(!empty($meta['source'])){  $post['source']=$meta['source'];  } 
// if(!empty($meta['source_detail'])){  $post['source_details']=$meta['source_detail'];  } 

} } 
//$post['email_address']['permission_to_send']='implicit'; 
//var_dump($post); die();
if( !empty($object_url) ){
$arr=$this->post_crm_arr($object_url,$method,$post);
}
//var_dump($object_url,$arr,$event,$post); die();
if(!empty($arr['contact_id'])){
$id=$arr['contact_id'];        

}else if(!empty($arr[0]['error_message'])){
$status=''; $error=$arr[0]['error_message']; $id='';
}
//var_dump($error); die();
  if($debug){
  ?>
  <h3>Account Information</h3>
  <p><?php //print_r($this->info) ?></p>
  <h3>Data Sent</h3>
  <p><?php print_r($post) ?></p>
  <h3>Fields</h3>
  <p><?php echo json_encode($fields) ?></p>
  <h3>Response</h3>
  <p><?php print_r($response) ?></p>
  <h3>Object</h3>
  <p><?php print_r($module."--------".$action) ?></p>
  <?php
 echo  $contents=trim(ob_get_clean());
  if($contents!=""){
  update_option($this->id."_debug",$contents);   
  }
  }
       //add entry note


return array("error"=>$error,"id"=>$id,"link"=>$link,"action"=>$action,"status"=>$status,"data"=>$fields,"response"=>$arr,"extra"=>$extra);
}
public function create_fields_section($fields){ 
$arr=array(); 

    // filter fields
    $crm_fields=$this->get_crm_fields(); 
    if(!is_array($crm_fields)){
        $crm_fields=array();
    } 
    $add_fields=array();
    if(is_array($fields['fields']) && count($fields['fields'])>0){
        foreach($fields['fields'] as $k=>$v){
           $found=false; 
                foreach($crm_fields as $crm_key=>$val){
                    if(strpos($crm_key,$k)!== false){
                        $found=true; break;
                }
            }
         //   echo $found.'---------'.$k.'============'.$crm_key.'<hr>';
         if(!$found){
       $add_fields[$k]=$v;      
         }   
        }
    }
 $arr['fields']=$add_fields;   


return $arr;  
} 
public function field_types($data){
    return array('string'=>'Text',"date"=>'Date');
}
public function create_field($field){
 
//$name=isset($field['name']) ? $field['name'] : '';
$label=isset($field['label']) ? $field['label'] : '';
$type=isset($field['type']) ? $field['type'] : '';
//$object=isset($field['object']) ? $field['object'] : '';

$error='Unknow error';
if(!empty($label) && !empty($type) ){
$body=array('label'=>$label,'type'=>$type);    
$url='contact_custom_fields';    
$arr=$this->post_crm_arr($url,'post',$body); 
    $error='ok';
if(empty($arr['custom_field_id']) ){
 $error=!empty($arr[0]['error_message']) ? $arr[0]['error_message'] : json_encode($arr);       
}
}
return $error;    
}

public  function post_crm_arr($path,$method,$body=""){
  $info=$this->info;    
  $get_token=false; 
      $path=$this->url.$path;
  $sales_response=$this->post_crm($path,$method,$body); 

  if(!empty($info['refresh_token']) && !empty($sales_response['error_key']) && $sales_response['error_key'] == 'unauthorized'){ 
  $get_token=true;         
  }

  if($get_token){ 
  $this->refresh_token();     
  if(!empty($this->info['access_token'])){
  $sales_response=$this->post_crm($path,$method,$body);
  } } 
  return $sales_response;   
}
public function post_crm($path,$method,$body='',$type=''){
        $head=array(); 
      $token=isset($this->info['access_token']) ? $this->info['access_token'] : '';
       $head['Authorization']='Bearer '.$token;
if($method == 'token'){
$method='post';
$head['Authorization']='Basic '.base64_encode($body['client_id'].':'.$body['client_secret']);
unset($body['client_id']); unset($body['client_secret']);
$body=http_build_query($body);
}else{
$head['Content-Type']='application/json';     
} 

if(is_array($body)&& count($body)>0 &&  $method !='get'){ 
$body=json_encode($body);
$head['Accept']='application/json'; 
} 

$args = array(
  'body' => $body,
  'headers'=> $head,
  'method' => strtoupper($method), // GET, POST, PUT, DELETE, etc.
  //'sslverify' => false,
  'timeout' => 30,
  );
  
  $response = wp_remote_request($path, $args);

  if(is_wp_error($response)) { 
  $body =array('error_message'=>$response->get_error_message());
  return $body;
  }
$body=json_decode($response['body'],true);
return $body;
}
public function get_entry($module,$id){

$search_response=$this->post_crm_arr('contacts/'.$id.'?include=custom_fields,phone_numbers,street_addresses','get');
$arr=array();
if(!empty($search_response)){
    foreach($search_response as $k=>$v){
if($k == 'email_address'){
    $arr['email_address']=$v['email_address']['address'];
}else if(is_array($v) && isset($v[0])){
    foreach($v[0] as $kk=>$vv){
        if(is_array($vv) && isset($vv['custom_field_id'])){
         $kk=$vv['custom_field_id']; $vv=$vv['value'];   
        }
        $arr[$kk]=$vv;
    }
}else{
 $arr[$k]=$v;    
} }
}
return $arr;     
}
   
}
}
?>