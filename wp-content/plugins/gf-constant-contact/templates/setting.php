<?php
if ( ! defined( 'ABSPATH' ) ) {
     exit;
 }                                         
$name=$this->post('name',$info);   
 $self_dir=admin_url().'?'.$this->id.'_tab_action=get_token'; 
 ?>
  <div class="crm_fields_table">
    <div class="crm_field">
  <div class="crm_field_cell1"><label for="vx_name"><?php esc_html_e("Account Name",'gravity-forms-ccontact-crm'); ?></label>
  </div>
  <div class="crm_field_cell2">
  <input type="text" name="crm[name]" value="<?php echo !empty($name) ? esc_attr($name) : 'Account #'.esc_attr($id); ?>" id="vx_name" class="crm_text">

  </div>
  <div class="clear"></div>
  </div>
  
  <div class="crm_field">
  <div class="crm_field_cell1"><label for="app_id"><?php esc_html_e("API Key",'gravity-forms-ccontact-crm'); ?></label></div>
  <div class="crm_field_cell2">
     <div class="vx_tr">
  <div class="vx_td">
  <input type="password" id="app_id" name="crm[app_id]" class="crm_text" placeholder="<?php esc_html_e("API Key",'gravity-forms-ccontact-crm'); ?>" value="<?php echo esc_html($this->post('app_id',$info)); ?>">
  </div><div class="vx_td2">
  <a href="#" class="button vx_toggle_btn vx_toggle_key" title="<?php esc_html_e('Toggle Consumer Key','gravity-forms-ccontact-crm'); ?>"><?php esc_html_e('Show Key','gravity-forms-ccontact-crm') ?></a>
  
  </div></div> 
  <div class="howto">
  <ol>
  <li><?php echo sprintf(__('Create New Application %shere%s','gravity-forms-ccontact-crm'),'<a href="https://app.constantcontact.com/pages/dma/portal/" target="_blank">','</a>'); ?></li>
  <li><?php esc_html_e('Enter Application Name(eg. My App)','gravity-forms-ccontact-crm'); ?></li>
  <li><?php echo sprintf(__('Enter  %s in Redirect URI','gravity-forms-ccontact-crm'),'<code>'.$self_dir.'</code>'); ?>
  </li>
<li><?php esc_html_e('Save Application','gravity-forms-ccontact-crm'); ?></li>
<li><?php esc_html_e('Generate secret and copy it','gravity-forms-ccontact-crm'); ?></li>
   </ol>
   

  </div>
</div>
  <div class="clear"></div>
  </div>
<div class="crm_field">
  <div class="crm_field_cell1"><label for="app_secret"><?php esc_html_e("Secret",'gravity-forms-ccontact-crm'); ?></label></div>
  <div class="crm_field_cell2">
       <div class="vx_tr" >
  <div class="vx_td">
 <input type="password" id="app_secret" name="crm[app_secret]" class="crm_text"  placeholder="<?php esc_html_e("Secret",'gravity-forms-ccontact-crm'); ?>" value="<?php echo esc_html($this->post('app_secret',$info)); ?>">
  </div><div class="vx_td2">
  <a href="#" class="button vx_toggle_btn vx_toggle_key" title="<?php esc_html_e('Toggle Consumer Secret','gravity-forms-ccontact-crm'); ?>"><?php esc_html_e('Show Key','gravity-forms-ccontact-crm') ?></a>
  
  </div></div>
  </div>
  <div class="clear"></div>
  </div>
<div class="crm_field">
  <div class="crm_field_cell1"><label for="app_url"><?php esc_html_e("Redirect URI",'gravity-forms-ccontact-crm'); ?></label></div>
  <div class="crm_field_cell2"><input type="text" id="app_url" name="crm[app_url]" class="crm_text" placeholder="<?php esc_html_e("Redirect URI",'gravity-forms-ccontact-crm'); ?>" value="<?php echo esc_html($this->post('app_url',$info)); ?>"> 

  </div>
  <div class="clear"></div>
  </div>
 <?php
 if(!empty($client['client_id'])){                   
                ?>               
  <div class="crm_field">
  <div class="crm_field_cell1"><label><?php esc_html_e('Constant Contact Access','gravity-forms-ccontact-crm'); ?></label></div>
  <div class="crm_field_cell2">
  <?php if(isset($info['access_token'])  && $info['access_token']!="") {
  ?>
  <div style="padding-bottom: 8px;" class="vx_green"><i class="fa fa-check"></i> <?php
  echo sprintf(__("Authorized Connection to %s on %s",'gravity-forms-ccontact-crm'),'<code>Constant Contact</code>',date('F d, Y h:i:s A',$info['_time']));
        ?></div>
  <?php
  }else{
  ?>
  <a class="button button-default button-hero sf_login" data-id="<?php echo esc_html($client['client_id']) ?>" href="https://authz.constantcontact.com/oauth2/default/v1/authorize?scope=contact_data+offline_access&response_type=code&client_id=<?php echo $client['client_id'] ?>&redirect_uri=<?php echo urlencode($client['call_back']) ?>&state=<?php echo esc_attr($id); ?>"  title="<?php esc_html_e("Login with Constant Contact",'gravity-forms-ccontact-crm'); ?>" > <i class="fa fa-lock"></i> <?php esc_html_e("Login with Constant Contact",'gravity-forms-ccontact-crm'); ?></a>
  <?php
  }
  ?></div>
  <div class="clear"></div>
  </div>                  
    <?php if(isset($info['access_token'])  && $info['access_token']!="") {
  ?>
    <div class="crm_field">
  <div class="crm_field_cell1"><label><?php esc_html_e("Revoke Access",'gravity-forms-ccontact-crm'); ?></label></div>
  <div class="crm_field_cell2">  <a class="button button-secondary" id="vx_revoke" href="<?php echo esc_url($link."&".$this->id."_tab_action=get_token&vx_nonce=".$nonce.'&id='.$id)?>"><i class="fa fa-unlock"></i> <?php esc_html_e("Revoke Access",'gravity-forms-ccontact-crm'); ?></a>
  </div>
  <div class="clear"></div>
  </div> 
<div class="crm_field">
  <div class="crm_field_cell1"><label><?php esc_html_e("Test Connection",'gravity-forms-ccontact-crm'); ?></label></div>
  <div class="crm_field_cell2">      <button type="submit" class="button button-secondary" name="vx_test_connection"><i class="fa fa-refresh"></i> <?php esc_html_e("Test Connection",'gravity-forms-ccontact-crm'); ?></button>
  </div>
  <div class="clear"></div>
  </div>
<?php
    }
 }   
?>    
  <div class="crm_field">
  <div class="crm_field_cell1"><label for="vx_error_email"><?php esc_html_e("Notify by Email on Errors",'gravity-forms-ccontact-crm'); ?></label></div>
  <div class="crm_field_cell2"><textarea name="crm[error_email]" id="vx_error_email" placeholder="<?php esc_html_e("Enter comma separated email addresses",'gravity-forms-ccontact-crm'); ?>" class="crm_text" style="height: 70px"><?php echo isset($info['error_email']) ? esc_html($info['error_email']) : ""; ?></textarea>
  <span class="howto"><?php esc_html_e("Enter comma separated email addresses. An email will be sent to these email addresses if an order is not properly added to Constant Contact. Leave blank to disable.",'gravity-forms-ccontact-crm'); ?></span>
  </div>
  <div class="clear"></div>
  </div>  
   
  
  
   <div class="crm_field">
  <div class="crm_field_cell1"><label for="vx_cache">
  <?php esc_html_e("Remote Cache Time", 'gravity-forms-ccontact-crm'); ?>
  </label>
 </div>
 <div class="crm_field_cell2">
    <div style="display: table">
  <div style="display: table-cell; width: 85%;">
  <select id="vx_cache" name="crm[cache_time]" style="width: 100%">
  <?php
  $cache=array("60"=>"One Minute (for testing only)","3600"=>"One Hour","21600"=>"Six Hours","43200"=>"12 Hours","86400"=>"One Day","172800"=>"2 Days","259200"=>"3 Days","432000"=>"5 Days","604800"=>"7 Days","18144000"=>"1 Month");
  if($this->post('cache_time',$info) == ""){
   $info['cache_time']="86400";
  }
  foreach($cache as $secs=>$label){
   $sel="";
   if($this->post('cache_time',$info) == $secs){
       $sel='selected="selected"';
   }
  echo '<option value="'.esc_attr($secs).'" '.$sel.' >'.esc_html($label).'</option>';     
  }   
  ?>
  </select></div><div style="display: table-cell;">
  <button name="vx_tab_action" value="refresh_lists_<?php echo esc_attr($this->id) ?>" class="button" style="margin-left: 10px; vertical-align: baseline; width: 110px" autocomplete="off" title="<?php esc_html_e('Refresh Picklists','gravity-forms-ccontact-crm'); ?>">Refresh Now</button>
  </div></div>
  <span class="howto">
  <?php esc_html_e("How long should form and field data be stored? This affects how often remote picklists will be checked for the Live Remote Field Mapping feature. This is an advanced setting. You likely won't need to change this.",'gravity-forms-ccontact-crm'); ?>
  </span></div>
  </div>
  

<p class="submit">
  <button type="submit" value="save" class="button-primary" title="<?php esc_html_e('Save Changes','gravity-forms-ccontact-crm'); ?>" name="save"><?php esc_html_e('Save Changes','gravity-forms-ccontact-crm'); ?></button></p>  
  </div>  

 