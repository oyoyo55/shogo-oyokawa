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
    header('Location: login.php');
    exit();
}

// 投稿を記録する
if (!empty($_POST)) {
    if ($_POST['message'] != '') {
        $message = $db->prepare('INSERT INTO posts SET member_id=?, message=?, reply_post_id=?, created=NOW()');
        $message->execute(array(
            $member['id'],
            $_POST['message'],
            $_POST['reply_post_id']
        ));

        header('Location: index.php');
        exit();
    }
}

// 投稿を取得する
$page = $_REQUEST['page'];
$page = max($page, 1);

// 最終ページを取得する
$counts = $db->query('SELECT COUNT(*) AS cnt FROM posts');
$cnt = $counts->fetch();
$maxPage = ceil($cnt['cnt'] / 5);
$page = min($page, $maxPage);

$start = ($page - 1) * 5;

$posts = $db->prepare('SELECT m.name, m.picture, p. * FROM members m, posts p WHERE m.id=p.member_id ORDER BY p.created DESC LIMIT ?, 5');
$posts->bindParam(1, $start, PDO::PARAM_INT);
$posts->execute();

// 返信の場合

if (isset($_REQUEST['res'])) {
    $response = $db->prepare('SELECT m.name, m.picture, p.* FROM members m,	posts p WHERE m.id=p.member_id AND p.id=? ORDER BY p.created DESC');
    $response->execute(array($_REQUEST['res']));
    $table = $response->fetch();
    $message = '@' . $table['name'] . ' ' . $table['message'];
}
    
// htmlspecialcharsのショートカット
function h($value)
{
    return htmlspecialchars($value, ENT_QUOTES);
}

// 本文内のURLリンクを設定します
function makeLink($value)
{
    return mb_ereg_replace("(https?)(://[[:alnum:]\+\$\;\?\.%,!#~*/:@&=_-]+)", '<a href="\1\2">\1\2</a>', $value);
}

?>

<!doctype html>
<html lang="ja">
<head>
<!-- Required meta tags -->
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

<!-- Bootstrap CSS -->
<link rel="stylesheet" href="style.css" type="text/css">
<link href="https://use.fontawesome.com/releases/v5.6.1/css/all.css" rel="stylesheet">
 
<title>よくわかるPHPの教科書</title>
</head>
<body>
<div id="wrap">
    <div id="head">
        <h1>ひとこと掲示板</h1>
    </div>    
    <div id="content">
        <div style="text-align: right"><a href="logout.php">ログアウト</a></div>
		<form action="" method="post">
		    <dl>
			    <dt><?php echo h($member['name']); ?>さん、メッセージをどうぞ</dt>
		    <dd>
		        <textarea name="message" cols="50" rows="5"><?php echo h($message); ?></textarea>
		        <input type="hidden" name="reply_post_id" value="<?php echo h($_REQUEST['res']); ?>" />
		    </dd>
		    </dl>
		    <div>
		        <input type="submit" value="投稿する" />
		    </div>
		</form>

        <?php foreach ($posts as $post): ?>
            
        <?php
        // likesテーブルからいいねのデータを取り出す
        $likes = $db->prepare('SELECT * FROM likes WHERE post_id=? OR post_id=?');
        $likes->execute(array(
            $post['id'],
            $post['rt_post_id']
        ));
        $like = $likes->fetch();

        // likesテーブルからいいねした人の数を取得
        $likes_cnt = $db->prepare('SELECT COUNT(member_id) AS cnt FROM likes WHERE post_id=? OR post_id=?');
        $likes_cnt->execute(array(
            $post['id'],
            $post['rt_post_id']
        ));
        $like_cnt = $likes_cnt->fetch();
        
        // ログインしているユーザーがリツイートしているデータを取得
        $retweet = $db->prepare('SELECT COUNT(*) AS cnt FROM posts WHERE rt_member_id=? AND rt_post_id=?');
        $retweet->execute(array(
            $member['id'],
            $post['rt_post_id']
        ));
        $retweets = $retweet->fetch();

        // リツイートされている数をカウント
        $rts_cnt = $db->prepare('SELECT COUNT(*) AS cnt FROM posts WHERE rt_post_id=?>0 AND rt_member_id>0');
        $rts_cnt->execute(array($post['rt_post_id']));
        $rt_cnt = $rts_cnt->fetch();
        
        // リツイートしたユーザーのnameを取得
        $rt_member = $db->prepare('SELECT * FROM members WHERE id=?');
        $rt_member->execute(array($post['rt_member_id']));
        $rt_member_name = $rt_member->fetch();
        ?>
        
		<div class="msg">

            <!-- リツイートされた投稿であれば、rt_memberを表示 -->
            <?php if ($post['rt_post_id'] >0 && $post['rt_member_id'] >0) : ?>
                <p class="rt_member_name"><i class="fas fa-retweet retweet"></i><?php echo h($rt_member_name['name']); ?>さんがリツイート</p>
            <?php endif; ?>

			<img src="member_picture/<?php echo h($post['picture']); ?>" width="48" height="48" alt="<?php echo h($post['name']); ?>" />
			<p><?php echo makeLink(h($post['message']));?><span class="name">（<?php echo h($post['name']); ?>）</span>
            [<a href="index.php?res=<?php echo h($post['id']); ?>">Re</a>]</p>
            <p class="day"><a href="view.php?id=<?php echo h($post['id']); ?>"><?php echo h($post['created']); ?></a></p>

            <!-- いいねボタン -->
            <!-- ログインユーザーがいいね済みなら色を赤色に -->
            <?php if ($member['id'] === $like['member_id']) : ?>
                <a href="like.php?id=<?php echo h($post['id']); ?>"><i class="fas fa-heart heart_red"></i></a>
            <?php else : ?>
                <a href="like.php?id=<?php echo h($post['id']); ?>"><i class="fas fa-heart heart_gray"></i></a>
            <?php endif; ?>
            
            <!-- いいね数を表示 -->
            <?php print($like_cnt['cnt']); ?>
           
            <!-- リツイートボタン -->
            <!-- ログインユーザーがリツイート済みなら色をオレンジに -->
            <?php if ($post['rt_member_id'] === $member['id'] || $rt_record['cnt'] >0) : ?>
                <a href="retweet.php?id=<?php echo h($post['id']); ?>"><i class="fas fa-retweet retweet_orange"></i></a>
            <?php else : ?>
                <a href="retweet.php?id=<?php echo h($post['id']); ?>"><i class="fas fa-retweet retweet_gray"></i></a>
            <?php endif; ?>

            <!-- リツイート数を表示 -->
            <?php print($rt_cnt['cnt']); ?>
            
            <!-- 返信メッセージurl -->
            <!-- 返信メッセージがあればurlを表示 -->
            <?php if ($post['reply_post_id'] > 0) : ?>
                <a href="view.php?id=<?php echo h($post['reply_post_id']); ?>">返信元のメッセージ</a>
            <?php endif; ?>

            <!-- ログインユーザーであれば削除ボタン表示 -->
            <?php if ($_SESSION['id'] === $post['member_id']): ?>
                [<a href="delete.php?id=<?php echo h($post['id']); ?>" style="color:#F33;">削除</a>]
            <?php endif; ?>    
		</div>

		<?php endforeach; ?>
        
        <ul class="paging">
            <?php if ($page > 1) { ?>
                <li><a href="index.php?page=<?php print($page - 1); ?>">前のページへ</a></li>
            <?php } else { ?>
                <li>前のページへ</li>
            <?php } ?>

            <?php if ($page < $maxPage) { ?>
                <li><a href="index.php?page=<?php print($page + 1); ?>">次のページへ</a></li>
            <?php } else { ?>
                <li>次のページへ</li>
            <?php } ?>
        </ul>
    </div>  
</div>
</body>    
</html>