<?php

// Autoload 自动载入
require '../vendor/autoload.php';

use Predis\Client;

const  ONE_WEEK_IN_SECONDS = 7 * 86400;
const VOTE_SCORE = 432;


function article_vote(Client $conn, $user, $article)
{
    $cutoff = time() - ONE_WEEK_IN_SECONDS;
    if ($conn->zscore('time:', $article) < $cutoff) {
        return;
    }
    $article_id = call_user_func('end', explode(":", $article));
    if ($conn->sadd('voted:' . $article_id, $user)) {
        $conn->zincrby('score:', VOTE_SCORE, $article);
        $conn->hincrby($article, 'votes', 1);
    }

}

function post_article(Client $conn, $user, $title, $link)
{
    $article_id = (string)$conn->incr('article:');

    $voted = 'voted:' . $article_id;
    $conn->sadd($voted, $user);
    $conn->expire($voted, ONE_WEEK_IN_SECONDS);

    $now = time();
    $article = 'article:' . $article_id;
    $conn->hmset($article, array(
        'title' => $title,
        'link' => $link,
        'poster' => $user,
        'time' => $now,
        'votes' => 1
    ));

    $conn->zadd('score:', $now + VOTE_SCORE, $article);
    $conn->zadd('time:', $now, $article);

    return $article_id;
}

const ARTICLES_PER_PAGE = 25;

function get_articles($conn, $page, $order='score:')
{
    $start = ($page - 1) * ARTICLES_PER_PAGE;
    $end = $start + ARTICLES_PER_PAGE -1;

    $ids = $conn->zrevrange($order, $start, $end);
//    echo json_encode($ids)."\n";
    $articles = [];
    foreach($ids as $id) {
        $article_data = $conn->hgetall($id);
        $article_data['id'] = $id;
        array_push($articles, $article_data);
    }
    return $articles;
}

function add_remove_groups(Client $conn, $article_id, $to_add=[], $to_remove=[])
{
    $article = 'article:' . $article_id;
    foreach($to_add as $group) {
        $conn->sadd('group:' . $group, $article);
    }
    foreach($to_remove as $group) {
        $conn->srem('group:' . $group, $article);
    }
}

function get_group_articles(Client $conn, $group, $page, $order='score:')
{
    $key = $order . $group;
    if (!$conn->exists($key)) {
        $conn->executeRaw(
            array(
                'zinterstore', $key, 2,
                'group:'.$group, $order,
                'aggregate', 'max'
            )
        );
        $conn->expire($key, 60);
    };
    return get_articles($conn, $page, $key);

}

class TestCh01 extends PHPUnit_Framework_TestCase
{
    protected static $conn;

    public function setUp()
    {
        self::$conn = new Client('tcp://localhost:6379');
        self::$conn->select(1);

    }

    public function tearDown()
    {
        self::$conn = NULL;
        echo "\n";
        echo "\n";
    }


    public function test_article_functionality()
    {
        $conn =self::$conn;
//        $conn->flushdb();
        $article_id = post_article($conn, 'username', 'A title', 'https://baidu.com');
        echo "we posted a new article with id:" . $article_id;
        echo "\n";
        echo "is_numeric(\$article_id):".is_numeric($article_id);
        echo "\n";
        self::assertTrue(is_numeric($article_id));

        echo "Its Hash looks like:";
        $r = $conn->hgetall('article:' . $article_id);
        echo json_encode($r, JSON_PRETTY_PRINT);
        echo "\n";
        self::assertTrue(is_array($r));

        article_vote($conn, 'other_user', 'article:'. $article_id);
        echo "We voted for the article, it now has votes:";
        $v = $conn->hget('article:'.$article_id, 'votes');
        echo $v."\n";
        self::assertTrue($v > 1);

        echo "The currently highest-scoring articles are:";
        $articles = get_articles($conn, 1);
        echo json_encode($articles, JSON_PRETTY_PRINT);

        self::assertTrue(count($articles) >= 1);

        add_remove_groups($conn, $article_id, ['new-group']);
        echo "We added the article to a new group, other articles included:";
        $articles = get_group_articles($conn, 'new-group', 1);
        echo json_encode($articles, JSON_PRETTY_PRINT);
        self::assertTrue(count($articles) >= 1);
    }


}


$suite = new PHPUnit_Framework_TestSuite();
$suite->addTestSuite('TestCh01');

PHPUnit_TextUI_TestRunner::run($suite);


