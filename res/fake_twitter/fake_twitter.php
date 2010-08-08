<!DOCTYPE HTML PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xml:lang="en" xmlns="http://www.w3.org/1999/xhtml" lang="en">
<head>
    <title id="page_title">Twitter / Applications: Register</title>
	<link href="http://a2.twimg.com/a/1276197224/stylesheets/twitter.css?1276316974" media="screen" rel="stylesheet" type="text/css" />
  <link href="http://a0.twimg.com/a/1276197224/stylesheets/settings.css?1276316974" media="screen, projection" rel="stylesheet" type="text/css" />
	<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.4.2/jquery.js"></script>
	<style>
	.yourls {border:1px solid red;padding:3px;outline:none;border-color:rgba(255,50,50,.75);box-shadow:0 0 8px rgba(200,50,50,.5);-moz-box-shadow:0 0 8px rgba(200,50,50,.5);-webkit-box-shadow:0 0 8px rgba(200,50,50,.5);}
	</style>
	<script>
	$.urlParam = function(name) {
		var results = new RegExp('[\\?&]' + name + '=([^&#]*)').exec(window.location.href);
		if (!results) { return 0; }
		return results[1] || 0;
	};
	
	$(document).ready(function(){
		$('#client_application_name').val( unescape($.urlParam('name')) );
		$('#client_application_url').val( unescape($.urlParam('url')) );
		$('#client_application_description').val( unescape($.urlParam('desc')) );
		$('#client_application_organization').val( unescape($.urlParam('org')) );
		$('#client_application_organization_url').val( unescape($.urlParam('url')) );
		$('#client_application_callback_url').val( unescape($.urlParam('url')) );
	});
	</script>
</head>

<body class="oauth_clients firefox-windows">
<div id="container" class="subpage">
<div class="wrapper" id="content" >
                
                  
<h1>Register an Application<br/><span style="color:green;font-size:90%;text-shadow:0 0 1px #000, 0 0 1px #030, 1px 1px 2px #3a3;">YOURLS Help, not an actual form!</span></h1>

<div class="section">
    <fieldset class="common-form settings-editor">
    <table class="input-form" cellspacing="0">
      <tbody><tr  id="app_image">
        <th class="middle"><label 
for="client_application_application_image">Application Icon:</label></th>
        <td>
          <img alt="Oauth_application" 
src="http://s.twimg.com/a/1276197224/images/oauth_application.png" alt="oauth_application.png"/><br>
          <input id="client_application_uploaded_data" 
name="client_application[uploaded_data]" size="30" type="file" value="omg">         
 <small><p>Maximum size of 700k.  JPG, GIF, PNG.</p></small>
         </td>
      </tr>
      <tr id="app_name">
        <th><label for="client_application_name">Application Name:</label></th>
        <td>
          <input value="" id="client_application_name" 
name="client_application[name]" size="30" type="text">        </td>
      </tr>
      <tr id="app_desc">
        <th><label for="client_application_description">Description:</label></th>
        <td>
          <textarea cols="40" id="client_application_description" 
name="client_application[description]" rows="10"></textarea>        </td>
      </tr>
      <tr id="app_website">
        <th><label for="client_application_url">Application Website:</label></th>
        <td>
          <input id="client_application_url" 
name="client_application[url]" size="30" type="text">          <small><p>Where's
 your application's home page, where users can go to download or use it?</p></small>
        </td>
      </tr id="org_desc">
      <tr>
        <th><label for="client_application_organization">Organization:</label></th>
        <td>
          <input id="client_application_organization" 
name="client_application[organization]" size="30" type="text">        </td>
      </tr>

      <tr id="org_website">
        <th><label for="client_application_organization_url">Website:</label></th>
        <td>
          <input id="client_application_organization_url" 
name="client_application[organization_url]" size="30" type="text">      
    <small><p>The home page of your company or organization.</p></small>
        </td>
      </tr>
      <tr id="type_row">
        <th><label for="client_application_desktop">Application Type:</label></th>
        <td>
          <div class="yourls"><input id="client_application_desktop_1" 
name="client_application[desktop]" style="vertical-align: middle;" 
value="1" type="radio"> Client          <input checked="checked" 
id="client_application_desktop_0" name="client_application[desktop]" 
style="vertical-align: middle; margin-left: 10px;" value="0" 
type="radio"> Browser</div> 
          <small>
            <p>Does your application run in a Web Browser or a Desktop 
Client?</p>
            <ul class="auth-type-details bulleted"><li>Browser uses a 
Callback URL to return to your App after successfully authentication.</li>
            <li>Client prompts your user to return to your application 
after approving access.</li></ul>
          </small>
        </td>
      </tr>

            <tr id="callback_row">
        <th><label for="client_application_callback_url">Callback URL:</label></th>
        <td>
          <input id="client_application_callback_url" 
name="client_application[callback_url]" class="yourls" size="30" type="text">          <p><small>Where
 should we return to after successfully authentication?</small></p>
        </td>
      </tr>
      <tr>
        <th><label for="client_application_is_writable">Default Access 
type:</label></th>
        <td>
         <div class="yourls"> <input checked="checked" id="client_application_is_writable_1" 
name="client_application[is_writable]" style="vertical-align: middle;" 
value="1" type="radio"> Read &amp; Write
          <input id="client_application_is_writable_0"
 name="client_application[is_writable]" style="vertical-align: middle; 
margin-left: 10px;" value="0" type="radio"> Read-only</div>
          <small><p>What type of access does your application need?<br>Note:
 @Anywhere applications require read &amp; write access.</p></small>
        </td>
      </tr>
      <tr>
        <th><label for="client_application_supports_login">Use Twitter 
for login:</label></th>
        <td>
          <input id="client_application_supports_login" 
name="client_application[supports_login]" style="vertical-align: 
middle;" value="1" type="checkbox"><input 
name="client_application[supports_login]" value="0" type="hidden"> Yes, 
use Twitter for login
          <br>
          <small><p>Does your application intend to use Twitter for 
authentication?</p></small>
        </td>
      </tr>

      
   </tbody></table>
  </fieldset>

  <br><br>
</div>


                </div>
                              
      

     
    
   

  </body></html>