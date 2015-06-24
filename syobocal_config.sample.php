<?php

$cfg = array();


// しょぼいカレンダーのユーザーID
// --interactiveで表示されるチャンネルを絞り込むためにもしょぼいカレンダーの負荷軽減のためにも
// ユーザーIDを指定してください
$cfg['user'] = '<<UserID>>';

// ファイル名をざく切りにするための正規表現
// (?P<NAME>REGEX..) で名前付きキャプチャグループの作成。
// 必要なキャプチャグループは
//   channel:   チャンネル。syobocal_channel.jsonでしょぼいカレンダーのチャンネル名と結びつけを行います。
//              syobocal_channel.jsonの更新には 'syobocal_rename.php --interactive' を使うと便利です。
//   date:      日時。放送開始時間の絞り込みに使われるので結構重要です。
//              $cfg['date_format'] によって更にパースされます
//   title:     タイトル。先頭5文字が比較に使われます。TVアニメなど意味のない名前が入るときは
//              syobocal_replace.json を編集してください。
//   extension: 拡張子。
//
// その他に
//   extra:     拡張用。何か付加的な情報がファイル名にあればここに入れてください。
//
// Chinachuデフォルトなら /^\\[(?P<date>\\d{6}-\\d{4})\\]\\[(?P<channel>[^\\]]+)\\]\\[.+\\](?P<title>.+).(?P<extension>m2ts)$/
// になるのかな？date_formatの更新も忘れずに
$cfg['path_regex'] = "/^(?P<extra>(?:nocm-)?)(?P<channel>.+)_(?P<date>\\d+)\\d{2}_(?P<title>.+)\\.(?P<extension>[^\\.]+)$/";

// リネーム後のパス。相対パスなら syobocal_rename.php を走らせた作業ディレクトリ内に保管します。
// 絶対パスなら言わずもがな。
// %Season%:    放送された季節 ("年度"Y"四半期"Q)
// %Title%:     しょぼいカレンダーによるタイトル
// %Extra%:     上の(?P<extra>REGEX)で取得した内容
// %Channel%:   もとのファイル名にあったチャンネル
// %Date%:      もとのファイル名にあった日時
// %Count%:     何話目か
// %SubTitle%:  サブタイトル (あれば)
// %ext%:       拡張子
$cfg['new_path'] = "%Season%/%Title%/%Extra%%Channel%_%Date%00_%Title%_#%Count%_%SubTitle%.%ext%";

// ファイル名の日時の形式。
// 参照: http://php.net/manual/ja/datetime.createfromformat.php#refsect1-datetime.createfromformat-parameters
// Chinachuデフォルトならおそらくymd-Hi
$cfg['date_format'] = "YmdHi";

// ファイルのグループ名指定。リネーム後にこのグループに所属させます。
$cfg['file_group'] = "mediaprov";

// epgrec用。実際のディレクトリとepgrecから見えるディレクトリが違うときに。
$cfg['media_path'] = array(
	'/data/epgrec' => $script_path.'/video',
	'/data/encoded/full' => $script_path.'/video/mp4'
);


{
$cfg['replace'] = json_decode(file_get_contents($script_path . "/syobocal_replace.json"), true);
}

date_default_timezone_set('Asia/Tokyo');
mb_internal_encoding('UTF-8');