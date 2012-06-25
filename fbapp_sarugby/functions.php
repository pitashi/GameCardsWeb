<?php
function ieversion() {
  $match=preg_match('/MSIE ([0-9]\.[0-9])/',$_SERVER['HTTP_USER_AGENT'],$reg);
  if($match==0)
    return 10;
  else
    return floatval($reg[1]);
}

function getUserPic($username) {
	$return = false;
	$sql = "SELECT facebook_user_id FROM mytcg_user WHERE username = '".$username."' AND facebook_user_id IS NOT NULL";
	$result = myqu($sql);
	if(sizeof($result)>0){
		$return = $result[0]['facebook_user_id'];
	}
	return $return;
}

function parse_signed_request($signed_request, $secret) {
  list($encoded_sig, $payload) = explode('.', $signed_request, 2); 

  // decode the data
  $sig = base64_url_decode($encoded_sig);
  $data = json_decode(base64_url_decode($payload), true);

  if (strtoupper($data['algorithm']) !== 'HMAC-SHA256') {
    error_log('Unknown algorithm. Expected HMAC-SHA256');
    return null;
  }

  // check sig
  $expected_sig = hash_hmac('sha256', $payload, '840f9dbf9af87721af9b095c67b3339f', $raw = true);
  if ($sig !== $expected_sig) {
    error_log('Bad Signed JSON signature!');
    return null;
  }

  return $data;
}

function base64_url_decode($input) {
  return base64_decode(strtr($input, '-_', '+/'));
}

function getUserAlbumStrength($userID) {
	//$leaderboardQuery = getLeaderboardQuery(10);
	$sql = "SELECT sum(mytcg_card.avgranking) FROM mytcg_card INNER JOIN mytcg_usercard ON mytcg_card.card_id=mytcg_usercard.card_id WHERE mytcg_usercard.user_id=$userID";
	//$sql = $leaderboardQuery;
	$result = myqu($sql);
	return $result;
}

function getCardOfDay($userID) {
	$sql = "SELECT card_id, image
			FROM mytcg_card C
			ORDER BY C.card_id DESC LIMIT 1";  
  $result = myqu($sql);
  return $result;
}

function getBestCard($userID) {
  $sql = "SELECT image FROM mytcg_card
		  ORDER BY RAND()
		  LIMIT 1";
  // $sql = "SELECT ranking, image
  		  // FROM mytcg_card C
  		  // INNER JOIN mytcg_usercard UC ON (C.card_id = UC.card_id)
  		  // WHERE UC.user_id = $userID ORDER BY ranking DESC LIMIT 1;";
  $result = myqu($sql);
  return $result;
}

function getLeaderboardQuery($boardID) {
	$sql = "SELECT lquery FROM mytcg_leaderboards WHERE leaderboard_id = ".$boardID;
	$result = myqu($sql);
	return $result[0][0];
}

function getRichestUsers() {
	$leaderboardQuery = getLeaderboardQuery(1);
	$sql = $leaderboardQuery;
	//$sql = rtrim($leaderboardQuery,";");
	$result = myqu($sql);
	return $result;
}

function getCardInAlbumCount($userID,$catID = 0)
{
  $add = ($catID != 0)? " WHERE C.category_id = ".$catID : "" ;
  
  //Get all count
  $sql = "SELECT COUNT(card_id) AS iNr
         FROM mytcg_card AS C".$add;
  $r = myqu($sql);
  $totals[1] = $r[0]['iNr'];
  
  //Get owned count
  $sql = "SELECT DISTINCT UC.card_id
          FROM mytcg_usercard UC
          INNER JOIN mytcg_card C ON UC.card_id = C.card_id
          INNER JOIN mytcg_usercardstatus UCS ON UC.usercardstatus_id = UCS.usercardstatus_id
          ".$add." AND UCS.description = 'Album' AND UC.user_id = ".$userID;
  $r = myqu($sql);
  $totals[0] = sizeof($r);
  return $totals;
}

function getCardOwnedCount($cardID,$userID)
{
 $sql = "SELECT COUNT(card_id) AS iNr
          FROM mytcg_usercard UC
          INNER JOIN mytcg_usercardstatus UCS ON UCS.usercardstatus_id = UC.usercardstatus_id
          WHERE UC.user_id = ".$userID." AND UC.card_id = ".$cardID." AND UCS.description = 'Album'";
  $r = myqu($sql);
  return $r[0]['iNr'];
}

function getDeckStrength($deckID){
	$query = "SELECT SUM(C.ranking) AS deckval
			  FROM mytcg_usercard UC
			  INNER JOIN mytcg_card C ON (UC.card_id = C.card_id)
			  WHERE UC.deck_id = ".$deckID;
	$aStat=myqu($query);
	return $aStat[0]['deckval'];
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

function sendEmail($sEmailAddress,$sFromEmailAddress,$sSubject,$sMessage){
	$sHeaders='From: '.$sFromEmailAddress;
	mail($sEmailAddress,$sSubject,$sMessage,$sHeaders);
	return;
}

// execute mysql query and log, return in associative array 
function myqu($sQuery){   
  global $db;
  $aOutput=array();
  $pattern = '/INSERT/i';
  
  $aLink=mysqli_connect($db["host"],$db["username"],$db["password"],$db["database"]);
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
    echo("Err:".mysqli_errno($aLink)." - ".mysqli_error($aLink)." - ".$sQuery."\r\n");
    @mysqli_free_result($aResult);
    mysqli_close($aLink);
  }
}

?>
