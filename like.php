<?php
session_start();
require('dbconnect.php');

if (isset($_SESSION['id']) && $_SESSION['time'] + 3600 > time()) {
    // ログインしている
    $_SESSION['time'] = time();

    $members = $db->prepare('SELECT * FROM members WHERE id=?');
    $members->execute(array($_SESSION['id']));
    $member = $members->fetch();
    
    // memberが見つからなかったら、ログイン画面に戻す
    if (!isset($member)) {
        $error['member_id'] = 'blank';
    }
    if (!empty($error)) {
        $_SESSION['member'] = $error['member_id'];
        header('Location: login.php');
    }
} else {
    // ログインしていない
    header('Location: login.php');
    exit();
}

// postsテーブルからデータ取得
$posts = $db->prepare('SELECT * FROM posts WHERE id=?');
$posts->execute(array($_REQUEST['id']));
$post = $posts->fetch();

// ログインユーザがいいねしているか判断するために
// likesテーブルからログインユーザーとpostのパラメーターが一緒のデータを取得
$like_post = $db->prepare('SELECT COUNT(*) AS cnt FROM likes WHERE (post_id=? OR post_id=?) AND member_id=?');
$like_post->execute(array(
    $_REQUEST['id'],
    $post['rt_post_id'],
    $member['id']
));
$record = $like_post->fetch();

// いいねを取り消すためのIDを取得
$likes_del = $db->prepare('SELECT * FROM likes WHERE (post_id=? OR post_id=?) AND member_id=?');
$likes_del->execute(array(
    $_REQUEST['id'],
    $post['rt_post_id'],
    $member['id']
));
$like_del = $likes_del->fetch();

// ログインユーザーがいいねしていなかったら
if ((int)$record['cnt'] === 0) {

    // リツイートされていなかったら
    if ((int)$post['rt_post_id'] === 0) {
        $likes = $db->prepare('INSERT INTO likes SET post_id=?, member_id=?');
        $likes->execute(array(
            $_REQUEST['id'],
            $member['id']
        ));
    } else {
        $rt_likes = $db->prepare('INSERT INTO likes SET post_id=?, member_id=?');
        $rt_likes->execute(array(
            $post['rt_post_id'],
            $member['id']
        ));
    }

    // いいね済みなら
} else {
    // likesテーブルから該当するIDをDELETE
    $del = $db->prepare('DELETE FROM likes WHERE id=?');
    $del->execute(array($like_del['id']));
}

header('Location: index.php');
