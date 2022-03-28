<?php
header("Access-Control-Allow-Origin: *");
date_default_timezone_set("Africa/Lagos");
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require("app.php");

// exit();
//------------------------------------------
 function lrand ($p) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randstring = '';
    for ($i = 0; $i < 7; $i++) {
        $randstring .= $characters[rand(0, strlen($characters))];
    }
    return strtoupper($p."-".$randstring);
}

function otp_generator(){
    $otp = substr(str_shuffle("0123456789"), 0, 4);
    return $otp;
}

function get_routes($db, $type = 0) {
    $routes = $db->query("SELECT * FROM routes WHERE type=".$type)->result();
    $route_list = array();
    foreach($routes as $route) {
        $route['dropoffs'] = json_decode(htmlspecialchars_decode($route['dropoffs']), true);
        $route['alt_dropoffs'] = json_decode(htmlspecialchars_decode($route['alt_dropoffs']), true);
        
        $route['schedule'] = json_decode(htmlspecialchars_decode($route['schedule']), true);
        $route['alt_schedule'] = json_decode(htmlspecialchars_decode($route['alt_schedule']), true);
        
        $route['geo'] = json_decode(htmlspecialchars_decode($route['geo']), true);
        $route['alt_geo'] = json_decode(htmlspecialchars_decode($route['alt_geo']), true);

        $t = date("w") + 1;
        $t = $t > 6 ? 0 : $t;
        $days = ["sunday", "monday", "tuesday", "wednesday", "thursday", "friday", "saturday"];
        
        $route['schedule'] = array(
            "today"     => empty($route['schedule']) ? null : explode(",", $route['schedule'][$days[date("w")]]),
            "tomorrow"  => empty($route['schedule']) ? null : explode(",", $route['schedule'][$days[$t]])
        );
        $route['alt_schedule'] = array(
            "today"     => empty($route['alt_schedule']) ? null : explode(",", $route['alt_schedule'][$days[date("w")]]),
            "tomorrow"  => empty($route['alt_schedule']) ? null : explode(",", $route['alt_schedule'][$days[$t]])
        );
        array_push($route_list, $route);
    }
    return $route_list;
}

function auto_assign ($db, $tid) {
    $trip = $db->query("SELECT * FROM trips WHERE id=".$tid." AND driver=0 AND paid=1")->result();
    if(count($trip)) {
        $tr = $trip[0];
        $drivers = $db->query("SELECT * FROM users WHERE utype=1 AND start_job=0 AND verified=1 AND route=".$tr['route_id']." AND position=".$tr['coming'])->result();
        if(count($drivers)) {
            $dv = false;
            foreach($drivers as $d) {
                if($d["job_time"] == $tr["time"]) {
                    $dv = $d;
                }
            }
            if(!$dv) {
                foreach($drivers as $d) {
                    if(empty($d["job_time"]) && !$dv) {
                        $dv = $d;
                    }
                }
            }
            if($dv) {
                $db->set("trips", ["driver" => $dv['id']], "id=".$tid);
                $db->set("users", ["job_time" => $tr["time"]], "id=".$dv['id']);
            }
        }
    }
}


if(q("banners")){
    $filelist = array();
    $banner_uri = "https://admin.rabbit.africa/assets/img/banners/";
    $db->query("SELECT image FROM banners WHERE available=1 ORDER BY id DESC")->result();
    
    for($i = 0; $i<count($rows); $i++){
        $filelist[] = $banner_uri.$rows[$i]['image'];
    }
    
    echo json_encode(["images"=> $filelist]);
    exit();
}

if(q("deleteUser") && isset($_GET['id'])) {
    $db->remove("users", 'id='.$_GET['id']);
    header("location: ".$_SERVER['HTTP_REFERER'].(isset($_GET['u']) ? $_GET['u'] : "users"));
    exit();
}


if(q("verifyUser") && isset($_GET['id'])) {
    $db->set("users", ["verified" => 1], 'id='.$_GET['id']);
    header("location: ".$_SERVER['HTTP_REFERER'].$_GET['u']);
    exit();
}

if(q("assignRoute") && isset($_GET['p']) && isset($_GET['id'])) {
    $db->set("users", ["route" => $_GET['id']], 'id='.$_GET['p']);
    header("location: ".$_SERVER['HTTP_REFERER'].$_GET['u']);
    exit();
}

if(q("assignMerchant") && isset($_GET['p']) && isset($_GET['id'])) {
    $db->query("SELECT rid FROM pickups WHERE did=".$_GET['id'])->result();
    if($count > 0) {
        $db->set("pickups", ["rid" => $_GET['p']], 'did='.$_GET['id']);
        header("location: ".$_SERVER['HTTP_REFERER'].'pickups');
    } else {
        $db->set("deliveries", ["mid" => $_GET['p']], 'id='.$_GET['id']);
        header("location: ".$_SERVER['HTTP_REFERER'].'deliveries');
    }
    exit();
}

if(q("assignjob")) {
    $time =  $db->query("SELECT time, coming FROM trips WHERE id=".$_GET['t'])->result()[0]["time"]; 
    $position = $row['coming'];
    $temi =  $db->query("SELECT job_time FROM users WHERE id=".$_GET['d'])->result()[0]["job_time"];
    // if(empty($temi) || $temi == $time) {
        $db->set("trips", ["driver" => $_GET['d']], "id=".$_GET['t']);
        $db->set("users", ["job_time" => $time, "position" => $position], "id=".$_GET['d']);
    // }
    header("location: ".$_SERVER['HTTP_REFERER'].$_GET['u']);
    exit();
}

if(q("assignDeli") && isset($_GET['d'])) {
    $db->set("pickups", ["driver" => $_GET['driver']], "did=".$_GET['d']);
    header("location: ".$_SERVER['HTTP_REFERER'].$_GET['u']);
    exit();
}

if(q("newroute") || q("updroute")) {
    print_v($_POST);
    $dropoffs = $alt_dropoffs = $geo = $alt_geo = array();
    
    foreach($_POST['destination'] as $dk => $dv) {
        $dropoffs[] = [$dv => $_POST['amount'][$dk]];
        $geo[] = [$dv => str_replace(",", "|", $_POST['geo'][$dk])];
    }
    foreach($_POST['alt_destination'] as $adk => $adv) {
        $alt_dropoffs[] = [$adv => $_POST['alt_amount'][$adk]];
        $alt_geo[] = [$adv => str_replace(",", "|", $_POST['alt_geo'][$adk])];
    }
    
    if(q("updroute")) {
        $db->set("routes", [
            "location" => $from,
            "dropoffs" => json_encode($dropoffs),
            "alt_dropoffs" => json_encode($alt_dropoffs),
            "geo" => json_encode($geo),
            "type" => $type,
            "Btype" => $Btype,
            "alt_geo" => json_encode($alt_geo),
            "geo_from" => str_replace(",", "|", $_POST['geo_from']),
            "geo_to" => str_replace(",", "|", $_POST['geo_to']),
            "destination" => $to,
            "price" => $price,
            "create_time" => $now
        ], "id=".$id);
    } else {
        $db->save("routes", [
            "location" => $from,
            "dropoffs" => json_encode($dropoffs),
            "alt_dropoffs" => json_encode($alt_dropoffs),
            "geo" => json_encode($geo),
            "type" => $type,
            "Btype" => $Btype,
            "alt_geo" => json_encode($alt_geo),
            "geo_from" => str_replace(",", "|", $_POST['geo_from']),
            "geo_to" => str_replace(",", "|", $_POST['geo_to']),
            "destination" => $to,
            "price" => $price,
            "create_time" => $now
        ]);
    }
    header("location: ".$_SERVER['HTTP_REFERER']."routes");
    exit();
}

if(q("newstop") || q("updstop")) {
    print_v($_POST);
    $arias = explode(",", $areas);
    $areas = array();
    $updstop = false;
    
    foreach($arias as $a) {
        if(!empty(trim($a))) {
            array_push($areas, ucwords(trim($a)));
        }
    }
    
    $areas = join(",", $areas);
    $db->get("routes", "id=".explode(".", $stop)[0]);
    $name  = $row[explode(".", $stop)[1] > 0 ? 'destination' : 'location'];
    
    if(q("newstop")) {
        $db->get("stops", "stop='".$stop."'");
        if($count) {
            $id = $row['id'];
            $updstop = true;
            $areas = $row["areas"].",".$areas;
        }
    }
    
    if(q("updstop") || $updstop) {
        $db->set("stops", [
            "name" => $name,
            "stop" => $stop,
            "areas" => $areas,
            "create_time" => $now
        ], "id=".$id);
    } else {
        $db->save("stops", [
            "name" => $name,
            "stop" => $stop,
            "areas" => $areas,
            "create_time" => $now
        ]);
    }
    header("location: ".$_SERVER['HTTP_REFERER']."bustops");
    exit();
}

if(q("deleteRoute") && isset($_GET['id'])) {
    $db->remove("routes", 'id='.$_GET['id']);
    header("location: ".$_SERVER['HTTP_REFERER']."routes");
    exit();
}

if(q("deleteStop") && isset($_GET['id'])) {
    $db->remove("stops", 'id='.$_GET['id']);
    header("location: ".$_SERVER['HTTP_REFERER']."bustops");
    exit();
}

if(q("deleteTrip") && isset($_GET['id'])) {
    $db->remove("trips", "id=".$_REQUEST['id']);
    header("location: ".$_SERVER['HTTP_REFERER']."trips");
    exit();
}

if(q("deleteDeli") && isset($_GET['id'])) {
    $db->remove("deliveries", "id=".$_REQUEST['id']);
    header("location: ".$_SERVER['HTTP_REFERER']."deliveries");
    exit();
}

if(q("uop") && isset($_POST['id'])) {
    
    $schedule = json_encode(array(
        'sunday'    => join(",", $_POST['sunday']),
        'monday'    => join(",", $_POST['monday']),
        'tuesday'   => join(",", $_POST['tuesday']),
        'wednesday' => join(",", $_POST['wednesday']),
        'thursday'  => join(",", $_POST['thursday']),
        'friday'    => join(",", $_POST['friday']),
        'saturday'  => join(",", $_POST['saturday'])
    ));
    
    $db->set("routes", [
        (isset($_POST['alt']) ? 'alt_' : '')."schedule" => $schedule
    ], "id=".$_POST['id']);
    header("location: ".$_SERVER['HTTP_REFERER']."operations");
    exit();
}

//------------------------------------------

if(!isset($_POST['__ed'])) {
    $db->jsonHeader();
    $_POST = file_get_contents("php://input");
    $_POST = json_decode($_POST, true);
}

//------------------------------------------
    
if(q("login")) {
    print_v($_POST);
    $query = "SELECT * FROM users WHERE (email='".$u."' OR phone='".$u."') AND password='".md5(strtolower($p))."'";
    $db->query($query)->result();
    if($count > 0) {
        unset($row['password']);
    }
    
    $email = $row['email'];
    $query = $dbc->prepare("SELECT is_verified from user_verification where email=:email");
    $query->bindValue(":email", $email);
    $query->execute();
    $verified = $query->fetch();
    $msg = $count > 0 ? ($verified['is_verified'] ? "Account Verified" : "Account not Verified" ) : "Invalid Credential" ;
    

    $db->reply([
        "message" => $count > 0 ? ($verified['is_verified'] ? true : "Account not Verified" ) : "Invalid Credential",
        "status" => $verified['is_verified'] && $count > 0,
        "data" => $verified['is_verified'] && $count > 0 ? $row : null,
    ]);
    exit();
}

if(q("walletBalance")){
    $email = $_POST['email'];
    $query = $dbc->prepare("SELECT wallet from users where email=:email");
    $query->bindValue(":email", $email);
    if ($query->execute()){
        $wallet = $query->fetch()[0];
        echo json_encode(['wallet'=>$wallet]);
    }
    exit();
}



if(q("verifyRegisteration")){
    if( !empty($_POST['email']) and filter_var($_POST['email'], FILTER_VALIDATE_EMAIL) ){
        // generate otp
        $otp = otp_generator();
        $email = $_POST['email'];
        
        // retrieve user details
        $query = $dbc->prepare("SELECT name FROM users where email=:email");
        $query->bindValue(":email", $email);
        $query->execute();
        $user = $query->fetch();
        
        
        // register token
        $query = $dbc->prepare("SELECT * from user_verification where email=:email");
        $query->bindValue(":email", $email);
        $query->execute();
        $res = $query->fetch();
        
        if( $res['email'] == $email){
            $query = $dbc->prepare("UPDATE  user_verification SET token=:token, is_verified=0 where email=:email");
    
        }else{
            $query = $dbc->prepare("INSERT INTO user_verification (email, token, is_verified) VALUES (:email, :token, 0)");
        }
        $query->bindValue(":email", $email);
        $query->bindValue(":token", $otp);
        $query->execute(); 
        require("mail.php");
        rabbit_mailer($email, $user['name'], $otp, "verify");
        
    } else{
        echo json_encode(['status'=>'error', 'message'=>"Invalid email address"]);
    }
    
    exit();
}



if(q("forgetPassword")){
    
    if( !empty($_POST['email']) and filter_var($_POST['email'], FILTER_VALIDATE_EMAIL) ){
        // generate token
        $otp = otp_generator();
        $email = $_POST['email'];
        
        // retrieve user details
        $query = $dbc->prepare("SELECT name FROM users where email=:email");
        $query->bindValue(":email", $_POST['email']);
        $query->execute();
        $user = $query->fetch();
        
        
        // register token
        $query = $dbc->prepare("SELECT * from password_reset where email=:email");
        $query->bindValue(":email", $email);
        $query->execute();
        $res = $query->fetch();
        
        
        if( $res['email'] == $email){
            $query = $dbc->prepare("UPDATE  password_reset SET token=:token  where email=:email");
        }else{
            $query = $dbc->prepare("INSERT INTO password_reset (email, token) VALUES (:email, :token)");
        }
        $query->bindValue(":email", $email);
        $query->bindValue(":token", $otp);
        $query->execute(); 

        require("mail.php");
        rabbit_mailer($email, $user['name'], $otp, "reset");
        
    }else{
        echo json_encode(['status'=>'error', 'message'=>"Invalid email address"]);
    }
    exit();
}

if(q("deliveryLocation")){
    
    $query = $dbc->prepare("SELECT location from delivery_zone_loc");
    $query->execute();
    $loc = $query->fetchAll(PDO::FETCH_COLUMN, ucfirst(0));
    echo json_encode(['location'=>$loc]);
    exit();
    
}

if(q("deliveryPrice")){
    if(!empty($_POST['sender_loc']) and !empty($_POST['reciever_loc'])){
        
        $query = $dbc->prepare("SELECT zone from delivery_zone_loc where location=:sender_loc or location=:reciever_loc");
        $query->bindValue(":sender_loc", $_POST['sender_loc']);
        $query->bindValue(":reciever_loc", $_POST['reciever_loc']);
        $query->execute();
        $zone = $query->fetchAll(PDO::FETCH_COLUMN, 0);
        if(sizeof($zone) <= 1){
            // delivery in same zone
            $query = $dbc->prepare("SELECT price from delivery_zone_price where zone=:zone");
            $query->bindValue(':zone',$zone[0]);
            $query->execute();
            $price = $query->fetch(PDO::FETCH_COLUMN, 0);
            echo json_encode(['status'=>'success','price'=>$price]);
        } else {
            // delivery to different zone
            $query = $dbc->prepare("SELECT price from delivery_zone_price where zone=:zone1 or zone=:zone2 ");
            $query->bindValue(':zone1',$zone[0]);
            $query->bindValue(':zone2', $zone[1]);
            $query->execute();
            $price = $query->fetchAll(PDO::FETCH_COLUMN, 0);
            echo json_encode(['status'=>'success','price'=>$price]);
        }
    }
    exit();
}


if(q("updatePassword")){
    $email = $_POST['email'];
    $password = md5(strtolower($_POST['password']));
    $otp = $_POST['otp'];
    
    
    $query = $dbc->prepare("SELECT token from password_reset where email=:email");
    $query->bindValue(":email", $email);
    $query->execute();
    $res = $query->fetch();
    
    if($res['token'] == $otp){
        $query = $dbc->prepare("UPDATE users set password=:password where email=:email");
        $query->bindValue(":email", $email);
        $query->bindValue(":password", $password);
        if ($query->execute()){ 
            $query = $dbc->prepare("DELETE from password_reset where email=:email");
            $query->bindValue(":email", $email);
            $query->execute();
            echo json_encode(['status'=>"success",'message' => "Password reset successful!"]) ;
        }else{ 
            echo json_decode(['status'=>"failed", 'message'=>"Password reset failed, Please try again!" ]);
        }
    }else{
        echo json_encode(['status'=>'failed','message'=>"OTP validation error"]);
    }
    exit();
}

if(q("verified")){
    $otp = $_POST['otp'];
    $email = $_POST['email'];
    
    $query = $dbc->prepare("SELECT token FROM user_verification  WHERE email=:email");
    $query->bindValue(":email", $email);
    $query->execute();
    
    
    $res = $query->fetch();
    if($res['token'] == $otp){
        $query = $dbc->prepare("UPDATE user_verification SET is_verified=1 WHERE email=:email");
        $query->bindValue(":email", $email);
        if($query->execute()){
            echo json_encode(["message" => "Account Verified Succesfully","status" => "success",]);
        }
    }else{
        echo json_encode(["message" => "Account Verified Failed","status" => "error",]);
    }
    exit();
}


if(q("register")) {
    print_v($_POST);
    $password = strtolower($_POST['password']);
    if(count($is_empty)) {
        $db->reply([
            "message" => "Please fill all fields",
            "status" => false,
            "empty" => $is_empty
        ]);
    } else {
        
        $query = $dbc->prepare("SELECT * FROM users WHERE email=:email OR phone=:phone");
        $query->bindValue(":email", $email);
        $query->bindValue(":phone", $phone);
        $query->execute();
        $user =  $query->fetch();
        
        if($user > 0) {
            $db->reply([
                "message" => ($email == $user['email'] ? "Email" : "Phone number")." already exists",
                "status" => false
            ]);
        } else {
            if(isset($_POST['utype'])) {
                $_POST["pid"] = lrand($_POST['utype'] == 1 ? "D" : "L");
            } else {
                $_POST["pid"] = lrand("U");
            }
            if(isset($_POST['route'])) {
                $_POST['address']   = isset($_POST['address']) ? $_POST['address'] : $db->query("SELECT name FROM stops WHERE stop='".$_POST['route']."'")->result()[0]["name"];
                $_POST['position']  = explode(".", $_POST['route'])[1];
                $_POST['route']     = explode(".", $_POST['route'])[0];
            }
            
            unset($_POST['q']);
            $_POST['password'] = md5($password);
            $_POST['create_time'] = $now;
            $_POST["name"] = str_replace("_", " ", $_POST["name"]);
            $db->save("users", $_POST);
            $query = "SELECT * FROM users WHERE (email='".$email."' OR phone='".$phone."') AND password='".md5($password)."'";
            $db->query($query)->result();
            
            
            unset($row['password']);
            $db->reply([
                "message" => "Registeration successful",
                "status" => true,
                "data" => $row
            ]);
        }
    }
    exit();
}

if(q("newTrip")) {
    unset($_POST['q']);
    print_v($_POST);
    $_POST['create_time'] = $now;
    $db->save("trips", $_POST);
    $db->get("trips", 'id='.$id);
    $db->reply([
        "message" => "Trip Booked successfully",
        "trip"    => $rows,
        "status"  => true
    ]);
    exit();
}

if(q("routes")){
    $query = $dbc->prepare("SELECT location, destination, price FROM routes");
    $query->execute();
    $res = $query->fetchAll();
    
    echo json_encode(['data'=>$res]);
    exit();
}



if(q("confirmTrip")) {
    unset($_POST['q']);
    $_POST['ticket'] = "RD-".strtoupper(substr(md5(microtime()),rand(0,26),5));
    print_v($_POST);
    $db->get("users", "wallet", "id=".$uid);
    $price = (float) $price;

    if($row['wallet'] >= $price) {
        $namt = $row['wallet'] - $price;
        
        $_POST['create_time'] = $now;
        $_POST['paid']        = 1;
        $db->save("trips", $_POST);
        $tid = $id;
    
        $db->save("transactions", [
            "uid"           => $uid,
            "amount"        => $price,
            "ttype"         => "debit",
            "utype"         => "user",
            "type"          => "trip",
            "type_id"       => $tid,
            "create_time"   => $now
        ]);
        
        $db->set("users", ["wallet" => $namt], "id=".$uid);
        // $db->set("trips", ["paid" => 1], "id=".$tid);
        
        // auto_assign($db, $tid);
        
        $user = $db->get("users", "id=".$uid)[0];
        unset($user["password"]);
        $db->get("trips", 'id='.$tid);
        
        $db->reply([
            "message" => "Trip Booked successfully",
            "trip"    => $rows,
            "data"    => $user,
            "status"  => true
        ]);
    } else {
        $db->reply([
            "message" => "Insufficient balance",
            "status"  => false
        ]);
    }
    
    exit();
}



if(q("confirmDelivery")) {
    unset($_POST['q']);
    print_v($_POST);
    $db->get("users", "wallet", "id=".$uid);
    $price = (float) $price;

    if($row['wallet'] >= $price) {
        $namt = $row['wallet'] - $price;
        
        foreach($order as $deli) {
            $deli["uid"] = $uid;
            $deli["create_time"] = $now;
	    $deli['price'] = $price;
            $db->save("deliveries", $deli);
            $tid = $id;
            $db->save("transactions", [
                "uid"           => $uid,
                "amount"        => $price,
                "ttype"         => "debit",
                "utype"         => "user",
                "type"          => "delivery",
                "type_id"       => $tid,
                "create_time"   => $now
            ]);
            $db->set("users", ["wallet" => $namt], "id=".$uid);
            // auto_assign($db, $tid);
        }
        
        $user = $db->get("users", "id=".$uid)[0]; unset($user["password"]);
        $db->get("deliveries", 'uid='.$uid);
        
        $db->reply([
            "message"       => "Delivery Paid successfully",
            "deliveries"    => $rows,
            "data"          => $user,
            "status"        => true
        ]);
    } else {
        $db->reply([
            "message" => "Insufficient balance",
            "status"  => false
        ]);
    }
    
    exit();
}


if(q("cancelTrip")) {
    $db->remove("trips", "id=".$_REQUEST['id']);
    $db->reply([
        "message" => "Trip Paid successfully",
        "status"  => true
    ]);
    exit();
}


if(q("loadApp") && isset($_GET['u'])) {
    $stops          = $db->get("stops");
    $settings       = $db->get("settings")[0];
    $data           = $db->query("SELECT * FROM users WHERE id=".$_GET['u'])->result()[0];
    $deliveries     = $db->query('SELECT * FROM deliveries WHERE uid='.$_GET['u']." ORDER BY id DESC")->result();
    $trips          = $db->query('SELECT * FROM trips WHERE uid='.$_GET['u']." GROUP BY location, dropoff ORDER BY id DESC")->result();
    unset($data["password"]);
    $db->reply([
        "data"      => $data,
        "trips"     => $trips,
        "stops"     => $stops,
        "settings"  => $settings,
        "deliveries"=> $deliveries,
        "playstore" => "https://play.google.com/store/apps/details?id=com.rabbit.app",
        "routes"    => get_routes($db),
        "soon"      => get_routes($db, 1),
        "version"   => "1.03",
        "update"    => true
    ]);
    exit();
}

if(q("loadStops")) {
    $stops          = $db->get("stops");
    $db->reply([
        "stops"     => $stops
    ]);
    exit();
}

if(q("loadCart") && isset($_GET['u'])) {
    $db->query("SELECT * FROM deliveries WHERE mid=".$_GET['u']." AND status<2 AND stage<2")->result();
    $pickup = array();
    $delivery = array();
    
    foreach($rows as $cart) {
        $cart['route'] = null;
        if($cart['sloc'] != $cart['rloc']) {
            $sid = explode(".", $cart['sloc']); $db->query("SELECT location, destination FROM routes WHERE id=".$sid[0])->result();
            $from = $row[$sid[1] > 0 ? 'destination' : 'location'];
            $rid = explode(".", $cart['rloc']); $db->query("SELECT location, destination FROM routes WHERE id=".$rid[0])->result();
            $to = $row[$rid[1] > 0 ? 'destination' : 'location'];
            $cart['route'] = [$from, $to];
        }
        if($cart['status'] == 0) {
            $pickup[] = $cart;
        } else {
            $delivery[] = $cart;
        }
    }
    
    $db->query("SELECT pickups.rid, deliveries.* FROM pickups INNER JOIN deliveries ON pickups.did = deliveries.id WHERE pickups.rid = ".$_GET['u']." AND deliveries.status < 2")->result();
    foreach($rows as $pc) {
        $sid = explode(".", $pc['sloc']); $db->query("SELECT location, destination FROM routes WHERE id=".$sid[0])->result();
        $from = $row[$sid[1] > 0 ? 'destination' : 'location'];
        $rid = explode(".", $pc['rloc']); $db->query("SELECT location, destination FROM routes WHERE id=".$rid[0])->result();
        $to = $row[$rid[1] > 0 ? 'destination' : 'location'];
        $pc['route'] = [$from, $to];
        $delivery[] = $pc;
    }
    
    $db->reply([
        "pickup" => $pickup,
        "delivery" => $delivery
    ]);
    exit();
}

if(q("loadHistory") && isset($_GET['u'])) {
    $history = $db->query("SELECT * FROM deliveries WHERE status > 1 AND (mid=".$_GET['u']." OR rider=".$_GET['u'].") ORDER BY id DESC")->result();
    $db->reply([
        "history" => $history
    ]);
    exit();
}

if(q("pickup") && isset($_POST['id'])) {
    $db->set("deliveries", ["qr" => $_POST['qr'], "status" => 1, "stage" => 1, "modify_time" => $now], "id=".$_POST['id']);
    $db->reply();
    exit();
}

if((q("transferDelivery") || q("pickDelivery")) && isset($_POST['id'])) {
    if(q("transferDelivery")) {
        $db->query("SELECT * FROM pickups WHERE did=".$_POST['id'])->result();
        if($count == 0) {
            $db->save("pickups", ["did" => $_POST['id'], "pc_time" => $now]);
        }
    }
    $db->set("deliveries", ["status" => $_POST['st'], "stage" => $_POST['stage'], "modify_time" => $now], "id=".$_POST['id']);
    $db->reply();
    exit();
}

if(q("endDelivery") && isset($_POST['id'])) {
    $db->set("deliveries", ["status" => 2, "stage" => 4, "rider" => $_POST['r'], "modify_time" => $now], "id=".$_POST['id']);
    $db->reply();
    exit();
}

if(q("loadProfile") && isset($_GET['u'])) {
    $data           = $db->query("SELECT * FROM users WHERE id=".$_GET['u'])->result()[0];
    unset($data["password"]);
    $db->reply([
        "data"      => $data,
    ]);
    exit();
}

if(q("dp") && isset($_GET['u'])) {
    $id = $_GET['u'];
    if(saveDP("photo", "user-dp-".$id)) {
        $db->set("users", ["dp" => "https://clients.codeapex.io/rabbit/assets/dp/user-dp-".$id.".".i_ext("photo"), "ext" => i_ext("photo"), "modify_time" => $now], "id=".$id);
        $db->get("users", "id=".$id);
        unset($row["password"]);
        $db->reply([
            "message" => "Upload successfull",
            "status" => true,
            "data" => $row
        ]);
    } else {
        $db->reply([
            "message" => "File too large",
            "status" => false,
            "data" => null
        ]);
    }
    exit();
}

if(q("loadHist") && isset($_GET['u'])) {
    $uid = $_GET['u'];
    $db->query("SELECT * FROM transactions WHERE (type='delivery' OR type='trip') AND uid=".$uid." ORDER BY id DESC")->result();
    $history = [];
    foreach($rows as $r) {
        if($r['type'] == 'delivery') {
            $db->query("SELECT * FROM deliveries WHERE id=".$r['type_id']." AND uid=".$uid)->result();
            if($count > 0) {
                $history[] = [
                    "from"      => $row['stop'],
                    "to"        => $row['rstop'],
                    "time"      => $row['create_time'],
                    "price"     => $row['price'],
                    "status"    => $row['status'],
                    "type"      => $r['type']
                ];
            }
        } else if($r['type'] == 'trip') {
            $db->query("SELECT * FROM trips WHERE id=".$r['type_id']." AND uid=".$uid)->result();
            if($count > 0) {
                $history[] = [
                    "from"      => $row['location'],
                    "to"        => $row['destination'],
                    "time"      => $row['create_time'],
                    "price"     => $row['price'],
                    "status"    => $row['status'],
                    "type"      => $r['type']
                ];
            }
        }
    }
    $db->reply([
        "history"   => $history
    ]);
    exit();
}

if(q("routes")) {
    $db->reply(get_routes($db));
    exit();
}

if(q("verifyPayment")) {
    $id   = $_POST['id'];
    $tid  = $_POST['tid'];
    $curl = curl_init();
    curl_setopt_array($curl, array(
      CURLOPT_URL => "https://api.flutterwave.com/v3/transactions/$tid/verify",
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => "GET",
      CURLOPT_HTTPHEADER => array(
        "Content-Type: application/json",
        "Authorization: Bearer FLWSECK-29b5e22a95c00a85ef0e9005cec8f21b-X"
      ),
    ));
    $response = curl_exec($curl);
    curl_close($curl);
    $response = json_decode($response, true);
    
    if($response["status"] == "success") {
        $amt = (float) $response["data"]["amount"];
        $db->get("users", "wallet, last_tid", "id=".$id);
        if($tid != $row["last_tid"]) {
            $wallet = $row["wallet"]+$amt;
            $db->set("users", [
                "wallet"    => $wallet,
                "last_tid"  => $tid
            ], "id=".$id);
        }
    }
    
    $db->get("users", "id=".$id);
    unset($row["pass"]);
    $db->reply([
        "message" => "Profile data",
        "data"    => $row,
        "status"  => true,
        "res"     => $id == 5 ? $response : null
    ]);
    
    exit();
}


if(q('flutterhook')){
    $input = @file_get_contents("php://input");
    
    $signature = (isset($_SERVER['HTTP_VERIF_HASH']) ? $_SERVER['HTTP_VERIF_HASH'] : '');
    if(!$signature) exit();
    
    $local_signature = "f269277a3b1fhYH90oklahl8hddba7c29b01f51GHjiDjidor0e6fd8f5b3";
    if($signature !== $local_signature ) exit();
    
    http_response_code(200);
    
    $resp = json_decode($input);
    
    $tid = $resp->data->id;
    $status = $resp->data->status;
    
    
    if ($status == "successful" ) {
        // verify Transaction
        $headers = ['Content-Type: application/json'];
        $ch = curl_init();
    
        curl_setopt($ch, CURLOPT_URL, "https://api.flutterwave.com/v3/transactions/$tid/verify");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_HTTPHEADER,array("Authorization: Bearer FLWSECK-29b5e22a95c00a85ef0e9005cec8f21b-X"));
    
        // execute!
        $response = curl_exec($ch);
        $response = json_decode($response);
        // close the connection, release resources used
        curl_close($ch);
        
        if($response->status == "success"){
            if($response->data->status == "successful"){
            
                $ref = $response->data->id;
                $email = $response->data->customer->email;
                $amt = $response->data->amount;
        
                $query = $dbc->prepare("SELECT wallet, last_tid FROM users where email=:email");
                $query->bindValue(":email", $email);
                $query->execute();
                $user = $query->fetch();
                
                if($ref != $user['last_tid']){
                    $wallet = $user["wallet"] + $amt;
                    $query = $dbc->prepare("UPDATE users SET wallet=:wallet, last_tid=:last_tid WHERE email=:email");
                    $query->bindValue(":email", $email);
                    $query->bindValue(":wallet", $wallet);
                    $query->bindValue(":last_tid", $ref);
                    $query->execute();
                }
            }
        }
    }
    exit();
}



if(q("paystackGateway")){
    $curl = curl_init();
    $reference = $_POST['reference'];
    $amount = $_POST['amount'];
    $id = $_POST['id'];
    curl_setopt_array($curl, array(
      CURLOPT_URL => "https://api.paystack.co/transaction/verify/" .$reference,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => "GET",
      CURLOPT_HTTPHEADER => array(
        "Authorization: Bearer sk_live_cbe901921040bba021972b9b99d715c5b6df82e1",
        "Cache-Control: no-cache",
      ),
    ));
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);
    
    $read_resp = json_decode($response);

    if ($read_resp->status == False) {
      echo json_encode(array('message' => $read_resp->message, 'status' => false));
    } elseif($read_resp->status == True){

        $amt = (float) $amount;
        $db->get("users", "wallet, last_tid", "id=".$id);
        if($reference != $row["last_tid"]) {
            $wallet = $row["wallet"]+$amt;
            $db->set("users", [
                "wallet"    => $wallet,
                "last_tid"  => $reference,
            ], "id=".$id);
        }
        
        $db->get("users", "id=".$id);
        unset($row["pass"]);
        $db->reply([
            "message" => "Profile data",
            "data"    => $row,
            "status"  => true,
            "res"     => $id == 5 ? $response : null
        ]);
        
        exit();
    }
}

if (q('payHook')){
    if( (strtoupper($_SERVER['REQUEST_METHOD']) != 'POST') || !array_key_exists('HTTP_X_PAYSTACK_SIGNATURE', $_SERVER) ) exit();
    
    $input = @file_get_contents("php://input");

    define('PAYSTACK_SECRET_KEY', 'sk_live_cbe901921040bba021972b9b99d715c5b6df82e1');

    if($_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] !== hash_hmac('sha512', $input, PAYSTACK_SECRET_KEY)) exit();
    
   

    $event = json_decode($input);
    $reference = $event->data->reference;
    $status = $event->data->status;
    $email = $event->data->customer->email;
    $amt = $event->data->amount;

    if($status == "success"){
        if($event->event == 'transfer.success' || $event->event == 'charge.success'){
            
            $query = $dbc->prepare("SELECT wallet, last_tid FROM users WHERE email=:email");
            $query->bindValue(":email", $email);
            $query->execute();
            $user =  $query->fetch();
            
            if($reference != $user["last_tid"]){
                $wallet = $user["wallet"] + $amt/ 100;
                $last_tid = $user['last_tid'];
                $query = $dbc->prepare("UPDATE users SET wallet=:wallet, last_tid=:last_tid WHERE email=:email");
                $query->bindValue(":email", $email);
                $query->bindValue(":wallet", $wallet);
                $query->bindValue(":last_tid", $reference);
                $query->execute();
            }
        }
    }
   
  http_response_code(200);
}

if(q("endAllTrip")){
    $query =  $dbc -> prepare("UPDATE trips SET status =:complete where status =:pending or status=:ongoing");
    $query->bindValue(":complete", 2);
    $query->bindValue(":pending", 0);
    $query->bindValue(":ongoing", 1);
    $query->execute();
    echo json_encode(['status' => $query->rowCount()]);
    exit();
}



if((q("fetchJobs") || q("startTrip") || q("stopTrip") || q("endTrip")) && isset($_POST['d'])) {
    $d = $_POST['d'];
    if(q("startTrip")) {
        $db->set("trips", ["status" => 1, "paid" => 1], "driver=".$d." AND status = 0");
        $db->set("pickups", ["pick" => 1], "driver=".$d." AND pick=0");
        $db->set("users", ["start_job" => 1], "id=".$d);
    } else if (q("stopTrip")) {
        $position = $db->query("SELECT coming FROM trips WHERE driver=".$d." AND status=1")->result()[0]["coming"];
        $db->set("trips", ["status" => 2], "driver=".$d." AND status=1");
        $db->set("users", ["start_job" => 0, "job_time" => "", "position" => $position], "id=".$d);
    } else if(q("endTrip") && $_POST['id']) {
        $position = $db->query("SELECT coming FROM trips WHERE driver=".$d." AND id=".$_POST['id'])->result()[0]["coming"];
        $cnt = $db->query('SELECT count(*) FROM trips WHERE driver='.$d.' AND status = 1')->result()[0]["count(*)"];
        if($cnt == 1) {
            $db->set("trips", ["status" => 2], "driver=".$d." AND status = 1");
            $db->set("users", ["start_job" => 0, "job_time" => "", "position" => $position], "id=".$d);
            $db->query("SELECT did FROM pickups WHERE driver=".$d." AND pick=1")->result();
            if($count > 0) {
                foreach($rows as $r) {
                    $db->set("deliveries", ["stage" => 2], "id=".$r['did']);
                }
                $db->set("pickups", ["pick" => 2], "driver=".$d." AND pick=1");
            }
        } else {
            $db->set("trips", ["status" => 2], "driver=".$d." AND id=".$_POST['id']." AND status = 1");
        }
    }
    $driver = $db->query('SELECT start_job, job_time FROM users WHERE id='.$d)->result()[0];
    $jobs = $db->query('SELECT trips.*, users.name FROM trips LEFT JOIN users ON trips.uid = users.id WHERE trips.driver='.$d.' AND trips.status < 2')->result();
    $nj = null;
    if($count) {
        $stot = 0;
        $drops = array();
        foreach($jobs as $nj) {
            $stot += (int) $nj["no_of_seats"];
            if(!in_array($nj["dropoff"], $drops)) {
                array_push($drops, $nj["dropoff"]);
            }
        }
        $nj = [
            "location"      => $jobs[0]["location"],
            "destination"   => $jobs[0]["destination"],
            "status"        => $driver["start_job"],
            "time"          => $driver["job_time"],
            "stops"         => count($drops),
            "seats"         => $stot,
        ];
    }
    
    $pc = $db->query("SELECT * FROM pickups INNER JOIN deliveries ON pickups.did = deliveries.id WHERE pickups.pick < 2 AND pickups.driver=".$d)->result();
    
    $db->reply([
        "job"           => $nj,
        "parcels"       => $pc,
        "passengers"    => $jobs,
    ]);
    exit();
}


if(q("fetchAllTrips") && isset($_POST['d'])) {
    $d = $_POST['d'];
    $jobs = $db->query('SELECT * FROM trips WHERE driver='.$d.' AND status = 2 ORDER BY id DESC')->result();
    $history = array();
    
    if($count) {
        $stot = 0;
        $time = "";
        $day = "";
        $job = null;
        $key = null;
        $drops = array();
        
        foreach($jobs as $nj) {
            
            $unique = false;
            $nky =  explode(" ", $nj["create_time"])[0]."|".$nj["time"];
            if($key != $nky) {
                if($stot > 0) {
                    $history[$key]["seats"] = $stot;
                    $history[$key]["stops"] = count($drops);
                }
                $key = $nky;
                $job = $nj;
                $drops = array($nj["dropoff"]);
                $unique = true;
                $stot = (int) $job["no_of_seats"];
            } else {
                $stot += (int) $nj["no_of_seats"];
                if(!in_array($nj["dropoff"], $drops)) {
                    array_push($drops, $nj["dropoff"]);
                }
            }
            
            if($unique) {
                $history[$key] = [
                    "location"      => $job["location"],
                    "destination"   => $job["destination"],
                    "day"           => str_replace("-", "/", explode(" ", $job["create_time"])[0]),
                    "time"          => $job["time"],
                    "seats"         => $job["no_of_seats"],
                    "status"        => 2,
                    "stops"         => 1
                ];
            }
        }
    }
    
    $db->reply([
        "history" => $history,
    ]);
    exit();
}

if(q("checkBooking")){
    $location  = $_POST['location'];
    $destination = $_POST['destination'];
    $time = $_POST['time'];
    $date =  $_POST['date'];
    $date < date("Y-m-d") ? exit() : true;
    $query1 = $dbc->prepare("SELECT SUM(no_of_seats) as total from trips where location = :location
				 and destination=:destination and status = 0 and DATE(create_time) = :date and time = :time");
    $query1->bindValue(":location", $location);
    $query1->bindValue(":destination", $destination);
    $query1->bindValue(":date", $date);
    $query1->bindValue(":time", $time);
    $query1->execute();
    $res = $query1->fetch(PDO::FETCH_ASSOC);

    $query2 = $dbc->prepare("SELECT capacity, available from tripCapacity where location=:location and destination=:destination");
    $query2->bindValue(":location", $location);
    $query2->bindValue(":destination", $destination);
    $query2->execute();
    $route = $query2->fetch(PDO::FETCH_ASSOC);
	if($route['available']){ 
		if($res == null){
			$capacity = $route['capacity'];
			echo json_encode(['status' => true, 'seat_left' => $capacity, "message" => "There are $capacity seat left for this route"]);
		}else{
			$seat_left = $route['capacity'] - $res['total'];
			echo json_encode(['status' => true, 'seat_left' => $seat_left, 'message' =>"There are $seat_left seat left for this route"]); 
 		}
		
	}
	else {
		echo json_encode(['status' => false, "seat_left" => -1 , "message" => "Sorry, These route is not available at the moment"]);
	}
    exit();
}

if(q("autoAssign")) {
    $db->query("SELECT id FROM trips WHERE driver=0 AND status=0")->result();
    foreach($rows as $r) {
        auto_assign($db, $r['id']);
    }
    exit();
}

if(q("updQR") && isset($_REQUEST['p'])) {
    $db->set("settings", [
        "prefix"        => protect($_REQUEST['p']),
        "max"           => $_REQUEST['max'],
        "modify_time"   => $now
    ], "id=1");
    $db->reply();
    exit();
}

if(q("updslrt")) {
    $db->set("settings", [
        "short_route"   => protect($_REQUEST['s']),
        "long_route"    => protect($_REQUEST['l']),
        "icat"          => protect($_REQUEST['i']),
        "modify_time"   => $now
    ], "id=1");
    $db->reply();
    exit();
}

?>
