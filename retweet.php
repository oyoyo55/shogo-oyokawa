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

// リツイートする投稿の取得
$posts = $db->prepare('SELECT * FROM posts WHERE id=?');
$posts->execute(array($_REQUEST['id']));
$post = $posts->fetch();

// ログインユーザーがリツイートされているか判断するために
// ログインユーザーがリツイートしているデータを取得
$rt_posts = $db->prepare('SELECT COUNT(*) AS cnt FROM posts WHERE rt_post_id=? AND rt_member_id=?');
$rt_posts->execute(array(
    $post['rt_post_id'],
    $member['id']
));
$rt_post = $rt_posts->fetch();

// 削除データの取得
$retweets_del = $db->prepare('SELECT * FROM posts WHERE rt_post_id=? AND rt_member_id=?');
$retweets_del->execute(array(
    $post['rt_post_id'],
    $member['id']
));
$retweet_del = $retweets_del->fetch();

// 自分の投稿はリツイートできないように
if ($post['rt_member_id'] || $post['member_id'] === $member['id']) {
    header('Location: index.php');
} else {

    // リツイートされていたら
    if ($rt_post['cnt'] >0) {
        
        // リツイートを削除
        $del = $db->prepare('DELETE FROM posts WHERE id=?');
        $del->execute(array($retweet_del['id']));

        header('Location: index.php');
    } else {
    
        // まだ誰もリツイートしていない投稿の場合
        if ($post['rt_post_id'] == 0) {
            
            // リツイートする
            $retweet = $db->prepare('INSERT INTO posts SET member_id=?, message=?, rt_member_id=?, rt_post_id=?, created=NOW()');
            $retweet->execute(array(
                $post['member_id'],
                $post['message'],
                $member['id'],
                $_REQUEST['id']
                ));
            
            // updateでrt_post_idを入れる
            $rt_post_up = $db->prepare('UPDATE posts SET rt_post_id=? WHERE id=?');
            $rt_post_up->execute(array($_REQUEST['id'],$_REQUEST['id']));
            header('Location: index.php');
        } else {

            // リツイートする
            $retweets = $db->prepare('INSERT INTO posts SET member_id=?, message=?, rt_member_id=?, rt_post_id=?, created=NOW()');
            $retweets->execute(array(
                $post['member_id'],
                $post['message'],
                $member['id'],
                $post['rt_post_id']
            ));

            header('Location: index.php');
        }
    }
}
