<?php
/* LJ filter by Mikhail Solovyev (mixailo@mixailo.org) */
/* License: CC-BY-SA */
/* Version: 0.1 */
/* protocol reference: http://code.livejournal.org/trac/livejournal/browser/trunk/cgi-bin/ljprotocol.pl?rev=18041 */
/* protocol reference: http://www.livejournal.com/doc/server/ljp.csp.xml-rpc.protocol.html */
/* alternative way to get comments: http://www.livejournal.com/doc/server/ljp.csp.export_comments.html */
/* IXR_library: http://www.wordtracker.com/docs/api/ch03.html */
/* Create file config.inc.php before using */
/* config.inc.php:
 *
    <?
    define('LJ_LOGIN',  ''); // логин в жж
    define('LJ_PASSWD', ''); // пароль в жж
 *
 *
 */

require('IXR_Library.php');
require("config.inc.php");

// конфиг
define('LJ_HOST',   'www.livejournal.com'); // не менять
define('LJ_PATH',   '/interface/xmlrpc'); // не менять
define('STOP_PHRASE', 'На самом деле многие решили поспорить с ботом');

// Создаем xml-rpc клиента
$ljClient = new IXR_Client(LJ_HOST, LJ_PATH);

$posts = array();

// Заполняем поля XML-запроса
$ljArgs = array();
$ljArgs['username'] = LJ_LOGIN;
$ljArgs['password'] = LJ_PASSWD;
$ljArgs['auth_method'] = 'clear';
$ljArgs['ver']  = "1";
$ljArgs['selecttype'] = 'lastn';
$ljArgs['howmany'] = 5;
$ljMethod = 'LJ.XMLRPC.getevents';

// Посылаем запрос
if (!$ljClient->query($ljMethod, $ljArgs)) {
    echo 'Ошибка ['.$ljClient->getErrorCode().'] '.$ljClient->getErrorMessage();
}
else {
    // Получаем ответ
    $ljResponse = $ljClient->getResponse();
    foreach ($ljResponse['events'] as $event) {
        $posts[$event['itemid']] = array("itemid" => $event['itemid'], "anum" => $event['anum'], "ditemid" => ($event['itemid']*256+$event['anum']));
        echo "Processing post ".$posts[$event['itemid']]['ditemid']."\n";
        $pages = ceil($event['reply_count']/25); // не вполне ясно, как получить нормальное число страниц
        echo "Maximum ".$pages." pages\n";
        for ($i=1;$i<=$pages;$i++) {
            echo "Processing page ".$i."\n";
            foreach ($posts as $post) {
                // Заполняем поля XML-запроса
                $ljArgs = array();
                $ljArgs['username']       = LJ_LOGIN;
                $ljArgs['auth_method']    = 'clear';
                $ljArgs['password'] = LJ_PASSWD;
                $ljArgs['ver']            = "1";
                $ljArgs['ditemid'] = $post['ditemid'];
                $ljArgs['journal'] = LJ_LOGIN;
                $ljArgs['page'] = $i;
                // закомментировать следующую строку, если нужны ветки целиком
                $ljArgs['expang_strategy'] = 'mobile_thread';
                $ljMethod = 'LJ.XMLRPC.getcomments';

                // Посылаем запрос
                if (!$ljClient->query($ljMethod, $ljArgs)) {
                    echo 'Ошибка ['.$ljClient->getErrorCode().'] '.$ljClient->getErrorMessage();
                }
                else {
                    $ljResponse = $ljClient->getResponse();
                    if (is_array($ljResponse['comments'])) {
                        // тут мы вынуждены обрабатывать их по одному
                        foreach ($ljResponse['comments'] as $comment) {
                            if (isBadComment($comment)) {
                                deleteBadComment($comment['dtalkid']);
                            }
                        }
                    }
                }
            }
        }
    }
}


function deleteBadComment($badId) {
    $ljClient = new IXR_Client(LJ_HOST, LJ_PATH);
    // Заполняем поля XML-запроса
    $ljArgs = array();
    $ljArgs['username']       = LJ_LOGIN;
    $ljArgs['password'] = LJ_PASSWD;
    $ljArgs['auth_method']    = 'clear';
    $ljArgs['ver']            = "1";
    $ljArgs['dtalkid'] = $badId;
    // судя по коду, можно делать еще recursive=1, чтобы удалить все ветку
    $ljMethod = 'LJ.XMLRPC.delcomments';
    if (!$ljClient->query($ljMethod, $ljArgs)) {
        echo 'Ошибка ['.$ljClient->getErrorCode().'] '.$ljClient->getErrorMessage();
    } else {
        // Получаем ответ
        $ljResponse = $ljClient->getResponse();
        echo "Deleted comment ".$badId."\n";
    }
}

function isBadComment($comment) {
    /*
     * Список полей массива: _show(int), parenttalkid, subject, userid, datepost_unix
     * state (A,D etc), body, level, dtalkid, _loaded, datepost, user, children (array of comments)
     * 
     * нас интересуют, в первую очередь, body и subject
    */
    // сюда можно добавить любые операции с комментарием
    if (preg_match("#".preg_quote(STOP_PHRASE)."#s",$comment['body'])) {
        return true;
    }
    return false;
}