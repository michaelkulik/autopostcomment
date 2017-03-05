<?php

require_once 'vendor/autoload.php';
include("vendor/phpxmlrpc/phpxmlrpc/lib/xmlrpc.inc");
$GLOBALS['xmlrpc_internalencoding'] = 'UTF-8';
PhpXmlRpc\PhpXmlRpc::importGlobals();

define('FINDPOST', ''); // искомый пост (целиком или чать названия поста, вводить без заглавных)
define('COMMENT', ''); // текст комментария
define('USERNAME', ''); // логин от livejournal
define('PASS', ''); // пароль от livejournal
define('JOURNAL', ''); // целевой журнал
define('URL', 'http://логин_пользователя.livejournal.com/'); // url целевого журнала для получения контента
define('BROWSER', 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/53.0.2785.143 Safari/537.36');
define('FLAG', __DIR__ . '/flag.txt');

function request($url)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, BROWSER);
    $res = curl_exec($ch);
    curl_close($ch);
    return $res;
}

if (file_exists(FLAG)) {
    exit('Comment was post already');
}

// запрос на нахождение поста
$html = request(URL);
phpQuery::newDocument($html);
$posts = pq('div.j-l-alpha-content-inner > .entryunit'); 
foreach ($posts as $post) {
    $post = pq($post);
    $pos = strpos(mb_strtolower($post->find('header > h3.entryunit__title > a')->text()), FINDPOST);
    if ($pos !== false) {
        $find_url = $post->find('footer div.entryunit__actions li.actions-entryunit__item--comments > a')->attr('href'); 
        $ditemid = preg_replace("/[^0-9]/", '', $find_url);
        break;
    }
}
phpQuery::unloadDocuments();

// пост комментария
if (isset($ditemid)) {
    var_dump($ditemid);

    $post = array(
        "username" => new xmlrpcval(USERNAME, "string"),
        "auth_method" => new xmlrpcval('clear', "string"),
        "password" => new xmlrpcval(PASS, "string"),
//        "ver" => new xmlrpcval(2, "int"),
        "body" => new xmlrpcval(html_entity_decode(COMMENT), "string"),
        "ditemid" => new xmlrpcval($ditemid, "int"),
        "journal" => new xmlrpcval(JOURNAL, "string"),
    );

    // на основе массива создаем структуру
    $post2 = array(
        new xmlrpcval($post, "struct")
    );

    // создаем XML сообщение для сервера
    $f = new xmlrpcmsg('LJ.XMLRPC.addcomment', $post2);

    // описываем сервер
    $c = new xmlrpc_client("/interface/xmlrpc", "www.livejournal.com", 80);
    $c->request_charset_encoding = "UTF-8";

    // отправляем XML сообщение на сервер
    $r = $c->send($f);

    if(!$r->faultCode()) {
        // сообщение принято успешно и вернулся XML-результат
        file_put_contents(FLAG, '');
        $v = php_xmlrpc_decode($r->value());
        print_r($v);
    } else {
        // сервер вернул ошибку
        echo "An error occurred: ";
        echo "Code: ".htmlspecialchars($r->faultCode());
        echo " Reason: '".htmlspecialchars($r->faultString())."'\n";
    }
} else {
    echo 'Post has disabled comments or post that is found doesn\'t exist';
}