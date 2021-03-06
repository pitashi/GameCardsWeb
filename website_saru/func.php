<?php
require_once("config.php");
define('ACHI_INC','1'); 
define('ACHI_TOT','2'); 

function getAchis($iUserID) {
	$aServers=myqu('SELECT b.imageserver_id, b.description as URL '
		.'FROM mytcg_imageserver b '
		.'ORDER BY b.description DESC '
	);

	$achiQu = ('SELECT progress, target, date_completed, complete_image, 
		name, description, incomplete_image, achievement_id, 
		a.imageserver_id as aserver_id, al.imageserver_id as alserver_id 
		FROM mytcg_userachievementlevel ual 
		LEFT OUTER JOIN mytcg_achievementlevel al 
		ON ual.achievementlevel_id = al.id 
		LEFT OUTER JOIN mytcg_achievement a 
		ON al.achievement_id = a.id 
		WHERE ual.user_id = '.$iUserID.' 
		ORDER BY name, achievement_id, target');
	
	$achiQuery = myqu($achiQu);
	
	$count = 0;
	$currentParent = '';
	
	$retXml = '<achis>';
	while ($aOneAchi=$achiQuery[$count]) {
		$achiId = $aOneAchi['achievement_id'];
		
		if ($achiId != $currentParent) {
			$currentParent = $achiId;
		
			if ($count > 0) {
				$retXml .= '</achi>';
			}
			$retXml .= '<achi>';
			
			$retXml .= '<name>'.$aOneAchi['name'].'</name>';
			$retXml .= '<description>'.$aOneAchi['description'].'</description>';
			
			$sFound='';
			$iCountServer=0;
			while ((!$sFound)&&($aOneServer=$aServers[$iCountServer])){
				if ($aOneServer['imageserver_id']==$aOneAchi['aserver_id']){
					$sFound=$aOneServer['URL'];
				} else {
					$iCountServer++;
				}
			}

			$retXml .= '<incomplete_image>'.$sFound.'achi/'.$aOneAchi['incomplete_image'].'</incomplete_image>';
		}
		
		$retXml .= '<subachi>';
		
		$retXml .= '<progress>'.$aOneAchi['progress'].'</progress>';
		$retXml .= '<target>'.$aOneAchi['target'].'</target>';
		$retXml .= '<date_completed>'.$aOneAchi['date_completed'].'</date_completed>';
		
		$sFound='';
		$iCountServer=0;
		while ((!$sFound)&&($aOneServer=$aServers[$iCountServer])){
			if ($aOneServer['imageserver_id']==$aOneAchi['alserver_id']){
				$sFound=$aOneServer['URL'];
			} else {
				$iCountServer++;
			}
		}

		$retXml .= '<complete_image>'.$sFound.'achi/'.$aOneAchi['complete_image'].'</complete_image>';
		
		$retXml .= '</subachi>';
		
		$count++;
	}
	if ($count > 0) {
		$retXml .= '</achi>';
	}
	$retXml .= '</achis>';
	
	return $retXml;
}

function checkAchis($iUserID, $iAchiTypeId) {
	$achiQu = ('SELECT ual.id, ual.progress, al.target, a.calc_id, a.reset, a.query, a.name 
		FROM mytcg_userachievementlevel ual
		INNER JOIN mytcg_achievementlevel al
		ON al.id = ual.achievementlevel_id
		INNER JOIN mytcg_achievement a
		ON a.id = al.achievement_id
		WHERE ual.date_completed IS NULL
		AND ual.user_id = '.$iUserID.' 
		AND a.type_id = '.$iAchiTypeId);
	
	$achiQuery = myqu($achiQu);
	
	$count = 0;
	while ($aOneAchi=$achiQuery[$count]) {
		$count++;
		
		$userAchiId = $aOneAchi['id'];
		$reset = $aOneAchi['reset'];
		$target = $aOneAchi['target'];
		$progress = $aOneAchi['progress'];
		$query = $aOneAchi['query'];
		$name = $aOneAchi['name'];
		$query = str_replace("useridreplac", $iUserID, $query);
		
		$valQuery = myqu($query);
		$val = $valQuery[0]['val'];
		if ($aOneAchi['calc_id'] == ACHI_INC) {
			if ($val >= 0) {
				$updateQuery = "UPDATE mytcg_userachievementlevel SET date_updated = now(), progress = progress + ".$val." WHERE id = ".$userAchiId;
				myqu($updateQuery);
				
				$progress = $progress + $val;
			}
			else if ($reset == 1) {
				$updateQuery = "UPDATE mytcg_userachievementlevel SET date_updated = now(), progress = 0 WHERE id = ".$userAchiId;
				myqu($updateQuery);
				
				$progress = 0;
			}
		}
		else if ($aOneAchi['calc_id'] == ACHI_TOT) {
			$updateQuery = "UPDATE mytcg_userachievementlevel SET date_updated = now(), progress = ".$val." WHERE id = ".$userAchiId;
			myqu($updateQuery);
			
			$progress = $val;
		}
		
		if ($progress >= $target) {
			$updateQuery = "UPDATE mytcg_userachievementlevel SET date_completed = now() WHERE id = ".$userAchiId;
			myqu($updateQuery);
			
			myqu('INSERT INTO mytcg_notifications (user_id, notification, notedate, notificationtype_id)
					VALUES ('.$iUserID.', "Achievement earned! ('.$name.') Well Done!", now(), 1)');
		}
	}
}

function validip($ip){
  if (!empty($ip) && ip2long($ip)!=-1){
    $reserved_ips = array(
      array('0.0.0.0','2.255.255.255'), 
      array('10.0.0.0','10.255.255.255'),
      array('127.0.0.0','127.255.255.255'),
      array('169.254.0.0','169.254.255.255'),
      array('172.16.0.0','172.31.255.255'),
      array('192.0.2.0','192.0.2.255'),
      array('192.168.0.0','192.168.255.255'),
      array('255.255.255.0','255.255.255.255')
    );
   
    foreach ($reserved_ips as $r) {
      $min = ip2long($r[0]);
      $max = ip2long($r[1]);
      if ((ip2long($ip) >= $min) && (ip2long($ip) <= $max)) return false;
    }
    return true;
  } else {
    return false;
  }
}
 
function getip(){
  if (validip($_SERVER["HTTP_CLIENT_IP"])) {
    return $_SERVER["HTTP_CLIENT_IP"];
  }

  foreach (explode(",",$_SERVER["HTTP_X_FORWARDED_FOR"]) as $ip) {
    if (validip(trim($ip))) {
      return $ip;
    }
  }
 
  if (validip($_SERVER["HTTP_X_FORWARDED"])) {
    return $_SERVER["HTTP_X_FORWARDED"];
  } elseif (validip($_SERVER["HTTP_FORWARDED_FOR"])) {
    return $_SERVER["HTTP_FORWARDED_FOR"];
  } elseif (validip($_SERVER["HTTP_FORWARDED"])) {
    return $_SERVER["HTTP_FORWARDED"];
  } elseif (validip($_SERVER["HTTP_X_FORWARDED"])) {
    return $_SERVER["HTTP_X_FORWARDED"];
  } else {
    return $_SERVER["REMOTE_ADDR"];
  }
}


//clears any actions that when limit is up
function updateAuctions() {
  //Select details of the auction
  $query = ('SELECT a.market_id, a.marketstatus_id, a.user_id owner, a.usercard_id, x.username ownername,
            IFNULL(b.price,0) price, IFNULL(b.user_id,-1) bidder, u.username, date_expired, d.description
            FROM mytcg_market a 
            LEFT OUTER JOIN mytcg_marketcard b 
            ON a.market_id = b.market_id 
            LEFT OUTER JOIN mytcg_user u
            ON b.user_id = u.user_id
            INNER JOIN mytcg_user x
            ON a.user_id = x.user_id
            INNER JOIN mytcg_usercard c
            ON a.usercard_id = c.usercard_id
            INNER JOIN mytcg_card d
            ON c.card_id = d.card_id
            WHERE datediff(now(), date_expired) >= 1 
            AND marketstatus_id = 1
            AND (b.price = (select max(price) 
                          from mytcg_marketcard c
                          where c.market_id = a.market_id 
                          group by market_id)
            OR ISNULL(b.price))');
  
  $auctions = myqu($query);
  
  $count = 0;
  foreach ($auctions as $auction) {
    //set the auction to expired
    $query = "update mytcg_market set marketstatus_id = '2' where market_id = ".$auction['market_id'];
    myqu($query);
    
    //add the credits to the user who was auctioning the card
    $query = "update mytcg_user set credits = credits + ".$auction['price']." where user_id = ".$auction['owner'];
    myqu($query);
    
    //set the cards status back to Album
    if ($auction['bidder'] == -1) {
      $query = "update mytcg_usercard set usercardstatus_id = (select usercardstatus_id from mytcg_usercardstatus where description = 'Album'), user_id = ".$auction['owner']." where usercard_id = ".$auction['usercard_id'];
      myqui('INSERT INTO mytcg_notifications (user_id, notification, notedate)
          VALUES ('.$auction['owner'].', "Auction ended on '.$auction['description'].' with no highest bidder.", now())');
    } else {
      $query = "update mytcg_usercard set usercardstatus_id = (select usercardstatus_id from mytcg_usercardstatus where description = 'Received'), user_id = ".$auction['bidder']." where usercard_id = ".$auction['usercard_id'];
      
      myqui('INSERT INTO mytcg_transactionlog (user_id, description, date, val)
        VALUES ('.$auction['owner'].', "Received '.$auction['price'].' credits for auctioning '.$auction['description'].' to '.$auction['username'].'.", now(), '.$auction['price'].')');
        
      myqui('INSERT INTO mytcg_notifications (user_id, notification, notedate)
        VALUES ('.$auction['owner'].', "Auctioned '.$auction['description'].' to '.$auction['username'].' for '.$auction['price'].' credits.", now())');
        
      myqui('INSERT INTO mytcg_transactionlog (user_id, description, date, val)
        VALUES ('.$auction['bidder'].', "Spent '.$auction['price'].' credits for winning the auction '.$auction['description'].' from '.$auction['ownername'].'.", now(), -'.$auction['price'].')');
        
      myqui('INSERT INTO mytcg_notifications (user_id, notification, notedate)
        VALUES ('.$auction['bidder'].', "Won auction '.$auction['description'].' from '.$auction['ownername'].' for '.$auction['price'].' credits.", now())');
    }
    
    myqu($query);
    
    $count++;
  }
}

function getUserData($prefix, $userId='')
{
	$userId = ($userId == '') ? $_SESSION['user']['id'] : $userId; 
	$sql = "SELECT user_id, username, password, date_last_visit, mobile_date_last_visit , (ifnull(credits,0)+ifnull(premium,0)) credits,credits freemium, premium, xp, freebie, completion_process_stage "
		."FROM mytcg_user "
		."WHERE user_id='".$userId."' "
		."AND is_active='1'";
	return myqu($sql);
}

function getCardOwnedCount($cardID)
{
  $sql = "SELECT COUNT(card_id) AS iNr
          FROM mytcg_usercard UC
          INNER JOIN mytcg_usercardstatus UCS ON UCS.usercardstatus_id = UC.usercardstatus_id
          WHERE UC.user_id = ".$_SESSION['user']['id']." AND UC.card_id = ".$cardID." AND UCS.description = 'Album'";
    $r = myqu($sql);
  return $r[0]['iNr'];
}

function getCardCategories($iCatID){
  global $Conf;
  $pre = $Conf["database"]["table_prefix"];
  $sCats = "";
  //GET LEVEL NR
  $sql = "SELECT level FROM mytcg_category WHERE category_id=".$iCatID;
  $level = myqu($sql);
  $level = $level[0]['level'];
  
  if($level==3){ //No subs
    $sCats = $iCatID;
  }
  elseif($level==2){ //Subs 1 level down
    $sql = "SELECT category_id FROM myctg_category WHERE parent_id=".$iCatID;
    $aCats = myqu($sql);
    foreach($aCats as $cat){
      $sCats .= $cat['category_id'].",";
    }
    $sCats = substr($sCats, 0, -1);
  }
  elseif($level==1){ //Subs 2 levels down
    $sql = "SELECT category_id FROM myctg_category WHERE parent_id=".$iCatID;
    $aSub = myqu($sql);
    $aSub = $aSub[0]['category_id'];
    
    $sql = "SELECT category_id FROM mytcg_category WHERE parent_id=".$aSub;
    $aCats = myqu($sql);
    foreach($aCats as $cat){
      $sCats .= $cat['category_id'].",";
    }
    $sCats = substr($sCats, 0, -1);
  }
  return $sCats;
}


function sendEmail($sEmailAddress,$sFromEmailAddress,$sSubject,$sMessage){
	$sHeaders='From: '.$sFromEmailAddress;
	mail($sEmailAddress,$sSubject,$sMessage,$sHeaders);
	return;
}


function cleanInput($sDirtyInput){
	return $sDirtyInput;
}


function findSQLValueFromKey($aData,$sCategory,$sKey){
	$iFound=0;
	$iCount=0;
	$sOutput="";
	while ((!$iFound)&&($sValue=$aData[$iCount]["keyname"])){
		if (($sValue==$sKey)&&($sCategory==$aData[$iCount]["category"])){
			$sOutput=$aData[$iCount]["keyvalue"];
			$iFound=1;
		} else {
			$iCount++;
		}
	}
	return $sOutput;
}


function sanitize($sStringUserInput){
	$sString=htmlspecialchars($sStringUserInput);
	if (mb_detect_encoding($sString)!="UTF-8"){
		$sString=utf8_encode($sString);	
	}
	return $sString;
}
/*
function customError($errno,$errstr){
  $aFileHandle=fopen("var/errorlog.log","a+");
  fwrite($aFileHandle,"[".date("Y-m-d H:i:s")."] Err:".$errno." - ".$errstr." - ".$_SERVER['PHP_SELF']."\r\n");
  fclose($aFileHandle);
  die();
}
set_error_handler("customError");
*/

// execute mysql query and log, return in associative array
function myqu($sQuery){	  
	global $Conf;
  $db = $Conf["database"];
  $aOutput=array();
  $pattern = '/INSERT/i';
  
	$aLink=mysqli_connect($db["host"],$db["username"],$db["password"],$db["databasename"]);
	$sQuery=str_replace("&nbsp;","",$sQuery);
	$sQueryCut=substr($sQuery,0,1500);
  
	if($aResult=@mysqli_query($aLink, $sQuery))
	{
    
		//If insert - return last insert id
		if(preg_match($pattern, $sQuery)){
			$mp = mysqli_insert_id($aLink);
			@mysqli_free_result($aResult);
      mysqli_close($aLink);
			return $mp;
		}
    //Else build return array
		while ($aRow=@mysqli_fetch_array($aResult,MYSQL_BOTH)){
			$aOutput[]=$aRow;
		}
		return $aOutput;
	}
  else{
    $aFileHandle=fopen("/usr/www/users/mytcga/var/sqlq.log","a+");
    fwrite($aFileHandle,"[".date("Y-m-d H:i:s")."] Err:".mysqli_errno($aLink)." - ".mysqli_error($aLink)." - ".$_SERVER['PHP_SELF']."\r\n");
    fclose($aFileHandle);
    $aFileHandle = null;
    @mysqli_free_result($aResult);
    mysqli_close($aLink);
  }
}

?>
