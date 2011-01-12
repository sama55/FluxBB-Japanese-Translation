<?php

/**
 * Copyright (C) 2008-2010 FluxBB
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// The FluxBB version this script installs
define('FORUM_VERSION', '1.4.2');

define('FORUM_DB_REVISION', 8);
define('FORUM_SI_REVISION', 1);
define('FORUM_PARSER_REVISION', 1);

define('MIN_PHP_VERSION', '4.3.0');
define('MIN_MYSQL_VERSION', '4.1.2');
define('MIN_PGSQL_VERSION', '7.0.0');
define('PUN_SEARCH_MIN_WORD', 3);
define('PUN_SEARCH_MAX_WORD', 20);


define('PUN_ROOT', './');

if (file_exists(PUN_ROOT.'config.php'))
{
	// Check to see whether FluxBB is already installed
	include PUN_ROOT.'config.php';

	// If we have the 1.3-legacy constant defined, define the proper 1.4 constant so we don't get an incorrect "need to install" message
	if (defined('FORUM'))
		define('PUN', FORUM);

	// If PUN is defined, config.php is probably valid and thus the software is installed
	if (defined('PUN'))
		exit('FluxBBはインストールされています。<a href="index.php">こちら</a> からアクセスしてください。');
}

// Define PUN because email.php requires it
define('PUN', 1);

// Make sure we are running at least MIN_PHP_VERSION
if (!function_exists('version_compare') || version_compare(PHP_VERSION, MIN_PHP_VERSION, '<'))
	exit('このサーバーでは PHP V'.PHP_VERSION.'が動作しています。FluxBB '.FORUM_VERSION.'が正しく動作するためには、PHP V'.MIN_PHP_VERSION.'以上の環境が必要です。インストールを続行する前にPHPをアップグレードしてください。');

// Load the functions script
require PUN_ROOT.'include/functions.php';

// Load UTF-8 functions
require PUN_ROOT.'include/utf8/utf8.php';

// Strip out "bad" UTF-8 characters
forum_remove_bad_characters();

// Reverse the effect of register_globals
forum_unregister_globals();

// Disable error reporting for uninitialized variables
error_reporting(E_ALL);

// Force POSIX locale (to prevent functions such as strtolower() from messing up UTF-8 strings)
setlocale(LC_CTYPE, 'C');

// Turn off magic_quotes_runtime
if (get_magic_quotes_runtime())
	set_magic_quotes_runtime(0);

// Strip slashes from GET/POST/COOKIE (if magic_quotes_gpc is enabled)
if (get_magic_quotes_gpc())
{
	function stripslashes_array($array)
	{
		return is_array($array) ? array_map('stripslashes_array', $array) : stripslashes($array);
	}

	$_GET = stripslashes_array($_GET);
	$_POST = stripslashes_array($_POST);
	$_COOKIE = stripslashes_array($_COOKIE);
	$_REQUEST = stripslashes_array($_REQUEST);
}

// Turn off PHP time limit
@set_time_limit(0);

//
// Generate output to be used for config.php
//
function generate_config_file()
{
	global $db_type, $db_host, $db_name, $db_username, $db_password1, $db_prefix, $cookie_name, $cookie_seed;

	return '<?php'."\n\n".'$db_type = \''.$db_type."';\n".'$db_host = \''.$db_host."';\n".'$db_name = \''.addslashes($db_name)."';\n".'$db_username = \''.addslashes($db_username)."';\n".'$db_password = \''.addslashes($db_password1)."';\n".'$db_prefix = \''.addslashes($db_prefix)."';\n".'$p_connect = false;'."\n\n".'$cookie_name = '."'".$cookie_name."';\n".'$cookie_domain = '."'';\n".'$cookie_path = '."'/';\n".'$cookie_secure = 0;'."\n".'$cookie_seed = \''.random_key(16, false, true)."';\n\ndefine('PUN', 1);\n";
}


if (isset($_POST['generate_config']))
{
	header('Content-Type: text/x-delimtext; name="config.php"');
	header('Content-disposition: attachment; filename=config.php');

	$db_type = $_POST['db_type'];
	$db_host = $_POST['db_host'];
	$db_name = $_POST['db_name'];
	$db_username = $_POST['db_username'];
	$db_password1 = $_POST['db_password1'];
	$db_prefix = $_POST['db_prefix'];
	$cookie_name = $_POST['cookie_name'];
	$cookie_seed = $_POST['cookie_seed'];

	echo generate_config_file();
	exit;
}


if (!isset($_POST['form_sent']))
{
	// Make an educated guess regarding base_url
	$base_url  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 'https://' : 'http://';	// protocol
	$base_url .= preg_replace('/:(80|443)$/', '', $_SERVER['HTTP_HOST']);							// host[:port]
	$base_url .= str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));							// path

	if (substr($base_url, -1) == '/')
		$base_url = substr($base_url, 0, -1);

	$db_type = $db_name = $db_username = $db_prefix = $username = $email = '';
	$db_host = 'localhost';
	$title = 'FluxBB フォーラム';
	$description = '<p><span>FluxBB フォーラムにようこそ！</span></p>';
	$default_lang = 'Japanese';
	$default_style = 'Air';
}
else
{
	$db_type = $_POST['req_db_type'];
	$db_host = pun_trim($_POST['req_db_host']);
	$db_name = pun_trim($_POST['req_db_name']);
	$db_username = pun_trim($_POST['db_username']);
	$db_password1 = pun_trim($_POST['db_password1']);
	$db_password2 = pun_trim($_POST['db_password2']);
	$db_prefix = pun_trim($_POST['db_prefix']);
	$username = pun_trim($_POST['req_username']);
	$email = strtolower(pun_trim($_POST['req_email']));
	$password1 = pun_trim($_POST['req_password1']);
	$password2 = pun_trim($_POST['req_password2']);
	$title = pun_trim($_POST['req_title']);
	$description = pun_trim($_POST['desc']);
	$base_url = pun_trim($_POST['req_base_url']);
	$default_lang = pun_trim($_POST['req_default_lang']);
	$default_style = pun_trim($_POST['req_default_style']);
	$alerts = array();

	// Make sure base_url doesn't end with a slash
	if (substr($base_url, -1) == '/')
		$base_url = substr($base_url, 0, -1);

	// Validate database password
	if ($db_password1 != $db_password2)
		$alerts[] = 'データベースのパスワードが一致しません。';

	// Validate username and passwords
	if (pun_strlen($username) < 2)
		$alerts[] = 'ユーザー名は2文字以上入力してください。';
	else if (pun_strlen($username) > 25) // This usually doesn't happen since the form element only accepts 25 characters
		$alerts[] = 'ユーザー名は25文字未満で入力してください。';
	else if (!strcasecmp($username, 'Guest'))
		$alerts[] = 'guestは指定できません。';
	else if (preg_match('/[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}/', $username) || preg_match('/((([0-9A-Fa-f]{1,4}:){7}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){6}:[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){5}:([0-9A-Fa-f]{1,4}:)?[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){4}:([0-9A-Fa-f]{1,4}:){0,2}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){3}:([0-9A-Fa-f]{1,4}:){0,3}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){2}:([0-9A-Fa-f]{1,4}:){0,4}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){6}((\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b)\.){3}(\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b))|(([0-9A-Fa-f]{1,4}:){0,5}:((\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b)\.){3}(\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b))|(::([0-9A-Fa-f]{1,4}:){0,5}((\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b)\.){3}(\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b))|([0-9A-Fa-f]{1,4}::([0-9A-Fa-f]{1,4}:){0,5}[0-9A-Fa-f]{1,4})|(::([0-9A-Fa-f]{1,4}:){0,6}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){1,7}:))/', $username))
		$alerts[] = 'ユーザー名にIPアドレスは指定できません。';
	else if ((strpos($username, '[') !== false || strpos($username, ']') !== false) && strpos($username, '\'') !== false && strpos($username, '"') !== false)
		$alerts[] = 'ユーザー名に \', " そして [ や ] は指定できません。';
	else if (preg_match('/(?:\[\/?(?:b|u|i|h|colou?r|quote|code|img|url|email|list)\]|\[(?:code|quote|list)=)/i', $username))
		$alerts[] = 'ユーザー名 BBCode は使えません。';

	if (pun_strlen($password1) < 4)
		$alerts[] = 'パスワードは4文字以上入力してください。';
	else if ($password1 != $password2)
		$alerts[] = 'パスワードが一致しません。';

	// Validate email
	require PUN_ROOT.'include/email.php';

	if (!is_valid_email($email))
		$alerts[] = '管理者のメールアドレスが正しくありません。';

	if ($title == '')
		$alerts[] = 'フォーラムのタイトルを入力してください.';

	$default_lang = preg_replace('#[\.\\\/]#', '', $default_lang);
	if (!file_exists(PUN_ROOT.'lang/'.$default_lang.'/common.php'))
		$alerts[] = 'デフォルト言語が存在しません。';

	$default_style = preg_replace('#[\.\\\/]#', '', $default_style);
	if (!file_exists(PUN_ROOT.'style/'.$default_style.'.css'))
		$alerts[] = 'デフォルトスタイルが存在しません。';
}

if (!isset($_POST['form_sent']) || !empty($alerts))
{
	// Determine available database extensions
	$dual_mysql = false;
	$db_extensions = array();
	$mysql_innodb = false;
	if (function_exists('mysqli_connect'))
	{
		$db_extensions[] = array('mysqli', 'MySQL Improved');
		$db_extensions[] = array('mysqli_innodb', 'MySQL Improved (InnoDB)');
		$mysql_innodb = true;
	}
	if (function_exists('mysql_connect'))
	{
		$db_extensions[] = array('mysql', 'MySQL Standard');
		$db_extensions[] = array('mysql_innodb', 'MySQL Standard (InnoDB)');
		$mysql_innodb = true;

		if (count($db_extensions) > 2)
			$dual_mysql = true;
	}
	if (function_exists('sqlite_open'))
		$db_extensions[] = array('sqlite', 'SQLite');
	if (function_exists('pg_connect'))
		$db_extensions[] = array('pgsql', 'PostgreSQL');

	if (empty($db_extensions))
		exit('このサーバーの PHP は FluxBB がサポートしているいずれのデータベースもサポートしていません。PHP が、MySQL/PostgreSQL/SQLiteのいずれかをサポートしている必要があります。');

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">

<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>FluxBB インストール</title>
<link rel="stylesheet" type="text/css" href="style/<?php echo $default_style ?>.css" />
<script type="text/javascript">
function process_form(the_form)
{
	var element_names = new Object()
	element_names["req_db_type"] = "Database type"
	element_names["req_db_host"] = "Database server hostname"
	element_names["req_db_name"] = "Database name"
	element_names["db_prefix"] = "Table prefix"
	element_names["req_username"] = "Administrator username"
	element_names["req_password1"] = "Administrator password 1"
	element_names["req_password2"] = "Administrator password 2"
	element_names["req_email"] = "Administrator's email"
	element_names["req_title"] = "Board title"
	element_names["req_base_url"] = "Base URL"

	if (document.all || document.getElementById)
	{
		for (var i = 0; i < the_form.length; ++i)
		{
			var elem = the_form.elements[i]
			if (elem.name && elem.name.substring(0, 4) == "req_")
			{
				if (elem.type && (elem.type=="text" || elem.type=="textarea" || elem.type=="password" || elem.type=="file") && elem.value=='')
				{
					alert("\"" + element_names[elem.name] + "\" を指定してください。")
					elem.focus()
					return false
				}
			}
		}
	}

	return true
}
</script>
</head>
<body onload="document.getElementById('install').req_db_type.focus();document.getElementById('install').start.disabled=false;">

<div id="puninstall" class="pun">
<div class="top-box"><div><!-- Top Corners --></div></div>
<div class="punwrap">

<div id="brdheader" class="block">
	<div class="box">
		<div id="brdtitle" class="inbox">
			<h1><span>FluxBB インストール</span></h1>
			<div id="brddesc"><p>FluxBB インストーラーにようこそ。下のフォームに必要事項を入力してください。不明点はインストールドキュメントを参照してください。</p></div>
		</div>
	</div>
</div>

<div id="brdmain">
<div class="blockform">
	<h2><span>FluxBB 1.4 をインストール</span></h2>
	<div class="box">
		<form id="install" method="post" action="install.php" onsubmit="this.start.disabled=true;if(process_form(this)){return true;}else{this.start.disabled=false;return false;}">
		<div><input type="hidden" name="form_sent" value="1" /></div>
			<div class="inform">
<?php if (!empty($alerts)): ?>				<div class="forminfo error-info">
					<h3>下のエラーを修正してください。</h3>
					<ul class="error-list">
<?php

foreach ($alerts as $cur_alert)
	echo "\t\t\t\t\t\t".'<li><strong>'.$cur_alert.'</strong></li>'."\n";
?>
					</ul>
				</div>
<?php endif; ?>			</div>
			<div class="inform">
				<div class="forminfo">
					<h3>データベースの設定</h3>
					<p>FluxBB をセットアップするデータベースの情報を入力します。インストールを続けるにはすべての情報を入力する必要があります。</p>
				</div>
				<fieldset>
				<legend>データベースタイプ</legend>
					<div class="infldset">
						<p>FluxBB は現在 MySQL, PostgreSQL, SQLite をサポートしています。あなたのデータベースが下のドロップダウンメニューにない場合は、PHPがそのデータベースをサポートしていないことを意味します。各データベースのバージョンごとの詳しい情報は FAQ を参照してください。</p>
<?php if ($dual_mysql): ?>						<p>MySQL には standard と improved の２つの方法で接続できます。よく分からない場合は、先に improved を試してください。</p>
<?php endif; ?><?php if ($mysql_innodb): ?>						<p>MySQL が <a href="http://dev.mysql.com/doc/refman/5.0/en/innodb.html">InnoDB</a> をサポートしているかもしれません。大きなフォーラムを運営する場合 InnoDB は良い選択肢ですが、よく分からない場合は選択しないことをお勧めします。</p>
<?php endif; ?>						<label class="required"><strong>データベースタイプ <span>(Required)</span></strong>
						<br /><select name="req_db_type">
<?php

	foreach ($db_extensions as $temp)
	{
		if ($temp[0] == $db_type)
			echo "\t\t\t\t\t\t\t".'<option value="'.$temp[0].'" selected="selected">'.$temp[1].'</option>'."\n";
		else
			echo "\t\t\t\t\t\t\t".'<option value="'.$temp[0].'">'.$temp[1].'</option>'."\n";
	}

?>
						</select>
						<br /></label>
					</div>
				</fieldset>
			</div>
			<div class="inform">
				<fieldset>
					<legend>データベースホスト</legend>
					<div class="infldset">
						<p>データベースサーバーのアドレス（例： localhost, db.myhost.com や 192.168.0.15）を入力します。デフォルトのポート番号で動かない場合は、ポート番号も指定できます（例： localhost:3580）。SQLite の場合は、任意の文字列か 'localhost' のままにします。</p>
						<label class="required"><strong>データベースホスト <span>(Required)</span></strong><br /><input type="text" name="req_db_host" value="<?php echo pun_htmlspecialchars($db_host) ?>" size="50" maxlength="100" /><br /></label>
					</div>
				</fieldset>
			</div>
			<div class="inform">
				<fieldset>
					<legend>データベース名</legend>
					<div class="infldset">
						<p>FluxBB をインストールするデータベース名を入力します。データベースは既存のものを指定します。SQLite の場合は、データベースファイルへの相対パスを入力します。 ファイルがない場合は新規に作成されます。</p>
						<label class="required"><strong>データベース名 <span>(Required)</span></strong><br /><input id="req_db_name" type="text" name="req_db_name" value="<?php echo pun_htmlspecialchars($db_name) ?>" size="30" maxlength="50" /><br /></label>
					</div>
				</fieldset>
			</div>
			<div class="inform">
				<fieldset>
					<legend>ユーザー名とパスワード</legend>
					<div class="infldset">
						<p>データベースに接続するユーザー名とパスワードを入力します。SQLite では不要です。</p>
						<label class="conl">ユーザー名<br /><input type="text" name="db_username" value="<?php echo pun_htmlspecialchars($db_username) ?>" size="30" maxlength="50" /><br /></label>
						<label class="conl">パスワード<br /><input type="password" name="db_password1" size="30" maxlength="50" /><br /></label>
						<label class="conl">パスワード（確認）<br /><input type="password" name="db_password2" size="30" maxlength="50" /><br /></label>
						<div class="clearer"></div>
					</div>
				</fieldset>
			</div>
			<div class="inform">
				<fieldset>
					<legend>プレフィックス</legend>
					<div class="infldset">
						<p>テーブル名の先頭に任意の文字列（例： foo_）を付加できます。一つのデータベースに複数のシステムをインストールする場合に使用します。</p>
						<label>プレフィックス<br /><input id="db_prefix" type="text" name="db_prefix" value="<?php echo pun_htmlspecialchars($db_prefix) ?>" size="20" maxlength="30" /><br /></label>
					</div>
				</fieldset>
			</div>
			<div class="inform">
				<div class="forminfo">
					<h3>管理者の設定</h3>
					<p>FluxBB の管理者情報を入力します。</p>
				</div>
				<fieldset>
					<legend>ユーザー名</legend>
					<div class="infldset">
						<p>管理者のユーザー名を入力します。管理者やモデレータは後で追加できます。2～25文字の範囲で入力します。</p>
						<label class="required"><strong>ユーザー名 <span>(Required)</span></strong><br /><input type="text" name="req_username" value="<?php echo pun_htmlspecialchars($username) ?>" size="25" maxlength="25" /><br /></label>
					</div>
				</fieldset>
			</div>
			<div class="inform">
				<fieldset>
					<legend>パスワード</legend>
					<div class="infldset">
					<p>管理者のパスワードを大文字と小文字を意識して4文字以上入力します。</p>
						<label class="conl required"><strong>パスワード <span>(Required)</span></strong><br /><input id="req_password1" type="password" name="req_password1" size="16" /><br /></label>
						<label class="conl required"><strong>パスワード（確認） <span>(Required)</span></strong><br /><input type="password" name="req_password2" size="16" /><br /></label>
						<div class="clearer"></div>
					</div>
				</fieldset>
			</div>
			<div class="inform">
				<fieldset>
					<legend>メールアドレス</legend>
					<div class="infldset">
						<p>管理者のメールアドレスを入力します。</p>
						<label class="required"><strong>メールアドレス <span>(Required)</span></strong><br /><input id="req_email" type="text" name="req_email" value="<?php echo pun_htmlspecialchars($email) ?>" size="50" maxlength="80" /><br /></label>
					</div>
				</fieldset>
			</div>
			<div class="inform">
				<div class="forminfo">
					<h3>フォーラムの設定</h3>
					<p>FluxBB フォーラムの基本情報を入力します。</p>
				</div>
				<fieldset>
					<legend>タイトル</legend>
					<div class="infldset">
						<p>フォーラムのタイトルを入力します。すべてのページの先頭に表示されます。</p>
						<label class="required"><strong>タイトル <span>(Required)</span></strong><br /><input id="req_title" type="text" name="req_title" value="<?php echo pun_htmlspecialchars($title) ?>" size="60" maxlength="255" /><br /></label>
					</div>
				</fieldset>
			</div>
			<div class="inform">
				<fieldset>
					<legend>説明</legend>
					<div class="infldset">
						<p>フォーラムの説明を簡潔に入力します。すべてのページの先頭に表示されます。（HTML可）</p>
						<label><strong>説明</strong><br /><input id="desc" type="text" name="desc" value="<?php echo pun_htmlspecialchars($description) ?>" size="60" maxlength="255" /><br /></label>
					</div>
				</fieldset>
			</div>
			<div class="inform">
				<fieldset>
					<legend>ベースアドレス</legend>
					<div class="infldset">
						<p><strong>最後のスラッシュを除いた</strong>フォーラムの URL を入力します（例： http://forum.myhost.com や http://myhost.com/~myuser）。<strong>ここは厳密に入力してください。</strong>設定を間違えるとすべての管理操作が失敗します。下はシステムが推理したアドレスで必ずしも正しいとは限りません。</p>
						<label class="required"><strong>ベースアドレス <span>(Required)</span></strong><br /><input id="req_base_url" type="text" name="req_base_url" value="<?php echo pun_htmlspecialchars($base_url) ?>" size="60" maxlength="100" /><br /></label>
					</div>
				</fieldset>
			</div>
			<div class="inform">
				<fieldset>
					<legend>デフォルト言語</legend>
					<div class="infldset">
						<p>この値はゲストと登録ユーザーの初期値として適用されます。</p>
						<label class="required"><strong>デフォルト言語 <span>(Required)</span></strong><br /><select id="req_default_lang" name="req_default_lang">
<?php

		$languages = forum_list_langs();

		foreach ($languages as $temp)
		{
			if ($temp == $default_lang)
				echo "\t\t\t\t\t\t\t\t\t\t\t".'<option value="'.$temp.'" selected="selected">'.$temp.'</option>'."\n";
			else
				echo "\t\t\t\t\t\t\t\t\t\t\t".'<option value="'.$temp.'">'.$temp.'</option>'."\n";
		}

?>
						</select><br /></label>
					</div>
				</fieldset>
			</div>
			<div class="inform">
				<fieldset>
					<legend>デフォルトスタイル</legend>
					<div class="infldset">
						<p>この値はゲストと登録ユーザーの初期値として適用されます。</p>
						<label class="required"><strong>デフォルトスタイル <span>(Required)</span></strong><br /><select id="req_default_style" name="req_default_style">
<?php

		$styles = forum_list_styles();

		foreach ($styles as $temp)
		{
			if ($temp == $default_style)
				echo "\t\t\t\t\t\t\t\t\t".'<option value="'.$temp.'" selected="selected">'.str_replace('_', ' ', $temp).'</option>'."\n";
			else
				echo "\t\t\t\t\t\t\t\t\t".'<option value="'.$temp.'">'.str_replace('_', ' ', $temp).'</option>'."\n";
		}

?>
						</select><br /></label>
					</div>
				</fieldset>
			</div>
			<p class="buttons"><input type="submit" name="start" value="インストール" /></p>
		</form>
	</div>
</div>
</div>

</div>
<div class="end-box"><div><!-- Bottom Corners --></div></div>
</div>

</body>
</html>
<?php

}
else
{
	// Load the appropriate DB layer class
	switch ($db_type)
	{
		case 'mysql':
			require PUN_ROOT.'include/dblayer/mysql.php';
			break;

		case 'mysql_innodb':
			require PUN_ROOT.'include/dblayer/mysql_innodb.php';
			break;

		case 'mysqli':
			require PUN_ROOT.'include/dblayer/mysqli.php';
			break;

		case 'mysqli_innodb':
			require PUN_ROOT.'include/dblayer/mysqli_innodb.php';
			break;

		case 'pgsql':
			require PUN_ROOT.'include/dblayer/pgsql.php';
			break;

		case 'sqlite':
			require PUN_ROOT.'include/dblayer/sqlite.php';
			break;

		default:
			error('\''.pun_htmlspecialchars($db_type).'\' is not a valid database type');
	}

	// Create the database object (and connect/select db)
	$db = new DBLayer($db_host, $db_username, $db_password1, $db_name, $db_prefix, false);

	// Validate prefix
	if (strlen($db_prefix) > 0 && (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $db_prefix) || strlen($db_prefix) > 40))
		error('テーブルプレフィックス \''.$db->prefix.'\' に不正な文字が含まれているか長すぎます。プレフィックスにはa～z、数字、アンダースコアを入力します。最初に数字を指定することはできません。最大文字数は40文字です。他のプレフィックスを指定してください。');
	
	// Do some DB type specific checks
	switch ($db_type)
	{
		case 'mysql':
		case 'mysqli':
		case 'mysql_innodb':
		case 'mysqli_innodb':
			$mysql_info = $db->get_version();
			if (version_compare($mysql_info['version'], MIN_MYSQL_VERSION, '<'))
				error('MySQL V'.$mysql_version.'　が動作しています。FluxBB V'.FORUM_VERSION.' が正しく動くためには MySQL V'.MIN_MYSQL_VERSION.' 以上が必要です。インストールを続行する前に MySQL をアップグレードしてください。');
			break;

		case 'pgsql':
			$pgsql_info = $db->get_version();
			if (version_compare($pgsql_info['version'], MIN_PGSQL_VERSION, '<'))
				error('PostgreSQL V'.$pgsql_info.'が動作しています。FluxBB V'.FORUM_VERSION.' が正しく動くためには PostgreSQL V'.MIN_PGSQL_VERSION.' 以上が必要です。インストールを続行する前に PostgreSQL をアップグレードしてください。');
			break;

		case 'sqlite':
			if (strtolower($db_prefix) == 'sqlite_')
				error('SQLite ではテーブルプレフィックスに \'sqlite_\' は使えません。別なプレフィックスを指定してください。');
			break;
	}


	// Make sure FluxBB isn't already installed
	$result = $db->query('SELECT 1 FROM '.$db_prefix.'users WHERE id=1');
	if ($db->num_rows($result))
		error('"'.$db_prefix.'users" テーブルがデータベース "'.$db_name.'" に存在します。FluxBB がインストール済みか別なシステムのテーブルと競合している可能性があります。同じデータベース内に FluxBB を複数インストールする場合はプレフィックスを変更してください。');

	// Check if InnoDB is available
	if ($db_type == 'mysql_innodb' || $db_type == 'mysqli_innodb')
	{
		$result = $db->query('SHOW VARIABLES LIKE \'have_innodb\'');
		list (, $result) = $db->fetch_row($result);
		if ((strtoupper($result) != 'YES'))
			error('InnoDB が無効なようです。別のデータベースタイプを選択するか、MySQL の InnoDB を有効にしてください。');
	}


	// Start a transaction
	$db->start_transaction();


	// Create all tables
	$schema = array(
		'FIELDS'		=> array(
			'id'			=> array(
				'datatype'		=> 'SERIAL',
				'allow_null'	=> false
			),
			'username'		=> array(
				'datatype'		=> 'VARCHAR(200)',
				'allow_null'	=> true
			),
			'ip'			=> array(
				'datatype'		=> 'VARCHAR(255)',
				'allow_null'	=> true
			),
			'email'			=> array(
				'datatype'		=> 'VARCHAR(80)',
				'allow_null'	=> true
			),
			'message'		=> array(
				'datatype'		=> 'VARCHAR(255)',
				'allow_null'	=> true
			),
			'expire'		=> array(
				'datatype'		=> 'INT(10) UNSIGNED',
				'allow_null'	=> true
			),
			'ban_creator'	=> array(
				'datatype'		=> 'INT(10) UNSIGNED',
				'allow_null'	=> false,
				'default'		=> '0'
			)
		),
		'PRIMARY KEY'	=> array('id'),
		'INDEXES'		=> array(
			'username_idx'	=> array('username')
		)
	);

	if ($db_type == 'mysql' || $db_type == 'mysqli' || $db_type == 'mysql_innodb' || $db_type == 'mysqli_innodb')
		$schema['INDEXES']['username_idx'] = array('username(25)');

	$db->create_table('bans', $schema) or error('bans テーブルが作成できません。', __FILE__, __LINE__, $db->error());


	$schema = array(
		'FIELDS'		=> array(
			'id'			=> array(
				'datatype'		=> 'SERIAL',
				'allow_null'	=> false
			),
			'cat_name'		=> array(
				'datatype'		=> 'VARCHAR(80)',
				'allow_null'	=> false,
				'default'		=> '\'新しいカテゴリー\''
			),
			'disp_position'	=> array(
				'datatype'		=> 'INT(10)',
				'allow_null'	=> false,
				'default'		=> '0'
			)
		),
		'PRIMARY KEY'	=> array('id')
	);

	$db->create_table('categories', $schema) or error('categories テーブルが作成できません。', __FILE__, __LINE__, $db->error());


	$schema = array(
		'FIELDS'		=> array(
			'id'			=> array(
				'datatype'		=> 'SERIAL',
				'allow_null'	=> false
			),
			'search_for'	=> array(
				'datatype'		=> 'VARCHAR(60)',
				'allow_null'	=> false,
				'default'		=> '\'\''
			),
			'replace_with'	=> array(
				'datatype'		=> 'VARCHAR(60)',
				'allow_null'	=> false,
				'default'		=> '\'\''
			)
		),
		'PRIMARY KEY'	=> array('id')
	);

	$db->create_table('censoring', $schema) or error('censoring テーブルが作成できません。', __FILE__, __LINE__, $db->error());


	$schema = array(
		'FIELDS'		=> array(
			'conf_name'		=> array(
				'datatype'		=> 'VARCHAR(255)',
				'allow_null'	=> false,
				'default'		=> '\'\''
			),
			'conf_value'	=> array(
				'datatype'		=> 'TEXT',
				'allow_null'	=> true
			)
		),
		'PRIMARY KEY'	=> array('conf_name')
	);

	$db->create_table('config', $schema) or error('config テーブルが作成できません。', __FILE__, __LINE__, $db->error());


	$schema = array(
		'FIELDS'		=> array(
			'group_id'		=> array(
				'datatype'		=> 'INT(10)',
				'allow_null'	=> false,
				'default'		=> '0'
			),
			'forum_id'		=> array(
				'datatype'		=> 'INT(10)',
				'allow_null'	=> false,
				'default'		=> '0'
			),
			'read_forum'	=> array(
				'datatype'		=> 'TINYINT(1)',
				'allow_null'	=> false,
				'default'		=> '1'
			),
			'post_replies'	=> array(
				'datatype'		=> 'TINYINT(1)',
				'allow_null'	=> false,
				'default'		=> '1'
			),
			'post_topics'	=> array(
				'datatype'		=> 'TINYINT(1)',
				'allow_null'	=> false,
				'default'		=> '1'
			)
		),
		'PRIMARY KEY'	=> array('group_id', 'forum_id')
	);

	$db->create_table('forum_perms', $schema) or error('forum_perms テーブルが作成できません。', __FILE__, __LINE__, $db->error());


	$schema = array(
		'FIELDS'		=> array(
			'id'			=> array(
				'datatype'		=> 'SERIAL',
				'allow_null'	=> false
			),
			'forum_name'	=> array(
				'datatype'		=> 'VARCHAR(80)',
				'allow_null'	=> false,
				'default'		=> '\'新しいフォーラム\''
			),
			'forum_desc'	=> array(
				'datatype'		=> 'TEXT',
				'allow_null'	=> true
			),
			'redirect_url'	=> array(
				'datatype'		=> 'VARCHAR(100)',
				'allow_null'	=> true
			),
			'moderators'	=> array(
				'datatype'		=> 'TEXT',
				'allow_null'	=> true
			),
			'num_topics'	=> array(
				'datatype'		=> 'MEDIUMINT(8) UNSIGNED',
				'allow_null'	=> false,
				'default'		=> '0'
			),
			'num_posts'		=> array(
				'datatype'		=> 'MEDIUMINT(8) UNSIGNED',
				'allow_null'	=> false,
				'default'		=> '0'
			),
			'last_post'		=> array(
				'datatype'		=> 'INT(10) UNSIGNED',
				'allow_null'	=> true
			),
			'last_post_id'	=> array(
				'datatype'		=> 'INT(10) UNSIGNED',
				'allow_null'	=> true
			),
			'last_poster'	=> array(
				'datatype'		=> 'VARCHAR(200)',
				'allow_null'	=> true
			),
			'sort_by'		=> array(
				'datatype'		=> 'TINYINT(1)',
				'allow_null'	=> false,
				'default'		=> '0'
			),
			'disp_position'	=> array(
				'datatype'		=> 'INT(10)',
				'allow_null'	=> false,
				'default'		=>	'0'
			),
			'cat_id'		=> array(
				'datatype'		=> 'INT(10) UNSIGNED',
				'allow_null'	=> false,
				'default'		=>	'0'
			)
		),
		'PRIMARY KEY'	=> array('id')
	);

	$db->create_table('forums', $schema) or error('forums テーブルが作成できません。', __FILE__, __LINE__, $db->error());


	$schema = array(
		'FIELDS'		=> array(
			'g_id'						=> array(
				'datatype'		=> 'SERIAL',
				'allow_null'	=> false
			),
			'g_title'					=> array(
				'datatype'		=> 'VARCHAR(50)',
				'allow_null'	=> false,
				'default'		=> '\'\''
			),
			'g_user_title'				=> array(
				'datatype'		=> 'VARCHAR(50)',
				'allow_null'	=> true
			),
			'g_moderator'				=> array(
				'datatype'		=> 'TINYINT(1)',
				'allow_null'	=> false,
				'default'		=> '0'
			),
			'g_mod_edit_users'			=> array(
				'datatype'		=> 'TINYINT(1)',
				'allow_null'	=> false,
				'default'		=> '0'
			),
			'g_mod_rename_users'		=> array(
				'datatype'		=> 'TINYINT(1)',
				'allow_null'	=> false,
				'default'		=> '0'
			),
			'g_mod_change_passwords'	=> array(
				'datatype'		=> 'TINYINT(1)',
				'allow_null'	=> false,
				'default'		=> '0'
			),
			'g_mod_ban_users'			=> array(
				'datatype'		=> 'TINYINT(1)',
				'allow_null'	=> false,
				'default'		=> '0'
			),
			'g_read_board'				=> array(
				'datatype'		=> 'TINYINT(1)',
				'allow_null'	=> false,
				'default'		=> '1'
			),
			'g_view_users'				=> array(
				'datatype'		=> 'TINYINT(1)',
				'allow_null'	=> false,
				'default'		=> '1'
			),
			'g_post_replies'			=> array(
				'datatype'		=> 'TINYINT(1)',
				'allow_null'	=> false,
				'default'		=> '1'
			),
			'g_post_topics'				=> array(
				'datatype'		=> 'TINYINT(1)',
				'allow_null'	=> false,
				'default'		=> '1'
			),
			'g_edit_posts'				=> array(
				'datatype'		=> 'TINYINT(1)',
				'allow_null'	=> false,
				'default'		=> '1'
			),
			'g_delete_posts'			=> array(
				'datatype'		=> 'TINYINT(1)',
				'allow_null'	=> false,
				'default'		=> '1'
			),
			'g_delete_topics'			=> array(
				'datatype'		=> 'TINYINT(1)',
				'allow_null'	=> false,
				'default'		=> '1'
			),
			'g_set_title'				=> array(
				'datatype'		=> 'TINYINT(1)',
				'allow_null'	=> false,
				'default'		=> '1'
			),
			'g_search'					=> array(
				'datatype'		=> 'TINYINT(1)',
				'allow_null'	=> false,
				'default'		=> '1'
			),
			'g_search_users'			=> array(
				'datatype'		=> 'TINYINT(1)',
				'allow_null'	=> false,
				'default'		=> '1'
			),
			'g_send_email'				=> array(
				'datatype'		=> 'TINYINT(1)',
				'allow_null'	=> false,
				'default'		=> '1'
			),
			'g_post_flood'				=> array(
				'datatype'		=> 'SMALLINT(6)',
				'allow_null'	=> false,
				'default'		=> '30'
			),
			'g_search_flood'			=> array(
				'datatype'		=> 'SMALLINT(6)',
				'allow_null'	=> false,
				'default'		=> '30'
			),
			'g_email_flood'				=> array(
				'datatype'		=> 'SMALLINT(6)',
				'allow_null'	=> false,
				'default'		=> '60'
			)
		),
		'PRIMARY KEY'	=> array('g_id')
	);

	$db->create_table('groups', $schema) or error('groups テーブルが作成できません。', __FILE__, __LINE__, $db->error());


	$schema = array(
		'FIELDS'		=> array(
			'user_id'		=> array(
				'datatype'		=> 'INT(10) UNSIGNED',
				'allow_null'	=> false,
				'default'		=> '1'
			),
			'ident'			=> array(
				'datatype'		=> 'VARCHAR(200)',
				'allow_null'	=> false,
				'default'		=> '\'\''
			),
			'logged'		=> array(
				'datatype'		=> 'INT(10) UNSIGNED',
				'allow_null'	=> false,
				'default'		=> '0'
			),
			'idle'			=> array(
				'datatype'		=> 'TINYINT(1)',
				'allow_null'	=> false,
				'default'		=> '0'
			),
			'last_post'			=> array(
				'datatype'		=> 'INT(10) UNSIGNED',
				'allow_null'	=> true
			),
			'last_search'		=> array(
				'datatype'		=> 'INT(10) UNSIGNED',
				'allow_null'	=> true
			),
		),
		'UNIQUE KEYS'	=> array(
			'user_id_ident_idx'	=> array('user_id', 'ident')
		),
		'INDEXES'		=> array(
			'ident_idx'		=> array('ident'),
			'logged_idx'	=> array('logged')
		),
		'ENGINE'		=> 'HEAP'
	);

	if ($db_type == 'mysql' || $db_type == 'mysqli' || $db_type == 'mysql_innodb' || $db_type == 'mysqli_innodb')
	{
		$schema['UNIQUE KEYS']['user_id_ident_idx'] = array('user_id', 'ident(25)');
		$schema['INDEXES']['ident_idx'] = array('ident(25)');
	}

	if ($db_type == 'mysql_innodb' || $db_type == 'mysqli_innodb')
		$schema['ENGINE'] = 'InnoDB';

	$db->create_table('online', $schema) or error('online テーブルが作成できません。', __FILE__, __LINE__, $db->error());


	$schema = array(
		'FIELDS'		=> array(
			'id'			=> array(
				'datatype'		=> 'SERIAL',
				'allow_null'	=> false
			),
			'poster'		=> array(
				'datatype'		=> 'VARCHAR(200)',
				'allow_null'	=> false,
				'default'		=> '\'\''
			),
			'poster_id'		=> array(
				'datatype'		=> 'INT(10) UNSIGNED',
				'allow_null'	=> false,
				'default'		=> '1'
			),
			'poster_ip'		=> array(
				'datatype'		=> 'VARCHAR(39)',
				'allow_null'	=> true
			),
			'poster_email'	=> array(
				'datatype'		=> 'VARCHAR(80)',
				'allow_null'	=> true
			),
			'message'		=> array(
				'datatype'		=> 'MEDIUMTEXT',
				'allow_null'	=> true
			),
			'hide_smilies'	=> array(
				'datatype'		=> 'TINYINT(1)',
				'allow_null'	=> false,
				'default'		=> '0'
			),
			'posted'		=> array(
				'datatype'		=> 'INT(10) UNSIGNED',
				'allow_null'	=> false,
				'default'		=> '0'
			),
			'edited'		=> array(
				'datatype'		=> 'INT(10) UNSIGNED',
				'allow_null'	=> true
			),
			'edited_by'		=> array(
				'datatype'		=> 'VARCHAR(200)',
				'allow_null'	=> true
			),
			'topic_id'		=> array(
				'datatype'		=> 'INT(10) UNSIGNED',
				'allow_null'	=> false,
				'default'		=> '0'
			)
		),
		'PRIMARY KEY'	=> array('id'),
		'INDEXES'		=> array(
			'topic_id_idx'	=> array('topic_id'),
			'multi_idx'		=> array('poster_id', 'topic_id')
		)
	);

	$db->create_table('posts', $schema) or error('posts テーブルが作成できません。', __FILE__, __LINE__, $db->error());


	$schema = array(
		'FIELDS'		=> array(
			'id'			=> array(
				'datatype'		=> 'SERIAL',
				'allow_null'	=> false
			),
			'rank'			=> array(
				'datatype'		=> 'VARCHAR(50)',
				'allow_null'	=> false,
				'default'		=> '\'\''
			),
			'min_posts'		=> array(
				'datatype'		=> 'MEDIUMINT(8) UNSIGNED',
				'allow_null'	=> false,
				'default'		=> '0'
			)
		),
		'PRIMARY KEY'	=> array('id')
	);

	$db->create_table('ranks', $schema) or error('ranks テーブルが作成できません。', __FILE__, __LINE__, $db->error());


	$schema = array(
		'FIELDS'		=> array(
			'id'			=> array(
				'datatype'		=> 'SERIAL',
				'allow_null'	=> false
			),
			'post_id'		=> array(
				'datatype'		=> 'INT(10) UNSIGNED',
				'allow_null'	=> false,
				'default'		=> '0'
			),
			'topic_id'		=> array(
				'datatype'		=> 'INT(10) UNSIGNED',
				'allow_null'	=> false,
				'default'		=> '0'
			),
			'forum_id'		=> array(
				'datatype'		=> 'INT(10) UNSIGNED',
				'allow_null'	=> false,
				'default'		=> '0'
			),
			'reported_by'	=> array(
				'datatype'		=> 'INT(10) UNSIGNED',
				'allow_null'	=> false,
				'default'		=> '0'
			),
			'created'		=> array(
				'datatype'		=> 'INT(10) UNSIGNED',
				'allow_null'	=> false,
				'default'		=> '0'
			),
			'message'		=> array(
				'datatype'		=> 'TEXT',
				'allow_null'	=> true
			),
			'zapped'		=> array(
				'datatype'		=> 'INT(10) UNSIGNED',
				'allow_null'	=> true
			),
			'zapped_by'		=> array(
				'datatype'		=> 'INT(10) UNSIGNED',
				'allow_null'	=> true
			)
		),
		'PRIMARY KEY'	=> array('id'),
		'INDEXES'		=> array(
			'zapped_idx'	=> array('zapped')
		)
	);

	$db->create_table('reports', $schema) or error('reports テーブルが作成できません。', __FILE__, __LINE__, $db->error());


	$schema = array(
		'FIELDS'		=> array(
			'id'			=> array(
				'datatype'		=> 'INT(10) UNSIGNED',
				'allow_null'	=> false,
				'default'		=> '0'
			),
			'ident'			=> array(
				'datatype'		=> 'VARCHAR(200)',
				'allow_null'	=> false,
				'default'		=> '\'\''
			),
			'search_data'	=> array(
				'datatype'		=> 'MEDIUMTEXT',
				'allow_null'	=> true
			)
		),
		'PRIMARY KEY'	=> array('id'),
		'INDEXES'		=> array(
			'ident_idx'	=> array('ident')
		)
	);

	if ($db_type == 'mysql' || $db_type == 'mysqli' || $db_type == 'mysql_innodb' || $db_type == 'mysqli_innodb')
		$schema['INDEXES']['ident_idx'] = array('ident(8)');

	$db->create_table('search_cache', $schema) or error('search_cache テーブルが作成できません。', __FILE__, __LINE__, $db->error());


	$schema = array(
		'FIELDS'		=> array(
			'post_id'		=> array(
				'datatype'		=> 'INT(10) UNSIGNED',
				'allow_null'	=> false,
				'default'		=> '0'
			),
			'word_id'		=> array(
				'datatype'		=> 'INT(10) UNSIGNED',
				'allow_null'	=> false,
				'default'		=> '0'
			),
			'subject_match'	=> array(
				'datatype'		=> 'TINYINT(1)',
				'allow_null'	=> false,
				'default'		=> '0'
			)
		),
		'INDEXES'		=> array(
			'word_id_idx'	=> array('word_id'),
			'post_id_idx'	=> array('post_id')
		)
	);

	$db->create_table('search_matches', $schema) or error('search_matches テーブルが作成できません。', __FILE__, __LINE__, $db->error());


	$schema = array(
		'FIELDS'		=> array(
			'id'			=> array(
				'datatype'		=> 'SERIAL',
				'allow_null'	=> false
			),
			'word'			=> array(
				'datatype'		=> 'VARCHAR(20)',
				'allow_null'	=> false,
				'default'		=> '\'\'',
				'collation'		=> 'bin'
			)
		),
		'PRIMARY KEY'	=> array('word'),
		'INDEXES'		=> array(
			'id_idx'	=> array('id')
		)
	);

	if ($db_type == 'sqlite')
	{
		$schema['PRIMARY KEY'] = array('id');
		$schema['UNIQUE KEYS'] = array('word_idx'	=> array('word'));
	}

	$db->create_table('search_words', $schema) or error('search_words テーブルが作成できません。', __FILE__, __LINE__, $db->error());


	$schema = array(
		'FIELDS'		=> array(
			'user_id'		=> array(
				'datatype'		=> 'INT(10) UNSIGNED',
				'allow_null'	=> false,
				'default'		=> '0'
			),
			'topic_id'		=> array(
				'datatype'		=> 'INT(10) UNSIGNED',
				'allow_null'	=> false,
				'default'		=> '0'
			)
		),
		'PRIMARY KEY'	=> array('user_id', 'topic_id')
	);

	$db->create_table('subscriptions', $schema) or error('subscriptions テーブルが作成できません。', __FILE__, __LINE__, $db->error());


	$schema = array(
		'FIELDS'		=> array(
			'id'			=> array(
				'datatype'		=> 'SERIAL',
				'allow_null'	=> false
			),
			'poster'		=> array(
				'datatype'		=> 'VARCHAR(200)',
				'allow_null'	=> false,
				'default'		=> '\'\''
			),
			'subject'		=> array(
				'datatype'		=> 'VARCHAR(255)',
				'allow_null'	=> false,
				'default'		=> '\'\''
			),
			'posted'		=> array(
				'datatype'		=> 'INT(10) UNSIGNED',
				'allow_null'	=> false,
				'default'		=> '0'
			),
			'first_post_id'	=> array(
				'datatype'		=> 'INT(10) UNSIGNED',
				'allow_null'	=> false,
				'default'		=> '0'
			),
			'last_post'		=> array(
				'datatype'		=> 'INT(10) UNSIGNED',
				'allow_null'	=> false,
				'default'		=> '0'
			),
			'last_post_id'	=> array(
				'datatype'		=> 'INT(10) UNSIGNED',
				'allow_null'	=> false,
				'default'		=> '0'
			),
			'last_poster'	=> array(
				'datatype'		=> 'VARCHAR(200)',
				'allow_null'	=> true
			),
			'num_views'		=> array(
				'datatype'		=> 'MEDIUMINT(8) UNSIGNED',
				'allow_null'	=> false,
				'default'		=> '0'
			),
			'num_replies'	=> array(
				'datatype'		=> 'MEDIUMINT(8) UNSIGNED',
				'allow_null'	=> false,
				'default'		=> '0'
			),
			'closed'		=> array(
				'datatype'		=> 'TINYINT(1)',
				'allow_null'	=> false,
				'default'		=> '0'
			),
			'sticky'		=> array(
				'datatype'		=> 'TINYINT(1)',
				'allow_null'	=> false,
				'default'		=> '0'
			),
			'moved_to'		=> array(
				'datatype'		=> 'INT(10) UNSIGNED',
				'allow_null'	=> true
			),
			'forum_id'		=> array(
				'datatype'		=> 'INT(10) UNSIGNED',
				'allow_null'	=> false,
				'default'		=> '0'
			)
		),
		'PRIMARY KEY'	=> array('id'),
		'INDEXES'		=> array(
			'forum_id_idx'		=> array('forum_id'),
			'moved_to_idx'		=> array('moved_to'),
			'last_post_idx'		=> array('last_post'),
			'first_post_id_idx'	=> array('first_post_id')
		)
	);

	$db->create_table('topics', $schema) or error('topics テーブルが作成できません。', __FILE__, __LINE__, $db->error());


	$schema = array(
		'FIELDS'		=> array(
			'id'				=> array(
				'datatype'		=> 'SERIAL',
				'allow_null'	=> false
			),
			'group_id'			=> array(
				'datatype'		=> 'INT(10) UNSIGNED',
				'allow_null'	=> false,
				'default'		=> '3'
			),
			'username'			=> array(
				'datatype'		=> 'VARCHAR(200)',
				'allow_null'	=> false,
				'default'		=> '\'\''
			),
			'password'			=> array(
				'datatype'		=> 'VARCHAR(40)',
				'allow_null'	=> false,
				'default'		=> '\'\''
			),
			'email'				=> array(
				'datatype'		=> 'VARCHAR(80)',
				'allow_null'	=> false,
				'default'		=> '\'\''
			),
			'title'				=> array(
				'datatype'		=> 'VARCHAR(50)',
				'allow_null'	=> true
			),
			'realname'			=> array(
				'datatype'		=> 'VARCHAR(40)',
				'allow_null'	=> true
			),
			'url'				=> array(
				'datatype'		=> 'VARCHAR(100)',
				'allow_null'	=> true
			),
			'jabber'			=> array(
				'datatype'		=> 'VARCHAR(80)',
				'allow_null'	=> true
			),
			'icq'				=> array(
				'datatype'		=> 'VARCHAR(12)',
				'allow_null'	=> true
			),
			'msn'				=> array(
				'datatype'		=> 'VARCHAR(80)',
				'allow_null'	=> true
			),
			'aim'				=> array(
				'datatype'		=> 'VARCHAR(30)',
				'allow_null'	=> true
			),
			'yahoo'				=> array(
				'datatype'		=> 'VARCHAR(30)',
				'allow_null'	=> true
			),
			'location'			=> array(
				'datatype'		=> 'VARCHAR(30)',
				'allow_null'	=> true
			),
			'signature'			=> array(
				'datatype'		=> 'TEXT',
				'allow_null'	=> true
			),
			'disp_topics'		=> array(
				'datatype'		=> 'TINYINT(3) UNSIGNED',
				'allow_null'	=> true
			),
			'disp_posts'		=> array(
				'datatype'		=> 'TINYINT(3) UNSIGNED',
				'allow_null'	=> true
			),
			'email_setting'		=> array(
				'datatype'		=> 'TINYINT(1)',
				'allow_null'	=> false,
				'default'		=> '1'
			),
			'notify_with_post'	=> array(
				'datatype'		=> 'TINYINT(1)',
				'allow_null'	=> false,
				'default'		=> '0'
			),
			'auto_notify'		=> array(
				'datatype'		=> 'TINYINT(1)',
				'allow_null'	=> false,
				'default'		=> '0'
			),
			'show_smilies'		=> array(
				'datatype'		=> 'TINYINT(1)',
				'allow_null'	=> false,
				'default'		=> '1'
			),
			'show_img'			=> array(
				'datatype'		=> 'TINYINT(1)',
				'allow_null'	=> false,
				'default'		=> '1'
			),
			'show_img_sig'		=> array(
				'datatype'		=> 'TINYINT(1)',
				'allow_null'	=> false,
				'default'		=> '1'
			),
			'show_avatars'		=> array(
				'datatype'		=> 'TINYINT(1)',
				'allow_null'	=> false,
				'default'		=> '1'
			),
			'show_sig'			=> array(
				'datatype'		=> 'TINYINT(1)',
				'allow_null'	=> false,
				'default'		=> '1'
			),
			'timezone'			=> array(
				'datatype'		=> 'FLOAT',
				'allow_null'	=> false,
				'default'		=> '9'
			),
			'dst'				=> array(
				'datatype'		=> 'TINYINT(1)',
				'allow_null'	=> false,
				'default'		=> '0'
			),
			'time_format'		=> array(
				'datatype'		=> 'TINYINT(1)',
				'allow_null'	=> false,
				'default'		=> '0'
			),
			'date_format'		=> array(
				'datatype'		=> 'TINYINT(1)',
				'allow_null'	=> false,
				'default'		=> '0'
			),
			'language'			=> array(
				'datatype'		=> 'VARCHAR(25)',
				'allow_null'	=> false,
				'default'		=> '\'Japanese\''
			),
			'style'				=> array(
				'datatype'		=> 'VARCHAR(25)',
				'allow_null'	=> false,
				'default'		=> '\''.$db->escape($default_style).'\''
			),
			'num_posts'			=> array(
				'datatype'		=> 'INT(10) UNSIGNED',
				'allow_null'	=> false,
				'default'		=> '0'
			),
			'last_post'			=> array(
				'datatype'		=> 'INT(10) UNSIGNED',
				'allow_null'	=> true
			),
			'last_search'		=> array(
				'datatype'		=> 'INT(10) UNSIGNED',
				'allow_null'	=> true
			),
			'last_email_sent'	=> array(
				'datatype'		=> 'INT(10) UNSIGNED',
				'allow_null'	=> true
			),
			'registered'		=> array(
				'datatype'		=> 'INT(10) UNSIGNED',
				'allow_null'	=> false,
				'default'		=> '0'
			),
			'registration_ip'	=> array(
				'datatype'		=> 'VARCHAR(39)',
				'allow_null'	=> false,
				'default'		=> '\'0.0.0.0\''
			),
			'last_visit'		=> array(
				'datatype'		=> 'INT(10) UNSIGNED',
				'allow_null'	=> false,
				'default'		=> '0'
			),
			'admin_note'		=> array(
				'datatype'		=> 'VARCHAR(30)',
				'allow_null'	=> true
			),
			'activate_string'	=> array(
				'datatype'		=> 'VARCHAR(80)',
				'allow_null'	=> true
			),
			'activate_key'		=> array(
				'datatype'		=> 'VARCHAR(8)',
				'allow_null'	=> true
			),
		),
		'PRIMARY KEY'	=> array('id'),
		'UNIQUE KEYS'	=> array(
			'username_idx'		=> array('username')
		),
		'INDEXES'		=> array(
			'registered_idx'	=> array('registered')
		)
	);

	if ($db_type == 'mysql' || $db_type == 'mysqli' || $db_type == 'mysql_innodb' || $db_type == 'mysqli_innodb')
		$schema['UNIQUE KEYS']['username_idx'] = array('username(25)');

	$db->create_table('users', $schema) or error('users テーブルが作成できません。', __FILE__, __LINE__, $db->error());


	$now = time();

	// Insert the four preset groups
	$db->query('INSERT INTO '.$db->prefix.'groups ('.($db_type != 'pgsql' ? 'g_id, ' : '').'g_title, g_user_title, g_moderator, g_mod_edit_users, g_mod_rename_users, g_mod_change_passwords, g_mod_ban_users, g_read_board, g_view_users, g_post_replies, g_post_topics, g_edit_posts, g_delete_posts, g_delete_topics, g_set_title, g_search, g_search_users, g_send_email, g_post_flood, g_search_flood, g_email_flood) VALUES('.($db_type != 'pgsql' ? '1, ' : '')."'管理者', '管理者', 0, 0, 0, 0, 0, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 0, 0, 0)") or error('グループが追加できません。', __FILE__, __LINE__, $db->error());

	$db->query('INSERT INTO '.$db->prefix.'groups ('.($db_type != 'pgsql' ? 'g_id, ' : '').'g_title, g_user_title, g_moderator, g_mod_edit_users, g_mod_rename_users, g_mod_change_passwords, g_mod_ban_users, g_read_board, g_view_users, g_post_replies, g_post_topics, g_edit_posts, g_delete_posts, g_delete_topics, g_set_title, g_search, g_search_users, g_send_email, g_post_flood, g_search_flood, g_email_flood) VALUES('.($db_type != 'pgsql' ? '2, ' : '')."'モデレータ', 'モデレータ', 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 0, 0, 0)") or error('グループが追加できません。', __FILE__, __LINE__, $db->error());

	$db->query('INSERT INTO '.$db->prefix.'groups ('.($db_type != 'pgsql' ? 'g_id, ' : '').'g_title, g_user_title, g_moderator, g_mod_edit_users, g_mod_rename_users, g_mod_change_passwords, g_mod_ban_users, g_read_board, g_view_users, g_post_replies, g_post_topics, g_edit_posts, g_delete_posts, g_delete_topics, g_set_title, g_search, g_search_users, g_send_email, g_post_flood, g_search_flood, g_email_flood) VALUES('.($db_type != 'pgsql' ? '3, ' : '')."'ゲスト', NULL, 0, 0, 0, 0, 0, 1, 1, 0, 0, 0, 0, 0, 0, 1, 1, 0, 60, 30, 0)") or error('グループが追加できません。', __FILE__, __LINE__, $db->error());

	$db->query('INSERT INTO '.$db->prefix.'groups ('.($db_type != 'pgsql' ? 'g_id, ' : '').'g_title, g_user_title, g_moderator, g_mod_edit_users, g_mod_rename_users, g_mod_change_passwords, g_mod_ban_users, g_read_board, g_view_users, g_post_replies, g_post_topics, g_edit_posts, g_delete_posts, g_delete_topics, g_set_title, g_search, g_search_users, g_send_email, g_post_flood, g_search_flood, g_email_flood) VALUES('.($db_type != 'pgsql' ? '4, ' : '')."'メンバー', NULL, 0, 0, 0, 0, 0, 1, 1, 1, 1, 1, 1, 1, 0, 1, 1, 1, 60, 30, 60)") or error('グループが追加できません。', __FILE__, __LINE__, $db->error());

	// Insert guest and first admin user
	$db->query('INSERT INTO '.$db_prefix."users (group_id, username, password, email) VALUES(3, 'Guest', 'Guest', 'Guest')")
		or error('ゲストが追加できません。設定を確認してリトライしてください。', __FILE__, __LINE__, $db->error());

	$db->query('INSERT INTO '.$db_prefix."users (group_id, username, password, email, num_posts, last_post, registered, registration_ip, last_visit) VALUES(1, '".$db->escape($username)."', '".pun_hash($password1)."', '$email', 1, ".$now.", ".$now.", '127.0.0.1', ".$now.')')
		or error('管理者が追加できません。設定を確認してリトライしてください。', __FILE__, __LINE__, $db->error());

	// Enable/disable avatars depending on file_uploads setting in PHP configuration
	$avatars = in_array(strtolower(@ini_get('file_uploads')), array('on', 'true', '1')) ? 1 : 0;

	// Insert config data
	$config = array(
		'o_cur_version'				=> "'".FORUM_VERSION."'",
		'o_database_revision'		=> "'".FORUM_DB_REVISION."'",
		'o_searchindex_revision'	=> "'".FORUM_SI_REVISION."'",
		'o_parser_revision'			=> "'".FORUM_PARSER_REVISION."'",
		'o_board_title'				=> "'".$db->escape($title)."'",
		'o_board_desc'				=> "'".$db->escape($description)."'",
		'o_default_timezone'		=> "'9'",
		'o_time_format'				=> "'H:i:s'",
		'o_date_format'				=> "'Y-m-d'",
		'o_timeout_visit'			=> "'1800'",
		'o_timeout_online'			=> "'300'",
		'o_redirect_delay'			=> "'1'",
		'o_show_version'			=> "'0'",
		'o_show_user_info'			=> "'1'",
		'o_show_post_count'			=> "'1'",
		'o_signatures'				=> "'1'",
		'o_smilies'					=> "'1'",
		'o_smilies_sig'				=> "'1'",
		'o_make_links'				=> "'1'",
		'o_default_lang'			=> "'".$db->escape($default_lang)."'",
		'o_default_style'			=> "'".$db->escape($default_style)."'",
		'o_default_user_group'		=> "'4'",
		'o_topic_review'			=> "'15'",
		'o_disp_topics_default'		=> "'30'",
		'o_disp_posts_default'		=> "'25'",
		'o_indent_num_spaces'		=> "'4'",
		'o_quote_depth'				=> "'3'",
		'o_quickpost'				=> "'1'",
		'o_users_online'			=> "'1'",
		'o_censoring'				=> "'0'",
		'o_ranks'					=> "'1'",
		'o_show_dot'				=> "'0'",
		'o_topic_views'				=> "'1'",
		'o_quickjump'				=> "'1'",
		'o_gzip'					=> "'0'",
		'o_additional_navlinks'		=> "''",
		'o_report_method'			=> "'0'",
		'o_regs_report'				=> "'0'",
		'o_default_email_setting'	=> "'1'",
		'o_mailing_list'			=> "'".$email."'",
		'o_avatars'					=> "'".$avatars."'",
		'o_avatars_dir'				=> "'img/avatars'",
		'o_avatars_width'			=> "'60'",
		'o_avatars_height'			=> "'60'",
		'o_avatars_size'			=> "'10240'",
		'o_search_all_forums'		=> "'1'",
		'o_base_url'				=> "'".$db->escape($base_url)."'",
		'o_admin_email'				=> "'".$email."'",
		'o_webmaster_email'			=> "'".$email."'",
		'o_subscriptions'			=> "'1'",
		'o_smtp_host'				=> "NULL",
		'o_smtp_user'				=> "NULL",
		'o_smtp_pass'				=> "NULL",
		'o_smtp_ssl'				=> "'0'",
		'o_regs_allow'				=> "'1'",
		'o_regs_verify'				=> "'0'",
		'o_announcement'			=> "'0'",
		'o_announcement_message'	=> "'アナウンスメッセージを入力'",
		'o_rules'					=> "'0'",
		'o_rules_message'			=> "'利用規約を入力'",
		'o_maintenance'				=> "'0'",
		'o_maintenance_message'		=> "'フォーラムはテンテナンス中です。少し経ってからアクセスしてください。<br />\\n<br />\\n管理者'",
		'o_default_dst'				=> "'0'",
		'o_feed_type'				=> "'2'",
		'p_message_bbcode'			=> "'1'",
		'p_message_img_tag'			=> "'1'",
		'p_message_all_caps'		=> "'1'",
		'p_subject_all_caps'		=> "'1'",
		'p_sig_all_caps'			=> "'1'",
		'p_sig_bbcode'				=> "'1'",
		'p_sig_img_tag'				=> "'0'",
		'p_sig_length'				=> "'400'",
		'p_sig_lines'				=> "'4'",
		'p_allow_banned_email'		=> "'1'",
		'p_allow_dupe_email'		=> "'0'",
		'p_force_guest_email'		=> "'1'"
	);

	foreach ($config as $conf_name => $conf_value)
	{
		$db->query('INSERT INTO '.$db_prefix."config (conf_name, conf_value) VALUES('$conf_name', $conf_value)")
			or error($db_prefix.'config テーブルにレコードが追加できません。設定を確認してリトライしてください。', __FILE__, __LINE__, $db->error());
	}

	// Insert some other default data
	$subject = 'テスト投稿';
	$message = 'この投稿が正しく見えれば FluxBB が正しくインストールされています！ログインして管理ページも覗いてみてください。';

	$db->query('INSERT INTO '.$db_prefix."ranks (rank, min_posts) VALUES('新メンバー', 0)")
		or error($db_prefix.'ranks テーブルにレコードが追加できません。設定を確認してリトライしてください。', __FILE__, __LINE__, $db->error());

	$db->query('INSERT INTO '.$db_prefix."ranks (rank, min_posts) VALUES('メンバー', 10)")
		or error($db_prefix.'ranks テーブルにレコードが追加できません。設定を確認してリトライしてください。', __FILE__, __LINE__, $db->error());

	$db->query('INSERT INTO '.$db_prefix."categories (cat_name, disp_position) VALUES('テストカテゴリー', 1)")
		or error($db_prefix.'categories テーブルにレコードが追加できません。設定を確認してリトライしてください。', __FILE__, __LINE__, $db->error());

	$db->query('INSERT INTO '.$db_prefix."forums (forum_name, forum_desc, num_topics, num_posts, last_post, last_post_id, last_poster, disp_position, cat_id) VALUES('テストフォーラム', 'これはテストフォーラムです。', 1, 1, ".$now.", 1, '".$db->escape($username)."', 1, 1)")
		or error($db_prefix.'forums テーブルにレコードが追加できません。設定を確認してリトライしてください。', __FILE__, __LINE__, $db->error());

	$db->query('INSERT INTO '.$db_prefix."topics (poster, subject, posted, first_post_id, last_post, last_post_id, last_poster, forum_id) VALUES('".$db->escape($username)."', '".$db->escape($subject)."', ".$now.", 1, ".$now.", 1, '".$db->escape($username)."', 1)")
		or error($db_prefix.'topics テーブルにレコードが追加できません。設定を確認してリトライしてください。', __FILE__, __LINE__, $db->error());

	$db->query('INSERT INTO '.$db_prefix."posts (poster, poster_id, poster_ip, message, posted, topic_id) VALUES('".$db->escape($username)."', 2, '127.0.0.1', '".$db->escape($message)."', ".$now.', 1)')
		or error($db_prefix.'posts テーブルにレコードが追加できません。設定を確認してリトライしてください。', __FILE__, __LINE__, $db->error());

	// Index the test post so searching for it works
	require PUN_ROOT.'include/search_idx.php';
	$pun_config['o_default_lang'] = $default_lang;
	update_search_index('post', 1, $message, $subject);

	$db->end_transaction();


	$alerts = array();
	// Check if the cache directory is writable
	if (!@is_writable('./cache/'))
		$alerts[] = '<strong>キャッシュディレクトリのパーミッションに問題があります。</strong> FluxBB を正しく動かすためには、PHP が <em>cache</em> ディレクトリに書き込めなければなりません。chmod コマンドでディレクトリのパーミッションを777などに変更してください。';

	// Check if default avatar directory is writable
	if (!@is_writable('./img/avatars/'))
		$alerts[] = '<strong>アバターディレクトリのパーミッションに問題があります。</strong> ユーザーがアバター画像をアップロードするには、PHP が <em>img/avatars</em> ディレクトリに書き込めなければなりません。アバター画像の保存ディレクトリは後で変更できます（管理/オプション を参照）。chmod コマンドでディレクトリのパーミッションを777などに変更してください。';

	// Check if we disabled uploading avatars because file_uploads was disabled
	if ($avatars == '0')
		$alerts[] = '<strong>ファイルのアップロードができないようです。</strong> ユーザーがアバター画像をアップロードするには、PHP の file_uploads　の設定を変更する必要があります。ファイルのアップロードが可能になれば, 管理/オプション/機能 のページでアバター画像のアップロードができるようになります。';

	// Add some random bytes at the end of the cookie name to prevent collisions
	$cookie_name = 'pun_cookie_'.random_key(6, false, true);

	// Generate the config.php file data
	$config = generate_config_file();

	// Attempt to write config.php and serve it up for download if writing fails
	$written = false;
	if (is_writable(PUN_ROOT))
	{
		$fh = @fopen(PUN_ROOT.'config.php', 'wb');
		if ($fh)
		{
			fwrite($fh, $config);
			fclose($fh);

			$written = true;
		}
	}


?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">

<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>FluxBB インストール</title>
<link rel="stylesheet" type="text/css" href="style/<?php echo $default_style ?>.css" />
</head>
<body>

<div id="puninstall" class="pun">
<div class="top-box"><div><!-- Top Corners --></div></div>
<div class="punwrap">

<div id="brdheader" class="block">
	<div class="box">
		<div id="brdtitle" class="inbox">
			<h1><span>FluxBB インストール</span></h1>
			<div id="brddesc"><p>FluxBB がインストールされました。次の指示に従ってインストールを完了してください。</p></div>
		</div>
	</div>
</div>

<div id="brdmain">

<div class="blockform">
	<h2><span>最終操作</span></h2>
	<div class="box">
<?php

if (!$written)
{

?>
		<form method="post" action="install.php">
			<div class="inform">
				<div class="forminfo">
					<p>下のボタンをクリックしてインストールを完了してください。ダウンロードされた config.php ファイルを FluxBB のルートディレクトリにアップロードしてください。</p>
					<p>config.php ファイルをアップロードしてインストールは終了です。 <a href="index.php">フォーラムを見る</a></p>
				</div>
				<input type="hidden" name="generate_config" value="1" />
				<input type="hidden" name="db_type" value="<?php echo $db_type; ?>" />
				<input type="hidden" name="db_host" value="<?php echo $db_host; ?>" />
				<input type="hidden" name="db_name" value="<?php echo pun_htmlspecialchars($db_name); ?>" />
				<input type="hidden" name="db_username" value="<?php echo pun_htmlspecialchars($db_username); ?>" />
				<input type="hidden" name="db_password1" value="<?php echo pun_htmlspecialchars($db_password1); ?>" />
				<input type="hidden" name="db_prefix" value="<?php echo pun_htmlspecialchars($db_prefix); ?>" />
				<input type="hidden" name="cookie_name" value="<?php echo pun_htmlspecialchars($cookie_name); ?>" />
				<input type="hidden" name="cookie_seed" value="<?php echo pun_htmlspecialchars($cookie_seed); ?>" />

<?php if (!empty($alerts)): ?>				<div class="forminfo error-info">
					<ul class="error-list">
<?php

foreach ($alerts as $cur_alert)
	echo "\t\t\t\t\t".'<li>'.$cur_alert.'</li>'."\n";
?>
					</ul>
				</div>
<?php endif; ?>			</div>
			<p class="buttons"><input type="submit" value="config.php ファイルをダウンロード" /></p>
		</form>

<?php

}
else
{

?>
		<div class="fakeform">
			<div class="inform">
				<div class="forminfo">
					<p>FluxBB がインストールされました。 <a href="index.php">フォーラムを見る</a></p>
				</div>
			</div>
		</div>
<?php

}

?>
	</div>
</div>

</div>

</div>
<div class="end-box"><div><!-- Bottom Corners --></div></div>
</div>

</body>
</html>
<?php

}
