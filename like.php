<?php
session_start();
require('dbconnect.php');

if (isset($_SESSION['id']) && $_SESSION['time'] + 3600 > time()) {
    // ログインしている
    $_SESSION['time'] = time();

    $members = $db->prepare('SELECT * FROM members WHERE id=?');
    $members->execute(array($_SESSION['id']));
    $member = $members->fetch();
} else {
    // ログインしていない
    header('Location: login.php'); exit();
}

// postsテーブルからデータ取得
$posts = $db->prepare('SELECT * FROM posts WHERE id=?');
$posts->execute(array($_REQUEST['id']));
$post = $posts->fetch();

// ログインユーザーがいいねしているか判断するために
// likesテーブルからログインユーザーとpostのパラメータが一緒のデータを取得
$like_posts = $db->prepare('SELECT COUNT(*) AS cnt FROM likes WHERE (post_id=? OR post_id=?) AND member_id=?');
$like_posts->execute(array(
    $_REQUEST['id'],
    $post['rt_post_id'],
    $member['id']
));
$like_post = $like_posts->fetch();

// いいねを取り消すためのidを取得
$likes_del = $db->prepare('SELECT * FROM likes WHERE (post_id=? OR post_id=?) AND member_id=?');
$likes_del->execute(array(
    $_REQUEST['id'],
    $post['rt_post_id'],
    $member['id']
));
$like_del = $likes_del->fetch();


// ログインユーザーがいいねしていなかったら
if ($like_post['cnt'] === 0) {
    
    // リツイートされていなかったら
    if ($post['rt_post_id'] === 0) {
    // likesテーブルにINSERT
    $likes = $db->prepare('INSERT INTO likes SET post_id=?, member_id=?');
    $likes->execute(array(
        $_REQUEST['id'],
        $member['id']
    ));
    } else {
    $likes = $db->prepare('INSERT INTO likes SET post_id=?, member_id=?');
    $likes->execute(array(
        $_REQUEST['rt_post_id'],
        $member['id']
    ));    
    }

// いいね済みなら    
} else {
    // likesテーブルから該当するidをDELETE
    $del = $db->prepare('DELETE FROM likes WHERE id=?');
    $del->execute(array($like_del['id']));
}
header('Location: index.php');
?>
