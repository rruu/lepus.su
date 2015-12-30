<?php
function rehash($passwd, $hash){
	if(password_needs_rehash($hash, PASSWORD_DEFAULT) || empty($hash))
		return password_hash($passwd, PASSWORD_DEFAULT);
	else
		return 'no_hash';
}

function _exit(){
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
	session_unset();
	session_destroy();
	header("Location: https://lepus.dev");
}

function is_lepus_user($login){
	global $db;
	$query = $db->prepare("SELECT * FROM `users` WHERE `login` =:login");
	$query->bindParam(':login', $login, PDO::PARAM_STR);
	$query->execute();
	return ['0' => $query->rowCount(), '1' => $query->fetch()];
}

function lost_passwd_change($arr){
	$data = json_decode(lepus_crypt($arr, 'decode'), true);
	$is_user = is_lepus_user($data[0]);
	if($is_user['0'] != 1) return 'no_user';
	$row = $is_user['1'];
	$real_hash = hash('sha512' ,$_SERVER['HTTP_USER_AGENT'].$_SERVER['REMOTE_ADDR'].$row['passwd'].$row['login']);
	if($data[1] != $real_hash) return 'wrong_hash';
		else return ['id' => $row['id'], 'email' => $data[0], 'time' => $data[2]];
}

function lost_passwd($login){
	$is_user = is_lepus_user($login);
	if($is_user['0'] != 1) return 'no_user';
	$row = $is_user['1'];
	return hash('sha512' ,$_SERVER['HTTP_USER_AGENT'].$_SERVER['REMOTE_ADDR'].$row['passwd'].$row['login']);
}

function login($login, $passwd){
	global $db;
	$is_user = is_lepus_user($login);
	if($is_user['0'] != 1) return 'no_user';
	$row = $is_user['1'];
	if (password_verify($passwd, $row['passwd'])){
		$new_passwd = rehash($passwd, $row['passwd']);
		$_SESSION['id'] = $row['id'];
		$_SESSION['sess'] = hash('sha512' ,$login.$passwd.$_SERVER['REMOTE_ADDR'].$_SERVER['HTTP_USER_AGENT']);
		
		if($new_passwd != 'no_hash'){
			$query = $db->prepare("UPDATE `users` SET `passwd` = :passwd WHERE `id` = :id");
			$query->bindParam(':passwd', $new_passwd, PDO::PARAM_STR);
			$query->bindParam(':id', $row['id'], PDO::PARAM_STR);
			$query->execute();
		}
			
		$query = $db->prepare("UPDATE `users` SET `session` = :sess WHERE `id` = :id");
		$query->bindParam(':id', $row['id'], PDO::PARAM_STR);
		$query->bindParam(':sess', $_SESSION['sess'], PDO::PARAM_STR);
		$query->execute();
		
		lepus_log_ip($row['id'], ip2long($_SERVER["REMOTE_ADDR"]));
		return 'enter';
		
	} else return 'bad_passwd';
}

function auth($id, $session){
	global $db;
	$query = $db->prepare("SELECT * FROM `users` WHERE `id` = :id AND `session` = :session");
	$query->bindParam(':id', $id, PDO::PARAM_STR);
	$query->bindParam(':session', $session, PDO::PARAM_STR);
	$query->execute();
	
	if($query->rowCount() != 1){
		$query = $db->prepare("UPDATE `users` SET `session` = NULL WHERE `login` = :login AND `session` = :session");
		$query->bindParam(':login', $login, PDO::PARAM_STR);
		$query->bindParam(':session', $session, PDO::PARAM_STR);
		$query->execute();
		
		session_unset();
		session_destroy();
		return 'no_auth';
	}
	
	$row = $query->fetch();
	return ["id" => $row['id'], "login" => $row['login'], "passwd" => $row['passwd'], "data" => $row['data']];
}

function error($message, $j = 0){
	if(!is_array($message)){
		$err = [
			"no_auth" => "Неудачная попытка входа.",
			"no_user" => "Неправильный логин.",
			"bad_passwd" => "Неправильный пароль.",
			"block_user" => "Пользователь заблокирован"
		];
		if (array_key_exists($message, $err)) $j = 1;
	}
	
	if($j == 1){
		$message = ['mess' => $message, 'err' => $err[$message]];
	}else{
		$message = ['mess' => $message, 'err' => 'OK'];
	}
	
	return $message;
}

function save_user_data($id, $data){
	global $db;
	$data = json_encode($data);
	$query = $db->prepare("UPDATE `users` SET `data` = :data WHERE `id` = :id");
	$query->bindParam(':data', $data, PDO::PARAM_STR);
	$query->bindParam(':id', $id, PDO::PARAM_STR);
	$query->execute();
	return '1';
}

function _mail($email, $subject, $message){
	$headers  = "MIME-Version: 1.0\r\n";
	$headers .= "Content-type: text/html; charset=utf-8\r\n";
	$subject  = "=?utf-8?B?".base64_encode($subject)."?=";
	$headers .= "From: Lepus Artifical Intelligence <support@lepus.su>\r\n";
	mail($email, $subject, $message, $headers);
}

function genRandStr($length){
	$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
	$randomString = '';	
	for ($i = 0; $i < $length; $i++)
		$randomString .= $characters[mt_rand(0, strlen($characters) - 1)];
	return $randomString;
}

function change_passwd($passwd, $id){
	global $db;
	$query = $db->prepare("UPDATE `users` SET `passwd` = :passwd WHERE `id` = :id");
	$query->bindParam(':passwd', $passwd, PDO::PARAM_STR);
	$query->bindParam(':id', $id, PDO::PARAM_STR);
	$query->execute();
}

function lepus_crypt($input, $do = 'encode', $key = 'Jml*Zwde4a#%ix$m'){
	$algo = MCRYPT_RIJNDAEL_256;
	$mode = MCRYPT_MODE_CBC;
	$iv_size = mcrypt_get_iv_size($algo, $mode);
	$iv = mcrypt_create_iv($iv_size, MCRYPT_DEV_URANDOM);
	switch($do){
		case 'encode':	
		$ciphertext = mcrypt_encrypt($algo, $key, $input, $mode, $iv);
		$ciphertext = $iv . $ciphertext;
		$result = base64_encode($ciphertext);
		break;
		
		case 'decode':
		$ciphertext_dec = base64_decode($input);
		$iv_dec = substr($ciphertext_dec, 0, $iv_size);
		$ciphertext_dec = substr($ciphertext_dec, $iv_size);
		$result = mcrypt_decrypt($algo, $key, $ciphertext_dec, $mode, $iv_dec);
		break;
	}
	return $result;
}

function lepus_new_account($login){
	global $db;
	$is_user = is_lepus_user($login);
	if($is_user['0'] != 0) return 'user_exist';
	$passwd = genRandStr(8);
	// {"balance":500,"phone":"7495xxxx80","regDate":"1448450707","access":"1","lastIP":"127.0.0.1","apiKey":"ec374361f6e0d83147924890027c28e8"}
	$data = ['balance' => 0, 'phone' => NULL, 'regDate' => time(), 'access' => 1, 'lastIP' => NULL, 'apiKey' => genRandStr(32)];
	$json = json_encode($data);
	$query = $db->prepare("INSERT INTO `users` (`login`, `passwd`, `data`) VALUES (:login, :passwd, :data)");
	$query->bindParam(':login', $login, PDO::PARAM_STR);
	$query->bindParam(':passwd', rehash($passwd), PDO::PARAM_STR);
	$query->bindParam(':data', $json, PDO::PARAM_STR);
	$query->execute();
	return $passwd;
}

function lepus_log_ip($id, $ip){
	global $db; $info = get_browser(null, true);

	if(preg_match('/[^0-9A-Za-z.]/', $info['platform'])) $info['platform'] = "unknown";
	if(preg_match('/[^0-9A-Za-z.]/', $info['browser'])) $info['browser'] = "unknown";
	
	$query = $db->prepare("INSERT INTO `log_ip` (`uid`, `ip`, `platform`, `browser`, `time`) VALUES (:id, :ip, :platform, :browser, :time)");
	$query->bindParam(':id', $id, PDO::PARAM_STR);
	$query->bindParam(':ip', $ip, PDO::PARAM_STR);
	$query->bindParam(':platform', $info['platform'], PDO::PARAM_STR);
	$query->bindParam(':browser', $info['browser'], PDO::PARAM_STR);
	$query->bindParam(':time', time(), PDO::PARAM_STR);
	$query->execute();
}

function lepus_get_logip($id, $i = 0){
	global $db;
	$query = $db->prepare("SELECT * FROM `log_ip` WHERE `uid` = :id");
	$query->bindParam(':id', $id, PDO::PARAM_STR);
	$query->execute();
	if($query->rowCount() == 0) return "no_data";
	while($row = $query->fetch()){
		$i++; $data .= "<tr><td>$i</td><td>".long2ip($row['ip'])."</td><td><img src=\"/images/flags16/".mb_strtolower(geoip_country_code_by_name('136.243.79.123')).".png\" style=\"margin-bottom:-3px;\"> ".geoip_country_name_by_name('136.243.79.123')."</td><td>".$row['platform']."</td><td>".$row['browser']."</td><td>".date('Y-m-d H:i', $row['time'])."</td></tr>";
	}
	return $data;
}

function lepus_addDNSDomain($domain, $type, $master, $id){
	global $pdns;
	$query = $pdns->prepare("SELECT * FROM `domains` WHERE `name` = :domain");
	$query->bindParam(':domain', $domain, PDO::PARAM_STR);
	$query->execute();
	if($query->rowCount() != 0) return 'already_add';
	switch($type){
		default: die("Something wrong"); break;
		case 'master':
			$query = $pdns->prepare("INSERT INTO `domains` (`name`, `type`, `account`) VALUES ( :domain, 'MASTER', :uid)");
			$query->bindParam(':domain', $domain, PDO::PARAM_STR);
			$query->bindParam(':uid', $id, PDO::PARAM_STR);
			$query->execute();
		break;
		case 'slave':
			$query = $pdns->prepare("INSERT INTO `domains` (`name`, `master`, `type`, `account`) VALUES ( :domain, :master, 'SLAVE', :uid)");
			$query->bindParam(':domain', $domain, PDO::PARAM_STR);
			$query->bindParam(':master', $master, PDO::PARAM_STR);
			$query->bindParam(':uid', $id, PDO::PARAM_STR);
			$query->execute();
		break;
	}
	return $pdns->lastInsertId();
}

function lepus_get_dnsDomains($id, $i = 0){
	global $pdns;
	$query = $pdns->prepare("SELECT * FROM `domains` WHERE `account` = :uid");
	$query->bindParam(':uid', $id, PDO::PARAM_STR);
	$query->execute();
	while($row = $query->fetch()){
		if($row['type'] == 'MASTER') $row['master'] = '-';
		$i++; $data .= "<tr id=\"".$row['id']."\"> <td>$i</td> <td>".htmlspecialchars(idn_to_utf8($row['name']))."</td> <td>".$row['type']."</td> <td>".$row['master']."</td> <td><a href=\"/pages/edit-domain.php?id=".$row['id']."\"><i class=\"glyphicon glyphicon-pencil\"></i></a> &nbsp; <a href=\"nourl\" data-dns-delete-id=".$row['id']."><i class=\"glyphicon glyphicon-remove\"></i></a></td> </tr>";
	}
	return $data;
}

function lepus_get_dnsAccess($id, $uid, $slave = 'no_check'){
	global $pdns;
	$query = $pdns->prepare("SELECT * FROM `domains` WHERE `id` = :id AND `account` =:uid");
	$query->bindParam(':id', $id, PDO::PARAM_STR);
	$query->bindParam(':uid', $uid, PDO::PARAM_STR);
	$query->execute();
	if($query->rowCount() != 1) return 'deny';
	$row = $query->fetch();
	if($slave == 'check') return $row['type'];
	return htmlspecialchars(idn_to_utf8($row['name']));
}

function lepus_add_dnsRecord($zone, $type, $data, $prio, $domain_id){
	global $pdns;	
	$types = ['A', 'AAAA', 'CNAME', 'MX', 'NS', 'TXT', 'SRV', 'PTR', 'SOA'];
	if(!in_array($type, $types)) return "wrong type record";
	$query = $pdns->prepare("INSERT INTO `records` (`domain_id`, `name`, `type`, `content`, `ttl`, `prio`) VALUES (:id, :name, :type, :content, 3600, :prio)");
	$query->bindParam(':id', $domain_id, PDO::PARAM_STR);
	$query->bindParam(':name', $zone, PDO::PARAM_STR);
	$query->bindParam(':type', $type, PDO::PARAM_STR);
	$query->bindParam(':content', $data, PDO::PARAM_STR);
	$query->bindParam(':prio', $prio, PDO::PARAM_STR);
	$query->execute();
	return $pdns->lastInsertId();
}

function lepus_delete_dnsDomain($id){
	global $pdns;
	$query = $pdns->prepare("DELETE FROM `domains` WHERE `id` = :id");
	$query->bindParam(':id', $id, PDO::PARAM_STR);
	$query->execute();

	$query = $pdns->prepare("DELETE FROM `records` WHERE `domain_id` = :id");
	$query->bindParam(':id', $id, PDO::PARAM_STR);
	$query->execute();
}

function lepus_get_dnsRecordAccess($id, $uid){
	global $pdns;
	$query = $pdns->prepare("SELECT * FROM `records` WHERE `id` = :id");
	$query->bindParam(':id', $id, PDO::PARAM_STR);
	$query->execute();
	if($query->rowCount() != 1) return "no_record";
	$row = $query->fetch();
	$tmpData = lepus_get_dnsAccess($row['domain_id'], $uid, 'check');
	if($tmpData == 'deny') return 'deny';
	if($tmpData == 'SLAVE') return 'SLAVE';
	return 'ok';
}

function lepus_get_dnsRecord($type, $id){
	global $pdns;
	$query = $pdns->prepare("SELECT * FROM `records` WHERE `id` = :id");
	$query->bindParam(':id', $id, PDO::PARAM_STR);
	$query->execute();
	$row = $query->fetch();
	//return htmlspecialchars(idn_to_utf8($row[$type]));
	return idn_to_utf8($row[$type]);
}

function lepus_get_dnsRecords($id, $i = 0){
	global $pdns;
	$query = $pdns->prepare("SELECT * FROM `records` WHERE `domain_id` = :id");
	$query->bindParam(':id', $id, PDO::PARAM_STR);
	$query->execute();
	while($row = $query->fetch()){
		$i++; $data .= "<tr id=\"".$row['id']."\"><td>".$i."</td><td class=\"edit\" id=\"name_".$row['id']."\">".htmlspecialchars(idn_to_utf8($row['name']))."</td><td class=\"edit_type\" id=\"type_".$row['id']."\">".$row['type']."</td><td class=\"edit\" id=\"content_".$row['id']."\">".htmlspecialchars($row['content'])."</td><td class=\"edit\" id=\"prio_".$row['id']."\">".$row['prio']."</td><td><a href=\"nourl\" data-dns-zone-id=\"".$row['id']."\"><i class=\"glyphicon glyphicon-remove\"></i></a></td></tr>";
	}
	return $data;
}

function lepus_edit_dnsRecord($type, $id, $value){
	global $pdns;
	$tmpTest = lepus_dnsValid($type, $value);
	if($tmpTest != 'ok') return $tmpTest;
	if($type == 'type' && lepus_dnsValidType($value) != 'ok') return "wrong type record";	
	if($type == 'name') $value = idn_to_ascii(mb_strtolower($value));
	$query = $pdns->prepare("UPDATE `records` SET `$type` = :value WHERE `id` = :id");
	$query->bindParam(':value', $value, PDO::PARAM_STR);
	$query->bindParam(':id', $id, PDO::PARAM_STR);
	$query->execute();
	return htmlspecialchars(idn_to_utf8($value));
}

function lepus_delete_dnsRecord($id){
	global $pdns;
	$query = $pdns->prepare("DELETE FROM `records` WHERE `id` = :id");
	$query->bindParam(':id', $id, PDO::PARAM_STR);
	$query->execute();
}

function lepus_dnsValidType($value, $j = 'ok'){
	$types = ['A', 'AAAA', 'CNAME', 'MX', 'NS', 'TXT', 'SRV', 'PTR', 'SOA'];
	if(!in_array($value, $types)) $j = "wrong type record";
	return $j;
}

function lepus_dnsValid($type, $value, $j = 'ok'){
	$i = ['prio' => 3, 'type' => 6, 'master' => 128, 'name' => 255, 'content' => 4096];
	if(isset($i[$type])){
		if(strlen($value) > $i[$type]) $j = "max $type strlen max $i[$type]";
	}else{
		$j = 'no_exist';
	}
	if(strlen($value) == 0) $j = 'empty value';
	if($type == 'master' && !filter_var($value, FILTER_VALIDATE_IP)) $j = 'Wrong master IP';
	if($type == 'prio' && !ctype_digit($value)) $j = 'prio only number';
	return $j;
}

function lepus_get_supportList($uid, $access){
	global $db;
	function tiket_status($id){
		$arr = [1 => 'Открыт', 2 => 'Закрыт'];
		return $arr[$id];
	}
	function tiket_label($id){
		$arr = [1 => 'success', 2 => 'warning', 3 => 'danger'];
		return $arr[$id];
	}
	if($access >= 2){
		$query = $db->prepare("SELECT * FROM `support`");
	}else{
		$query = $db->prepare("SELECT * FROM `support` WHERE `uid` = :uid");
		$query->bindParam(':uid', $uid, PDO::PARAM_STR);
	}
	$query->execute();
	while($row = $query->fetch()){
		if(!empty($row['open'])) $row['open'] = date("Y-m-d H:i", $row['open']); else $row['open'] = '-';
		if(!empty($row['last'])) $row['last'] = date("Y-m-d H:i", $row['last']); else $row['last'] = '-';
		$data .= "<tr><td><a href=\"/pages/tiket.php?id={$row['id']}\" title=\"Открыть\">#".$row['id']."</a></td><td>".$row['title']."</td><td>".$row['open']."</td><td>".$row['last']."</td><td style=\"padding-top: 11px;\"><span class=\"label label-pill label-".tiket_label($row['status'])." myLabel\">".tiket_status($row['status'])."</span></td></tr>";
	}
	return $data;
}

function support_create($uid){
	global $db;
	if(empty(trim($_POST['title'])) || empty(trim($_POST['msg']))) return('empty_post_value');
	$title = filter_var($_POST["title"], FILTER_SANITIZE_STRING);
	$msg = nl2br(htmlentities($_POST["msg"], ENT_QUOTES, 'UTF-8'));
	$query = $db->prepare("INSERT INTO `support` (`uid`, `title`, `open`, `status`) VALUES (:uid, :title, :open, 1)");
	$query->bindParam(':uid', $uid, PDO::PARAM_STR);
	$query->bindParam(':title', $title, PDO::PARAM_STR);
	$query->bindParam(':open', time(), PDO::PARAM_STR);
	$query->execute();
	return $db->lastInsertId();
}

function lepus_get_supportMsg($tid, $uid, $access, $msgID = 0, $update = 0, $data = '', $j = 0){
	global $db;
	$query = $db->prepare("SELECT * FROM `support` WHERE `id` = :id");
	$query->bindParam(':id', $tid, PDO::PARAM_STR);
	$query->execute();
	$row = $query->fetch();
	if($row['uid'] != $uid && $access < 2) return 'no_access';
	if($msgID == 0){
		if($update != 0){
			$query = $db->prepare("SELECT * FROM `support_msg` WHERE `tid` = :tid ORDER BY `time` ASC");
		}else{
			$query = $db->prepare("SELECT * FROM `support_msg` WHERE `tid` = :tid ORDER BY `time` DESC");
		}
		$query->bindParam(':tid', $tid, PDO::PARAM_STR);
	}else{
		$query = $db->prepare("SELECT * FROM `support_msg` WHERE `id` = :id AND `tid` = :tid");
		$query->bindParam(':id', $msgID, PDO::PARAM_STR);
		$query->bindParam(':tid', $tid, PDO::PARAM_STR);
	}
	$query->execute();
	$countMSG = $query->rowCount();
	if($update != 0 && $countMSG <= $update ) return 'no_new_msg';
	while($msg = $query->fetch()){
		$j++;
		if($update != 0 && $update+1 > $j) continue;
		$tmpQuery = $db->prepare("SELECT * FROM `users` WHERE `id` =:id");
		$tmpQuery->bindParam(':id', $msg['uid'], PDO::PARAM_STR);
		$tmpQuery->execute();
		$tmpRow = $tmpQuery->fetch();
		$tmpData = json_decode($tmpRow['data'], true);
		if($tmpData['access'] > 1){
			$panel = 'panel-danger';
			$who = "Ответ службы поддержи";
		}else{
			$panel = 'panel-info';
			$who = "Пользователь написал ({$tmpRow['login']})";
		}
		$msg['time'] = date("Y-m-d H:i", $msg['time']);
		$data .= "<div class=\"panel $panel panelbg\"><div class=\"panel-heading\"><span class=\"label label-pill label-default myColor myLabel\">{$msg['time']}</span><font color=\"black\"> $who</font></div><div class=\"panel-body\">{$msg['msg']}</div></div>";
		if($update != 0 && strlen($data) > 10) break 1;
	}
	return ['title' => $row['title'], 'msg' => $data, 'countMSG' => $countMSG];
}

function support_msg($uid, $tid, $access){
	global $db;
	if($access > 1 && $_POST['msg'] != 'END' && $_POST['msg'] != 'OPEN') $_POST['msg'] .= "\n\n\n[i]С уважением, команда технической поддержки.[/i]";
	$msg = parse_bb_code(nl2br(htmlentities($_POST['msg'], ENT_QUOTES, 'UTF-8')));
	if($msg == 'END') $msg = '<span class="label label-pill label-danger myLabel">Тикет закрыт</span>';
	if($msg == 'OPEN') $msg = '<span class="label label-pill label-success myLabel">Тикет открыт</span>';

	$query = $db->prepare("INSERT INTO `support_msg` (`tid`, `msg`, `uid`, `time`) VALUES (:tid, :msg, :uid, :time)");
	$query->bindParam(':tid', $tid, PDO::PARAM_STR);
	$query->bindParam(':msg', $msg, PDO::PARAM_STR);
	$query->bindParam(':uid', $uid, PDO::PARAM_STR);
	$query->bindParam(':time', time(), PDO::PARAM_STR);
	$query->execute();
	return ['tid' => $tid, 'msgID' => $db->lastInsertId()];
}

function parse_bb_code($text){
	$text = preg_replace('/\[(\/?)(b|i|u|s)\s*\]/', "<$1$2>", $text);
	$text = preg_replace('/\[url\](?:http:\/\/)?(.+)\[\/url\]/', "<a href=\"http://$1\" target=\"_blank\">$1</a>", $text);
	$text = preg_replace('/\[url\s?=\s?([\'"]?)(?:http:\/\/)?(.+)\1\](.*?)\[\/url\]/', "<a href=\"http://$2\" target=\"_blank\">$3</a>", $text);
	$text = preg_replace('/\[urls\](?:https:\/\/)?(.+)\[\/urls\]/', "<a href=\"https://$1\" target=\"_blank\">$1</a>", $text);
	$text = preg_replace('/\[urls\s?=\s?([\'"]?)(?:https:\/\/)?(.+)\1\](.*?)\[\/urls\]/', "<a href=\"https://$2\" target=\"_blank\">$3</a>", $text);
	return $text;
}

