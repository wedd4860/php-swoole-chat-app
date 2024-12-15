<?php

namespace framework\Socket\Helpers;

use framework\Socket\Helpers\Str;
use \PDOException;
use \PDO;

class DB
{
    private $insPdoConn; // PDO 인스턴스 변수
    private $strDbHost = ''; // DB접속 호스트
    private $strDbPort = ''; // DB접속 포트
    private $strDbUser = ''; // DB접속 계정
    private $strDbPass = ''; // DB접속 비번
    private $strDbType = ''; // DB 타입, mysql, mssql 등
    private $isReturn = 'N'; // Y, N : mssql 타입의 리턴값이 있을때

    private $chkDebugMode = ''; // 'on' or null (on일 경우, 실행쿼리 보여줌)
    private $statement; // prepare 시 쿼리문 컨트롤 인스턴스
    private $strPrepareQry = ''; // prepare -> execute 하는 경우 사용할 쿼리문 저장

    private $bound_variables = array();
    private $strFetch = 'fetchAll';

    function __construct()
    {
    }

    public function set_strFetch($val = null)
    {
        if ($val == 'fetch' || $val == 'fetchAll' || $val == 'insert' || $val == 'sql') {
            $this->strFetch = $val;
        } else {
            $this->strFetch = "";
        }
    }
    /**
     * DB 접속정보 넘겨받아서 접속실행 후 접속 인스턴스 리턴
     *
     * @param [array] $arrDbInfo : DB접속 정보(global_var 배열변수 넘겨받음)
     * @param [string] $strDbName : 접속할 DB명
     * @return [instance] $this->insPdoConn : 커넥션 인스턴스
     */
    function setDbConnect($arrDbInfo, $strDbName)
    {
        $this->setPdoDbInfo($arrDbInfo);
        $this->insPdoConn = $this->setPdoConnection($strDbName);

        return $this->insPdoConn;
    }

    /**
     * DB 접속정보 배열을 받아서 접속정보 변수로 세팅
     *
     * @param [array] $arrDbInfo : global_var에서 사용하는 배열변수 그대로 사용
     * @return void
     */
    function setPdoDbInfo($arrDbInfo)
    {
        $this->strDbHost = Str::before($arrDbInfo['host'], ':');
        $this->strDbPort = Str::after($arrDbInfo['host'], ':');
        $this->strDbUser = $arrDbInfo['userid'];
        $this->strDbPass = $arrDbInfo['passwd'];
        $this->strDbType = $arrDbInfo['type'];
    }

    /**
     * PDO 커넥션 실행 함수
     *
     * @param [string] $strDbName : 접속할 DB명
     * @return [instance] $insPdoConn : DB 접속 인스턴스
     */
    function setPdoConnection($strDbName)
    {
        if ($this->strDbType == 'mssql') {
            $strConnInfoTxt = 'sqlsrv:Server=' . $this->strDbHost . ',' . $this->strDbPort . ';Database=' . $strDbName;
        } else {
            $strDbPortTxt   = ($this->strDbPort != '') ? 'port=' . $this->strDbPort . ';' : '';
            $strConnInfoTxt = 'mysql:host=' . $this->strDbHost . ';' . $strDbPortTxt . 'dbname=' . $strDbName . ';charset=utf8mb4';
        }
        try {
            $insPdoConn = new PDO($strConnInfoTxt, $this->strDbUser, $this->strDbPass);
            $insPdoConn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            $insPdoConn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            // 에러메시지는 디버깅할 때만 봐야함
            if ($this->chkDebugMode == 'on') {
                echo $e->getMessage() . '<br /><br />';
            }
        }
        return $insPdoConn;
    }

    /**
     * 단일 값 가져올 때 사용하는 쿼리함수
     * count, sum, min, max 등 결과값이 컬럼 한 개(단일 값) 얻을 때 사용 권장
     * 결과가 여러 ROW이고 가져오는 컬럼이 여러개일 때는 첫번째 row의 첫번째 컬럼 값만 가져옴
     *
     * @param [string] $strQuery : 실행할 쿼리문
     * @return [mixed] $rtnColumn : 실행결과값(단일 값)
     */
    function fetchColumn($strQuery)
    {
        if ($this->chkDebugMode == 'on') {
            echo $strQuery . '<br />';
            $msc = microtime(true);
        }

        $rtnColumn = $this->insPdoConn->query($strQuery)->fetchColumn();

        if ($this->chkDebugMode == 'on') {
            $msc = microtime(true) - $msc;
            echo '실행시간 : ' . $msc . 's<br /><br />';
        }

        return $rtnColumn;
    }

    /**
     * 여러 행 값 가져올 때 사용하는 쿼리함수
     * 기존 mysql_fetch_assoc(또는 mysql_fetch_array)와 같은 형태로 결과배열을 생성
     *
     * @param [string] $strQuery : 실행할 쿼리문
     * @return [array] $arrRtnRow : 쿼리결과값(배열)
     */
    function fetchColumns($strQuery)
    {
        if ($this->chkDebugMode == 'on') {
            echo $strQuery . '<br />';
            $msc = microtime(true);
        }

        $arrRtnRow = array();
        $this->statement = $this->insPdoConn->query($strQuery);
        while ($row = $this->statement->fetch(PDO::FETCH_ASSOC)) {
            $arrRtnRow[] = $row;
        }

        if ($this->chkDebugMode == 'on') {
            $msc = microtime(true) - $msc;
            echo 'Execute Time : ' . $msc . 's<br /><br />';
        }
        $this->statement = null;
        return $arrRtnRow;
    }

    /**
     * prepare() -> execute() 하는 경우, 쿼리문 정의해 두는 함수
     *
     * @param [string] $strPrepareQuery : 실행준비할 쿼리문 (치환값은 :COLUMN 으로 사용하도록 권장함)
     * @return void
     */
    function prepare($strPrepareQuery)
    {
        $this->set_strFetch();
        $this->strPrepareQry = $strPrepareQuery; // execute()에서 출력할 쿼리문 저장해 둠
        try {
            $this->statement = $this->insPdoConn->prepare($strPrepareQuery);
        } catch (PDOException $e) {
            $this->errorReport($e);
        }
    }

    private function errorReport($e)
    {
        if ($this->chkDebugMode == 'on') { // 화면 출력
            print_r('<pre>');
            print_r("SQL error (" . $e->getMessage() . ")\n\n");
            print_r('</pre>');
            exit;
        }
    }

    public function cleanUTF8($string, $len = 3)
    {
        if (!$string) return '';

        $string2 = '';
        $strLen = mb_strlen($string, 'utf-8');
        for ($i = 0; $i < $strLen; $i++) {
            $str = mb_substr($string, $i, 1, 'utf-8');

            if (strlen($str) > $len) continue;
            $string2 .= $str;
        }

        # \u2800 안보이는 공백문자 제거
        $string2 = preg_replace('/\x{2800}/u', '', $string2);
        $string2 = preg_replace('/\x{3000}/u', '', $string2);
        $string2 = preg_replace('/\x{200b}/u', '', $string2);
        $string2 = preg_replace('/\x{3164}/u', '', $string2);

        return $string2;
    }

    /**
     * prepare() 사용 후, 실제 쿼리 실행하는 함수
     * $arrValues에는 미리 선언해 놓은 쿼리문 치환값들과 개수가 맞게 정의되어 있어야 함
     *
     * @param [array] $arrValues : prepare 쿼리문의 치환값들과 치환할 값들의 배열
     * @return [array] $arrRtnRow : 쿼리결과값(배열)
     */

    function execute($arrValues = null)
    {
        // prepare -> execute 하는 경우에는 배열값을 치환해서 보여줌
        if ($this->chkDebugMode == 'on') {
            $msc = microtime(true);

            $strShowQuery = $this->strPrepareQry;
            if (isset($arrValues)) {
                foreach ($arrValues as $key => $value) {
                    $strShowQuery = str_replace($key, "'" . $value . "'", $strShowQuery);
                }
            }
            if (count($this->bound_variables) > 0) {
                $this->debugQuery();
            } else {
                echo $strShowQuery . '<br />';
            }
        }
        $res = $this->statement->execute($arrValues);
        $arrRtnRow = null;
        if ($this->strFetch == 'fetchAll') {
            if ($this->isReturn == 'Y') {
                do {
                    $arrRtnRow = $this->statement->fetchAll(PDO::FETCH_ASSOC);
                } while ($this->statement->nextRowset());
            } else {
                $arrRtnRow = $this->statement->fetchAll(PDO::FETCH_ASSOC);
            }
        } else if ($this->strFetch == 'fetch') {
            if ($this->isReturn == 'Y') {
                do {
                    $arrRtnRow = $this->statement->fetch(PDO::FETCH_ASSOC);
                } while ($this->statement->nextRowset());
            } else {
                $arrRtnRow = $this->statement->fetch(PDO::FETCH_ASSOC);
            }
        } else if ($this->strFetch == 'insert') {
            //insert 시에는 마지막 id 리턴
            $arrRtnRow = ['result' => $res, 'lastId' => $this->insPdoConn->lastInsertId()];
        } else if ($this->strFetch == 'sql') {
            //sql 평문
            $arrRtnRow = ['result' => $res];
        }
        $this->statement = null;
        if ($this->chkDebugMode == 'on') {
            $msc = microtime(true) - $msc;
            echo 'Execute Time : ' . $msc . 's<br /><br />';
        }
        return $arrRtnRow;
    }

    /**
     * prepare와 execute를 한꺼번에 수행하는 함수
     * $arrValues값만 바꿔가면서 여러번 execute() 실행할 수 없음
     *
     * @param [string] $strPrepareQuery : 실행준비할 쿼리문 (치환값은 :COLUMN 으로 사용하도록 권장함)
     * @param [array] $arrValues : prepare 쿼리문의 치환값들과 치환할 값들의 배열
     * @return [array] $arrRtnRow : 쿼리결과값(배열)
     */
    function prepareExec($strPrepareQuery, $arrValues)
    {
        $this->prepare($strPrepareQuery);
        $arrRtnRow = $this->execute($arrValues);

        $this->statement = null; // 한 번에 실행하는 경우에는 execute()를 여러번 실행시킬 수 없으므로 바로 초기화 함
        return $arrRtnRow;
    }

    /**
     * PDO 커넥션 끊기
     * PDO 인스턴스 변수와 prepare시에 사용하는 $statement 변수 초기화
     *
     * @return void
     */
    function closePdo()
    {
        $this->statement    = null;
        $this->insPdoConn   = null;
    }

    function close()
    {
        $this->closePdo();
    }
    /**
     * PDO prepare시에 사용하는 $statement 변수 초기화
     *
     * @return void
     */
    function closeStmt()
    {
        $this->statement    = null;
    }

    /**
     * 쿼리 디버그 모드 설정
     *
     * @param [string] $strChk : 'on' or null
     * @return void
     */
    function setDebugQuery($strChk)
    {
        $this->chkDebugMode = ($strChk == 'on') ? 'on' : '';
    }


    /**
     * bind function
     *
     * @param [mixed] $param : bind 식별자.
     * @param [mixed] $value : bind 값
     * @param [int] $dataType : bind 값 타입
     * @return void
     */
    public function bind($param, &$value, $dataType = null, $length = 0)
    {

        if (is_null($dataType)) {
            switch (true) {
                case is_int($value):
                    $dataType = PDO::PARAM_INT;
                    break;
                case is_bool($value):
                    $dataType = PDO::PARAM_BOOL;
                    break;
                case is_null($value):
                    $dataType = PDO::PARAM_NULL;
                    break;
                default:
                    $dataType = PDO::PARAM_STR;
            }
        }

        $this->bound_variables[$param] = (object) array('type' => $dataType, 'value' => $value);
        if ($this->strDbType == 'mssql' && $length > 0) {
            $this->isReturn = 'Y';
            $this->statement->bindParam($param, $value, $dataType, $length);
        } else {
            $this->statement->bindValue($param, $value, $dataType);
        }
    }

    /**
     * bind 디버그용
     *
     * @return void
     */
    public function debugBindedVariables()
    {
        $vars = array();

        foreach ($this->bound_variables as $key => $val) {
            $vars[$key] = $val->value;

            if ($vars[$key] === NULL) continue;

            switch ($val->type) {
                case PDO::PARAM_STR:
                    $type = 'string';
                    break;
                case PDO::PARAM_BOOL:
                    $type = 'boolean';
                    break;
                case PDO::PARAM_INT:
                    $type = 'integer';
                    break;
                case PDO::PARAM_NULL:
                    $type = 'null';
                    break;
                default:
                    $type = FALSE;
            }
            if ($type !== FALSE)
                settype($vars[$key], $type);
        }

        if (is_numeric(key($vars))) {
            ksort($vars);
        }

        return $vars;
    }

    /**
     * query 디버그
     *
     * @return void
     */
    public function debugQuery()
    {
        $queryString = $this->getQueryString();

        echo $queryString . PHP_EOL . '<br />';
    }

    public function getQueryString()
    {
        $queryString = $this->strPrepareQry;

        $vars = $this->debugBindedVariables();

        $params_are_numeric = is_numeric(key($vars));

        foreach ($vars as $key => &$var) {
            switch (gettype($var)) {
                case 'string':
                    $var = "'{$var}'";
                    break;
                case 'integer':
                    $var = "{$var}";
                    break;
                case 'boolean':
                    $var = $var ? 'TRUE' : 'FALSE';
                    break;
                case 'NULL':
                    $var = 'NULL';
                default:
            }
        }

        if ($params_are_numeric) {
            $queryString = preg_replace_callback('/\?/', function ($match) use (&$vars) {
                return array_shift($vars);
            }, $queryString);
        } else {
            $queryString = strtr($queryString, $vars);
        }

        return $queryString;
    }

    function __destruct()
    {
    }
}
