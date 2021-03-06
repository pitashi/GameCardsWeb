<?php
session_start();

function openStarter($userID,$packID){
  $aGetCards = myqu("SELECT c.*, cq.booster_probability, I.description AS path
    FROM mytcg_card c
    INNER JOIN mytcg_imageserver I ON (c.thumbnail_imageserver_id = I.imageserver_id)
    INNER JOIN mytcg_productcard AS pc
    ON pc.card_id = c.card_id
    INNER JOIN mytcg_cardquality AS cq
    ON cq.cardquality_id = c.cardquality_id
    WHERE pc.product_id={$packID}");
  $iNumCards = sizeof($aGetCards);
  
  if($userID=='-1'){
    //return xml of potential cards in pack
  $sCRLF="\r\n";
  $sTab=chr(9);
  //get product details
  $sql = "SELECT P.*, I.description AS imageserver FROM mytcg_product P INNER JOIN mytcg_imageserver I ON P.full_imageserver_id=I.imageserver_id WHERE P.product_id=".$packID." LIMIT 1";
  $pack = myqu($sql);
  $pack = $pack[0];
    echo '<pack>'.$sCRLF;
  echo $sTab.'<desc val="'.$pack['description'].'" />'.$sCRLF;
  echo $sTab.'<size val="'.$pack['no_of_cards'].'" />'.$sCRLF;
  echo $sTab.'<path val="'.$pack['imageserver'].'" />'.$sCRLF;
  echo $sTab.'<image val="'.$pack['image'].'" />'.$sCRLF;
  echo $sTab.'<price val="'.$pack['price'].'" />'.$sCRLF;
  echo $sTab.'<count val="'.$iNumCards.'" />'.$sCRLF;
  echo $sTab.'<cards>'.$sCRLF;
  if($iNumCards > 0){
    $i = 0;
    foreach($aGetCards as $card){
      $sql = "SELECT COUNT(*) AS 'possess' FROM mytcg_usercard WHERE card_id=".$card['card_id']." AND user_id=".$_SESSION["user"]["id"];
      $usercard = myqu($sql);
      echo $sTab.$sTab.'<card_'.$i.'>'.$sCRLF;
      echo $sTab.$sTab.$sTab.'<card_id val="'.$card['card_id'].'" />'.$sCRLF;
      echo $sTab.$sTab.$sTab.'<description val="'.$card['description'].'" />'.$sCRLF;
      echo $sTab.$sTab.$sTab.'<possess val="'.$usercard[0]['possess'].'" />'.$sCRLF;
      echo $sTab.$sTab.$sTab.'<path val="'.$card['path'].'" />'.$sCRLF;
      echo $sTab.$sTab.$sTab.'<image val="'.$card['image'].'" />'.$sCRLF;
      echo $sTab.$sTab.'</card_'.$i.'>'.$sCRLF;
      $i++;
    }
  }
  echo $sTab.'</cards>'.$sCRLF;
  echo '</pack>';
  exit;
  }
  
  $cards = array();
  for ($i = 0; $i < $iNumCards; $i++){
    //GET CARD FROM STACK
    $iCardID = $aGetCards[$i]['card_id'];
  
    //REMOVE THE CARD FROM THE STACK
    //this bit is removed for now, as the database doesnt support individual cards amounts
    //$iReleasedLeft=$aGetCards[$i]['released_left']-1;
    //$aReleasedLeft=myqu("UPDATE mytcg_card SET released_left={$iReleasedLeft} WHERE card_id={$iCardID}");
            
    //GIVE THE CARD TO THE USER
    $aCards=myqu("INSERT mytcg_usercard (user_id, card_id, usercardstatus_id)
      SELECT {$userID}, {$iCardID}, usercardstatus_id
      FROM mytcg_usercardstatus
      WHERE description = 'Album'");
    
    $card;
    if ($cards[$iCardID] == null) {
      $card = array();
      $card['cardId'] = $iCardID;
      $card['quantity'] = 1;
    }
    else {
      $card = $cards[$iCardID];
      $card['quantity'] = $card['quantity']+1;
    }
    $cards[$iCardID] = $card;
  }
  
  //we can remove one of the products from stock though
  myqu("UPDATE mytcg_product SET in_stock=in_stock-1 WHERE product_id={$packID}");
  return $cards;
}


//GENERATES THE CONTENTS OF A BOOSTER PACK AND GIVES IT TO THE USER
function openBooster($userID,$packID){
  $iReleasedBuffer = 1;
  
  //CARD COUNT OF PACK
  $iPackCount = myqu("SELECT no_of_cards FROM mytcg_product WHERE product_id={$packID}");
  $iPackCount = $iPackCount[0]['no_of_cards'];
  
  $aQuality = myqu("SELECT distinct cq.cardquality_id,((cq.booster_probability)*{$iPackCount}) AS bp 
    FROM mytcg_cardquality cq 
    INNER JOIN mytcg_card c 
    ON c.cardquality_id = cq.cardquality_id 
    INNER JOIN mytcg_productcard pc 
    ON pc.card_id = c.card_id 
    WHERE pc.product_id = {$packID} 
    ORDER BY booster_probability ASC");
  $iQualityID = 0;
  $cards = array();
  
  //GET CARDS
  for ($i = 0; $i < $iPackCount; $i++){
    //GET A RANDOM QUALITY CARD
    $iQualityID = randomQualityID($aQuality,$iPackCount);
  
    //GET STACK OF SAME QUALITY CARDS
    $aGetCards = myqu(" SELECT c.card_id, c.cardquality_id, cq.booster_probability
      FROM mytcg_card c
      INNER JOIN  mytcg_productcard pc
      ON pc.card_id = c.card_id
      INNER JOIN mytcg_cardquality AS cq
      ON cq.cardquality_id = c.cardquality_id
      WHERE pc.product_id={$packID}
      AND c.cardquality_id={$iQualityID}");
    $iNumCards = sizeof($aGetCards);
    
    //PICK A RANDOM CARD FROM THE STACK
    $iRandom=rand(0,$iNumCards-1);
    $iCardID=$aGetCards[$iRandom]['card_id'];
          
    //GIVE THE CARD TO THE USER
    $aCards=myqu("INSERT mytcg_usercard (user_id, card_id, usercardstatus_id, is_new)
      SELECT {$userID}, {$iCardID}, usercardstatus_id, 1 
      FROM mytcg_usercardstatus
      WHERE description = 'Album'");
	  
	  $sql = "INSERT INTO tcg_transaction_log (fk_user, fk_boosterpack, fk_usercard, fk_card, transaction_date, description, tcg_credits, fk_payment_channel, application_channel, mytcg_reference_id, fk_transaction_type)
		VALUES({$userID}, {$packID}, (SELECT max(usercard_id) from mytcg_usercard where card_id = {$iCardID} and user_id = {$userID}), {$iCardID}, 
				now(), 'Card received in booster', 0, NULL, 'facebook',  NULL, 10)";
				
				/*myqu("INSERT INTO sql_capture (sql_text) VALUES (".$sql.")");
	  */
	myqu($sql);
  
    $card;
    if ($cards[$iCardID] == null) {
      $card = array();
      $card['cardId'] = $iCardID;
      $card['quantity'] = 1;
    }
    else {
      $card = $cards[$iCardID];
      $card['quantity'] = $card['quantity']+1;
    }
    $cards[$iCardID] = $card;
  }
  
  //we can remove one of the products from stock though
  myqu("UPDATE mytcg_product SET in_stock=in_stock-1 WHERE product_id={$packID}");
  
  return $cards;
}

//ROLL DICE AND CHECK WHAT QUALITY CARD THE USER RECEIVES 
function randomQualityID($aQuality,$iPackCount){
  $iRandom = rand(1, $aQuality[sizeof($aQuality) - 1]['bp']);//rand(1,$iPackCount);
  $interval=0;
  for($l=0; $l < sizeof($aQuality); $l++){
      $interval += $aQuality[$l]['bp'];
        if ($iRandom <= $interval){
          $iQualityID = $aQuality[$l]['cardquality_id'];
          break;
    }
  }
  return $iQualityID;
}

function boosterCards($userID,$packID){
  $sql = "SELECT C.*, I.description AS path
          FROM mytcg_card C
          INNER JOIN mytcg_imageserver I ON (C.thumbnail_imageserver_id = I.imageserver_id)
          INNER JOIN mytcg_productcard AS PC ON PC.card_id = C.card_id
          WHERE PC.product_id=".$packID;
  $aGetCards = myqu($sql);
  $iNumCards = sizeof($aGetCards);
  
  $sCRLF="\r\n";
  $sTab=chr(9);

  $sql = "SELECT P.*, I.description AS imageserver
          FROM mytcg_product P INNER JOIN mytcg_imageserver I ON P.full_imageserver_id=I.imageserver_id
          WHERE P.product_id=".$packID." LIMIT 1";
  $pack = myqu($sql);
  $pack = $pack[0];
  $return = '<pack>'.$sCRLF;
  $return .= $sTab.'<desc val="'.$pack['description'].'" />'.$sCRLF;
  $return .= $sTab.'<size val="'.$pack['no_of_cards'].'" />'.$sCRLF;
  $return .= $sTab.'<path val="'.$pack['imageserver'].'" />'.$sCRLF;
  $return .= $sTab.'<image val="'.$pack['image'].'" />'.$sCRLF;
  $return .= $sTab.'<price val="'.$pack['premium'].'" />'.$sCRLF;
  $return .= $sTab.'<count val="'.$iNumCards.'" />'.$sCRLF;
  $return .= $sTab.'<cards>'.$sCRLF;
  if($iNumCards > 0){
    $iCount = 0;
    foreach($aGetCards as $card){
      $iCC = getCardOwnedCount($card['card_id'],$userID);
      $return .= $sTab.$sTab.'<card_'.$iCount.'>'.$sCRLF;
      $return .= $sTab.$sTab.$sTab.'<card_id val="'.$card['card_id'].'" />'.$sCRLF;
      $return .= $sTab.$sTab.$sTab.'<description val="'.$card['description'].'" />'.$sCRLF;
      $return .= $sTab.$sTab.$sTab.'<possess val="'.$iCC.'" />'.$sCRLF;
      $return .= $sTab.$sTab.$sTab.'<path val="'.$card['path'].'" />'.$sCRLF;
      $return .= $sTab.$sTab.$sTab.'<image val="'.$card['image'].'" />'.$sCRLF;
      $return .= $sTab.$sTab.'</card_'.$iCount.'>'.$sCRLF;
      $iCount++;
    }
  }
  $return .= $sTab.'</cards>'.$sCRLF;
  $return .= '</pack>';
  return $return;
}


?>