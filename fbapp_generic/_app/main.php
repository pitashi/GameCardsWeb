<?php
require_once("../configuration.php");
require_once("../functions.php");
require_once("portal.php");
require_once("../facebooksdk/facebook.php");

//INIT FACEBOOK USER AUTH THING A MA BOB
$facebook = new Facebook(array(
  'appId'  => $fbconfig['appid'],
  'secret' => $fbconfig['secret'],
  'cookie' => true,
));
$fbuserID = $facebook->getUser();

if($_GET['signup']){
  $userProfile = $_SESSION['userProfile'];
  $sEmail = $_GET["email_address"];
  $sPassword = $_GET["password"];
  $sAge = $_GET["age"];
  $sGender = $_GET["gender"];
  $sName = $_GET["name"];
  $sSurname = $_GET["surname"];
  
  
  $sql = "SELECT user_id FROM mytcg_user WHERE email_address='".$sEmail."'";
  $getUser = myqu($sql);
  if(sizeof($getUser) > 0){
  	// user logging in
  	  $userProfile = $_SESSION['userProfile'];
	  $sUsername = $_GET["username"];
	  $sPassword = $_GET["password"];
	  $sql = "SELECT user_id,password FROM mytcg_user WHERE username='".$sUsername."'";
	  $getUser = myqu($sql);
	  if(!$getUser){
	    echo('Username not found<br />Are you sure you have registered?');
	    exit;
	  }
	  $user_id = $getUser[0]['user_id'];
	  $iMod=(intval($user_id) % 10)+1;
	  $sPassword=substr(md5($user_id),$iMod,10).md5($sPassword);
	  
	  if($sPassword != $getUser[0]['password']){
	    echo("Invalid password.");
	    exit;
	  }
	  
	  $sql = "UPDATE mytcg_user SET facebook_user_id = '".$userProfile['id']."',facebook_process = 1 WHERE user_id = ".$user_id;
	  $sUA=$_SERVER["HTTP_USER_AGENT"];
	  $sUA=myqu("UPDATE mytcg_user SET last_useragent='".$sUA."' WHERE user_id='".$user_id."'");
	  $response = myqu($sql);
	  myqu("INSERT INTO tcg_user_log (user_id, name, surname, email_address, email_verified, date_register, date_last_visit, msisdn, imsi, imei, version, os, make, model, osver, touch, width, height, facebook_user_id, mobile_date_last_visit, web_date_last_visit, facebook_date_last_visit, last_useragent, ip, apps_id, age, gender, referer_id)
		SELECT user_id, name, surname, email_address, email_verified, date_register, date_last_visit, msisdn, imsi, imei, version, os, make, model, osver, touch, width, height, facebook_user_id, mobile_date_last_visit, web_date_last_visit, facebook_date_last_visit, last_useragent, ip, apps_id, age, gender, referer_id
		FROM mytcg_user WHERE user_id=".$user_id);
	  echo("1");
	  exit;
  }
  
  $sql = "INSERT INTO mytcg_user (name,surname,date_register,username,email_address,age,gender,facebook_user_id,credits,premium) VALUES ('".$sName."','".$sSurname."',NOW(),'".$sEmail."','".$sEmail."',".$sAge.",".$sGender.",'".$userProfile['id']."',0,0)";
  $res = myqu($sql);
  
  $sql = "SELECT user_id FROM mytcg_user WHERE email_address='".$sEmail."'";
  $getUser = myqu($sql);
  
  $user_id = $getUser[0]['user_id'];
  
  myqu("INSERT INTO tcg_user_log (user_id, name, surname, email_address, email_verified, date_register, date_last_visit, msisdn, imsi, imei, version, os, make, model, osver, touch, width, height, facebook_user_id, mobile_date_last_visit, web_date_last_visit, facebook_date_last_visit, last_useragent, ip, apps_id, age, gender, referer_id)
	SELECT user_id, name, surname, email_address, email_verified, date_register, date_last_visit, msisdn, imsi, imei, version, os, make, model, osver, touch, width, height, facebook_user_id, mobile_date_last_visit, web_date_last_visit, facebook_date_last_visit, last_useragent, ip, apps_id, age, gender, referer_id
	FROM mytcg_user WHERE user_id=".$user_id);
  
  $iMod=(intval($user_id) % 10)+1;
  $sPassword=substr(md5($user_id),$iMod,10).md5($sPassword);
  
  $sql = "UPDATE mytcg_user SET password = '".$sPassword."' WHERE user_id = ".$user_id;
  $res = myqu($sql);
  
  $sUA=$_SERVER["HTTP_USER_AGENT"];
  $sUA=myqu("UPDATE mytcg_user SET last_useragent='".$sUA."' WHERE user_id='".$user_id."'");
  
  $sql = "SELECT * FROM mytcg_user_detail";
  $getUser = myqu($sql);
  foreach($getUser as $u){
  	$sql = "INSERT INTO mytcg_user_answer (detail_id,answered,user_id) VALUES (".$u['detail_id'].",0,".$user_id.")";
  	$res = myqu($sql);
  }
  
  $sql = "INSERT INTO mytcg_frienddetail (user_id, friend_id) values (".$user_id.",".$user_id.")";
	myqu($sql);
	$sql = "SELECT user_fb_id FROM mytcg_userrequest where request_user_fb_id = '".$userProfile['id']."'";
  $res = myqu($sql);
  if (sizeof($res) > 0) {
	foreach($res as $friend){
		$sql = "INSERT INTO mytcg_frienddetail (user_id, friend_id) values ((SELECT user_id from mytcg_user where facebook_user_id = '".$friend['user_fb_id']."'),".$user_id.")";
		myqu($sql);
		$sql = "INSERT INTO mytcg_frienddetail (user_id, friend_id) values (".$user_id.",(SELECT user_id from mytcg_user where facebook_user_id = '".$friend['user_fb_id']."'))";
		myqu($sql);
	}
  }
  echo("1");
  exit;
}

if($_GET['init']){
	$userProfile = $_SESSION['userProfile']['id'];

	//FREE CREDITS ON DAILY LOGIN
	$aUser = myqu("SELECT user_id,credits,date_last_visit,mobile_date_last_visit FROM mytcg_user WHERE facebook_user_id = '".$userProfile."' LIMIT 1");
	$aUser = $aUser[0];
	$sLastDate = $aUser['date_last_visit'];
	$sMobileLastDate = $aUser['mobile_date_last_visit'];
      
	//update last visit
	$sDate=date("Y-m-d H:i:s");
	$aDateVisit=myqu("UPDATE mytcg_user SET date_last_visit='".$sDate."', facebook_date_last_visit='".$sDate."' WHERE user_id=".$aUser['user_id']);
    
	$today = date("Y-m-d");
	if((substr($sLastDate,0,10) != $today)&&(substr($sMobileLastDate,0,10) != $today))
	{
		//give user credits for daily login
		$amount = $aUser['credits'] + 20;
		myqu("UPDATE mytcg_user SET credits = (".$amount.") , gameswon=0 WHERE user_id=".$aUser['user_id']);
		myqu("INSERT INTO mytcg_transactionlog (user_id, description, date, val) VALUES (".$aUser['user_id'].", 'Received 20 credits for logging in today', NOW(), 20)");

		myqu("INSERT INTO tcg_transaction_log (fk_user, fk_boosterpack, fk_usercard, fk_card, transaction_date, description, tcg_credits, fk_payment_channel, application_channel, mytcg_reference_id, fk_transaction_type,tcg_freemium,tcg_premium)
			VALUES(".$aUser['user_id'].", NULL, NULL, NULL, 
				now(), 'Received 20 credits for logging in today', 20, NULL, 'facebook',  (SELECT max(transaction_id) FROM mytcg_transactionlog WHERE user_id = ".$aUser['user_id']."), 16,20,0)");
		$popup = true;
	}
	
	$sql = "SELECT request_user_fb_id AS fbid FROM mytcg_userrequest WHERE user_fb_id = '".$fbuserID."'";
	$aList = myqu($sql);
	echo '<init>'.$sCRLF;
	if($popup){
		echo($sTab."<credits>1</credits>".$sCRLF);
	}
	echo $sTab.'<requests>'.$sCRLF;
	echo $sTab.$sTab.'<iCount>'.sizeof($aList).'</iCount>'.$sCRLF;
	$i = 0;
	foreach($aList as $item)
	{
		echo $sTab.$sTab.'<request_'.$i.'>'.$sCRLF;
		echo $sTab.$sTab.$sTab.'<fbid>'.$item['fbid'].'</fbid>'.$sCRLF;
		echo $sTab.$sTab.'</request_'.$i.'>'.$sCRLF;
		$i++;
	}
	echo $sTab.'</requests>'.$sCRLF;
	
	//Category selection
	$sql = "SELECT category_id,description,parent_id,level 
		   FROM mytcg_category 
		   ORDER BY description ";
	  
	echo $sTab.'<categories>'.$sCRLF;
	$aCategory=myqu($sql);
	$iCatCount=sizeof($aCategory);
	echo $sTab.$sTab.'<iCount>'.$iCatCount.'</iCount>'.$sCRLF;
	  for ($iCount=0;$iCount<$iCatCount;$iCount++){
	    echo $sTab.$sTab.'<category_'.$iCount.'>'.$sCRLF;
	    echo $sTab.$sTab.$sTab.'<category_id>'.$aCategory[$iCount]["category_id"].'</category_id>'.$sCRLF;
	    $parent_id = ($aCategory[$iCount]["parent_id"] > 0) ? $aCategory[$iCount]["parent_id"] : "main";
	    echo $sTab.$sTab.$sTab.'<parent_id>'.$parent_id.'</parent_id>'.$sCRLF;
	    echo $sTab.$sTab.$sTab.'<description>'.$aCategory[$iCount]["description"].'</description>'.$sCRLF;
	    echo $sTab.$sTab.$sTab.'<level>'.$aCategory[$iCount]["level"].'</level>'.$sCRLF;
	    echo $sTab.$sTab.'</category_'.$iCount.'>'.$sCRLF;
	  }
	  echo $sTab.'</categories>'.$sCRLF;
	echo '</init>'.$sCRLF;
  exit;
}

if($_REQUEST['request_ids']){
	$sql = "SELECT * FROM mytcg_userrequest WHERE user_fb_id = '".$fbuserID."' AND request_user_fb_id = '".$_REQUEST['request_ids']."'";
	$res = myqu($sql);
	
	if(sizeof($res) == 0){
		$sql = "INSERT INTO mytcg_userrequest (user_fb_id,request_user_fb_id,request_status) VALUES ('".$fbuserID."','".$_REQUEST['request_ids']."',1)";
		$res = myqu($sql);
	}
	$sql = "SELECT user_id FROM mytcg_user WHERE facebook_user_id = '".$_REQUEST['request_ids']."'";
	$res = myqu($sql);
	if (sizeof($res) > 0) {
		$sql = "INSERT INTO mytcg_frienddetail (user_id, friend_id) values ((SELECT user_id from mytcg_user where facebook_user_id = '".$fbuserID."'),".$res[0]['user_id'].")";
		myqu($sql);
		$sql = "INSERT INTO mytcg_frienddetail (user_id, friend_id) values (".$res[0]['user_id'].",(SELECT user_id from mytcg_user where facebook_user_id = '".$fbuserID."'))";
		myqu($sql);
	}
}


?>