<?php
// Disposable loopback server only; no application configuration is loaded.
$port=(int)getenv('LETHE_MYSQL_TEST_PORT');
if ($port<1 || $port===3306 || getenv('LETHE_TEST_DISPOSABLE')!=='1') { throw new RuntimeException('Explicit disposable-server configuration required'); }
$package=getenv('LETHE_PACKAGE_DIR') ?: dirname(__DIR__,2);
define('__LETHE_ROOT__',dirname(__DIR__,2));define('__LETHE_LETHE__',$package);
$_SERVER=['SERVER_PORT'=>80,'REQUEST_URI'=>'/prepared-driver-test/'];
require $package.'/init/boot.php';
error_reporting(E_ALL & ~E_DEPRECATED);
set_error_handler(function($level,$message,$file,$line) { if(error_reporting() & $level) { throw new ErrorException($message,0,$level,$file,$line); }return true; });
$admin=new mysqli('127.0.0.1','root','','',$port);
$database='lethe_prepared_'.bin2hex(random_bytes(6));
$checks=0;$mode=(new mysqli_driver)->report_mode;
function preparedExpect($value,$message) { global $checks;if(!$value) { throw new RuntimeException($message); }$checks++; }
try {
    $admin->query('CREATE DATABASE `'.$database.'` CHARACTER SET utf8');
    $config=['engine'=>'Mysqldb','host'=>'127.0.0.1','port'=>$port,'db'=>$database,'user'=>'root','password'=>''];
    Lethe\Config::set('db/0',$config);
    $admin->select_db($database);
    $admin->query('CREATE TABLE items (id INT PRIMARY KEY,title VARCHAR(255),optional VARCHAR(255),weight DOUBLE,enabled INT) ENGINE=InnoDB');
    foreach ([false,true] as $persistent) {
        $config['persistent']=$persistent;Lethe\Config::set('db/0',$config);
        $driver=new Lethe\Mysqldb($config);
        $id=$persistent ? 2: 1;
        $payload="O'Reilly \\ Český text '); DROP TABLE items; --";
        preparedExpect($driver->preparedQuery('INSERT INTO items VALUES (?,?,?,?,?)',[$id,$payload,null,1.25,true])===1,'Insert affected rows');
        $rows=Lethe\Db::preparedResult(['query'=>'SELECT * FROM items WHERE id = ?','params'=>[$id]]);
        preparedExpect(count($rows)===1 && $rows[0]['title']===$payload && $rows[0]['optional']===null,'Bound text and null roundtrip');
        preparedExpect((int)$rows[0]['enabled']===1 && abs((float)$rows[0]['weight']-1.25)<0.001,'Native numeric parameter types');
        preparedExpect($driver->preparedResult('SELECT id FROM items WHERE title = ?', ["' OR 1=1 --"])===[],'SQL payload remains data');
        preparedExpect($driver->preparedQuery('UPDATE items SET title = ? WHERE id = ?', ['unchanged',999])===0,'Zero affected rows');
        $driver->preparedQuery('INSERT INTO items VALUES (?,?,?,?,?)',[$id+10,'second',null,2.0,false]);
        $rows=$driver->preparedResult('SELECT id,title FROM items WHERE id IN (?,?) ORDER BY id',[$id,$id+10]);
        preparedExpect(count($rows)===2 && $rows[0]['title']===$payload && $rows[1]['title']==='second','Independent result rows');
        preparedExpect(count($driver->preparedResult('SELECT id FROM items ORDER BY id LIMIT ?',[1]))===1,'Bound integer LIMIT');
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        try { $driver->preparedResult('SELECT invalid_column FROM items WHERE id = ?',[$id]);preparedExpect(false,'Invalid SQL must fail'); }
        catch (RuntimeException $error) { preparedExpect($error->getMessage()==='Database prepare failed','Error excludes SQL and parameter values'); }
        preparedExpect((new mysqli_driver)->report_mode===(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT),'Report mode restored');
        preparedExpect($driver->query("UPDATE items SET title='legacy' WHERE id=".$id)===true,'Legacy write still returns boolean');
        preparedExpect($driver->result('SELECT title FROM items WHERE id='.$id)[0]['title']==='legacy','Legacy read still works after prepared error');
        $bad=false;
        try { $driver->preparedResult('SELECT id FROM items WHERE id = ?', [[]]); }
        catch (InvalidArgumentException $error) { $bad=true; }
        preparedExpect($bad,'Invalid parameter type rejected');
    }
    $admin->query('CREATE TABLE fulltext_items (id INT PRIMARY KEY,title TEXT,lead TEXT,keywords TEXT,content TEXT,ext_link TEXT,
        FULLTEXT(title,lead,keywords,ext_link),FULLTEXT(title,lead,keywords,content,ext_link),FULLTEXT(keywords)) ENGINE=InnoDB');
    Lethe\Db::preparedQuery(['query'=>'INSERT INTO fulltext_items VALUES (?,?,?,?,?,?)','params'=>[1,'Needle document','lead','public','body','link']]);
    foreach (['title,lead,keywords,ext_link','title,lead,keywords,content,ext_link','keywords'] as $columns) {
        $term=$columns==='keywords' ? '"public"': '"needle"';
        $rows=Lethe\Db::preparedResult(['query'=>'SELECT id FROM fulltext_items WHERE MATCH('.$columns.') AGAINST(? IN BOOLEAN MODE)','params'=>[$term]]);
        preparedExpect(count($rows)===1,'Native bound FULLTEXT');
    }
    preparedExpect(count(Lethe\Db::preparedResult(['query'=>"SELECT id FROM fulltext_items WHERE title LIKE ? ESCAPE '!'",'params'=>['%Needle%']]))===1,'Explicit LIKE escape character');
    preparedExpect(count(Lethe\Db::preparedResult(['query'=>'SELECT id FROM fulltext_items WHERE keywords REGEXP ?','params'=>[preg_quote('public')]]))===1,'Bound literal tag regexp');
    echo 'Prepared native driver: '.$checks.' checks passed; PHP '.PHP_VERSION.PHP_EOL;
} finally {
    $admin->query('DROP DATABASE IF EXISTS `'.$database.'`');$admin->close();
    mysqli_report($mode);restore_error_handler();
}
