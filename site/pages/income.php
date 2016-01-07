<?php
require_once($_SERVER['DOCUMENT_ROOT'].'/private/config.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/private/init/mysql.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/private/init/memcache.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/private/init/session.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/private/func.main.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/private/auth.php');
if(empty($user)){
	$tmpData = error('no_auth_page');
	die(lepus_error_page($tmpData['mes']));
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
	<head>
		<title>Lepus - интернет хостинг</title>
		<meta name="description" content="Виртуальный хостинг, быстрые VPS, выделенные серверы по привлекательной цене." />
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<meta name="yandex-verification" content="6940b644b3235f76" />
		<link rel="shortcut icon" type="image/x-icon" href="/favicon.ico" />
		<link rel="stylesheet" type="text/css" href="/css/bootstrap.min.css">
		<link rel="stylesheet" type="text/css" href="/css/font-awesome.min.css">
		<link rel="stylesheet" type="text/css" href="/css/reset.css"/>
		<link rel="stylesheet" type="text/css" href="/css/style.css"/>
		<link rel="stylesheet" type="text/css" href="/css/alertify.core.css" />
		<link rel="stylesheet" type="text/css" href="/css/alertify.bootstrap.css" />
		<link rel="stylesheet" href="//cdn.datatables.net/plug-ins/1.10.10/integration/bootstrap/3/dataTables.bootstrap.css">
		<style> td,th { text-align: center; vertical-align: middle; } </style>
		<script src="/js/jquery.min.js"></script>
		<script src="//cdn.datatables.net/1.10.10/js/jquery.dataTables.min.js"></script>
		<script src="//cdn.datatables.net/plug-ins/1.10.10/integration/bootstrap/3/dataTables.bootstrap.js"></script>
		<script src="/js/bootstrap.min.js"></script>

		<script src="/js/alertify.js"></script>
		<script src="/js/lepus.js"></script>
	<script type="text/javascript" charset="utf-8"> $(document).ready(function() { $('#log_ip').dataTable({ "order": [[ 0, "desc" ]] }); }); </script>
	</head>
	<body>
		<div class="wrapper">
			<?php require_once($_SERVER['DOCUMENT_ROOT'].'/private/pages/menu.php'); ?>
			<div class="logo"></div>
			<div class="information">
			<?php require_once($_SERVER['DOCUMENT_ROOT'].'/private/pages/news.php'); ?>
			</div>
			<div class="container">
				<div class="content-box">
					<div class="content-info">
						<div class="content-text">
							<div class="page-title">ывыва</div>
							<div class="row">					
								<div class="col-lg-14">
									<div class="col-lg-4">
										<input class="form-control" type="password" value="" id="real_passwd" required="" placeholder="Старый пароль">
										<input data-cp-change-passwd class="btn btn-sm btn-danger btn-block" style="margin-top: 2px;" type="submit" value="Получить новый пароль">
									</div>
									<div class="col-lg-4">
										<input class="form-control" type="text" value="" id="new_phone"  required="" placeholder="+<?php echo substr_replace($user['data']['phone'], 'XXXXX', 4, -2); ?>">
										<input data-cp-change-phone class="btn btn-sm btn-danger btn-block" style="margin-top: 2px;" type="submit" value="Изменить номер">
									</div>
									<div class="col-lg-4">
										<div class="form-inline">
											<input class="form-control" style="width: 98px;" type="text" name="count" value="" maxlength="5" required="" placeholder="Сумма">
												<select class="form-control" name="type">
													<option value="master" selected="">Paymaster</option>
													<option value="paypal">PayPal</option>
													<option value="paypal">Bitcoin</option>
												</select>
											<input class="btn btn-sm btn-danger btn-block" style="margin-top: 2px;" type="submit" value="Пополнить счет">
										</div>
									</div>
									<div class="col-lg-12">
										<hr/>
										
			<table id="log_ip" class="table table-striped table-bordered" cellspacing="0" width="100%">
				<thead>
					<tr>
						<th>ID</th>
						<th>IP</th>
						<th>Страна</th>
						<th>Система</th>
						<th>Браузер</th>
						<th>Время</th>
					</tr>
				</thead>
				<tbody>
				<?php echo lepus_get_logip($user['id']); ?>
				</tbody>
			</table>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
				<?php require_once($_SERVER['DOCUMENT_ROOT'].'/private/pages/navi.php'); ?>
			</div>
		</div>
		<?php require_once($_SERVER['DOCUMENT_ROOT'].'/private/pages/footer.php'); ?>
		<?php require_once($_SERVER['DOCUMENT_ROOT'].'/private/pages/modal.php'); ?>
		<script src="//www.google.com/recaptcha/api.js?onload=onloadCallback&render=explicit" async defer></script>
	</body>
</html>