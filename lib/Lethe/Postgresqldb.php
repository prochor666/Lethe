<?php
/**
* PostgreSQL database support
*
* @author Jan Prochazka aka prochor <prochor666@gmail.com>
* @package Lethe
*/

namespace Lethe;

/**
* Lethe\Postgresqldb - PostgreSQL database manipulation class
* @author Jan Prochazka aka prochor <prochor666@gmail.com>
* @license http://opensource.org/licenses/mit-license.php MIT License
* @version 1.0 (2011-04-28)
*/

class Postgresqldb extends Lethe{

   private $host, $db, $user, $pass;

	/**
	* @ignore
	 */
	public function __construct($conf = null){
	  if($conf != null && is_array($conf) && count($conf)>3 && isset( $conf['host'], $conf['db'], $conf['user'], $conf['pass'] ) ){
		 $this->host =  $conf['host'];
			$this->user = $conf['user'];
			$this->pass = $conf['pass'];
			$this->db = $conf['db'];
			$this->port = $conf['port'];
	  }else{
		 $this->host =  _PGHOST_;
			$this->user = _PGUSER_;
			$this->pass = _PGPASS_;
			$this->db = _PGMYDB_;
			$this->port = _PGPORT_;
	  }

	}

   /* Connection */
	private function mydb_connect(){
		$pg_conn_str = 'host='.$this->host.' port='.$this->port.' dbname='.$this->db.' user='.$this->user.' password='.$this->pass;
		$DBLINK = pg_connect($pg_conn_str); 
		return $DBLINK;
	}
	
	/* test connection */
	public function test_connection(){
		$pg_conn_str = 'host='.$this->host.' port='.$this->port.' dbname='.$this->db.' user='.$this->user.' password='.$this->pass;
		$DBLINK = @pg_connect($pg_conn_str); 
		return $DBLINK;
	}
	
	/* test database */
	public function test_db(){
		$pg_conn_str = 'host='.$this->host.' port='.$this->port.' dbname='.$this->db.' user='.$this->user.' password='.$this->pass;
		$DBLINK = @pg_connect($pg_conn_str); 
		return $DBLINK;
	}

	/* BASIC SQL query */
	public function query($sqlquery){
	  $DBLINK = $this->mydb_connect();
		$RESULT = pg_query($DBLINK, trim($sqlquery));
   	$_SESSION['weebo_sql_error'] = $RESULT ? null: pg_last_notice($DBLINK);
		pg_close($DBLINK);
		return $RESULT;
	}

	/* SQL result into array / object */
	public function result($sqlquery, $arrayType = 'assoc'){

	   $__RESULT = $this->query($sqlquery);
	   $__PRECACHE = array();
	   $_SESSION['weebo_sql_error_description'] = false;

	   if(!isset($_SESSION['weebo_sql_error']) && pg_num_rows($__RESULT)>0){

		  switch($arrayType){
			  case "array":
				while($__TESTROW = pg_fetch_array($__RESULT)){
				  array_push($__PRECACHE,$__TESTROW);
				}
			  break; case "row":
				while($__TESTROW = pg_fetch_row($__RESULT)){
				  array_push($__PRECACHE,$__TESTROW);
				}
			  break; case "object": 
				  $__PRECACHE = $__RESULT;
			  break; case "assoc": default:
				while($__TESTROW = pg_fetch_assoc($__RESULT)){
				  array_push($__PRECACHE,$__TESTROW);
				}
  
		  }

		  pg_free_result($__RESULT);

		}else{
			$_SESSION['weebo_sql_error_description'] = '<pre>Q: '.trim($sqlquery).' E:'.$_SESSION['weebo_sql_error'].'</pre>';
		}

	 return $__PRECACHE;
	}


  	/* LAST inserted ID, call it strictly after last query / in transaction */
	public function get_last_id($table){

		#make the initial query
		$link = $this->mydb_connect();
		  
		 /* Auto field[0]  */
		$sql = "SELECT * FROM " . $table;
		$ret = pg_query($link, $sql);
		$idseq = pg_field_name($ret, 0);
		
		/* last item */
		$sql = "SELECT MAX(".$idseq.") FROM ".$table."";
	   
		#exec
		$retorno = pg_query($link, $sql);
		
		if(pg_num_rows($ret)>0){
			$data = pg_fetch_row($retorno);
			return $data[0]; 
		} else {
			#case error, returns false
			return false;
		} 
	}
	
	public function escapeField($data){
		$DBLINK = $this->mydb_connect();
		$RESULT = pg_escape_string($DBLINK, $data);
   		$_SESSION['weebo_sql_error'] = $RESULT ? null: pg_last_notice($DBLINK);
		pg_close($DBLINK);
		return $RESULT; 
	}

}
?>

