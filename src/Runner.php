<?php
namespace Plin2k\DBChecker;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

class Runner 
{

	private $etalon = '';
	private $patient = '';
	private $connEtalon = '';
	private $connPatient = '';

	public function __construct(string $sEtalon, $sPatient, $sConnEtalon = false, $sConnPatient = false)
	{
		$this->etalon = $sEtalon;
		$this->patient = $sPatient;
		$this->connEtalon = $sConnEtalon;
		$this->connPatient = $sConnPatient;
		if(!$sConnEtalon)
		{
			$this->newConnection($sEtalon);
			$this->connEtalon = $sEtalon;
		}
		if(!$sConnPatient)
		{
			$this->newConnection($sPatient);
			$this->connPatient = $sPatient;
		} 
	}

	public function getDatabaseDifference()
	{	

		$aSnapshotEtalon = $this->databaseSnapshot($this->etalon, $this->connEtalon);
		$aSnapshotPatient = $this->databaseSnapshot($this->patient, $this->connPatient);

		$aSnapshotDifference = $this->arrayDifference($aSnapshotEtalon, $aSnapshotPatient);

		$aDatabaseDifference = [];

		if(!empty($aSnapshotDifference))
		{
			foreach($aSnapshotDifference as $sTableName => $aFields)
			{
				$aDatabaseDifference[$sTableName]['exists_etalon'] = isset($aSnapshotEtalon[$sTableName]);
				$aDatabaseDifference[$sTableName]['exists_patient'] = isset($aSnapshotPatient[$sTableName]);
				if(!isset($aSnapshotEtalon[$sTableName]) || !isset($aSnapshotPatient[$sTableName]))
				{
					continue;
				}
				$aTableDifference = $this->arrayDifference($aSnapshotEtalon[$sTableName],$aFields);
				foreach($aTableDifference as $sField => $aParameters)
				{
					$aDatabaseDifference[$sTableName][$sField]['exists_etalon'] = isset($aSnapshotEtalon[$sTableName]);
					$aDatabaseDifference[$sTableName][$sField]['exists_patient'] = isset($aSnapshotPatient[$sTableName]);
					if(!isset($aSnapshotEtalon[$sTableName][$sField]) || !isset($aSnapshotPatient[$sTableName][$sField]))
					{
						continue;
					}
					$aParametersDifference = $this->arrayDifference($aSnapshotEtalon[$sTableName][$sField],$aParameters);
					if(array_key_exists('field',$aParametersDifference))
					{
						$aDatabaseDifference[$sTableName][$sField]['field']['exists_etalon'] = $aSnapshotEtalon[$sTableName][$sField]['field'];
						$aDatabaseDifference[$sTableName][$sField]['field']['exists_patient'] = $aParameters['field'];
					}
					if(array_key_exists('type',$aParametersDifference))
					{
						$aDatabaseDifference[$sTableName][$sField]['type']['exists_etalon'] = $aSnapshotEtalon[$sTableName][$sField]['type'];
						$aDatabaseDifference[$sTableName][$sField]['type']['exists_patient'] = $aParameters['type'];
					}
					if(array_key_exists('null',$aParametersDifference))
					{
						$aDatabaseDifference[$sTableName][$sField]['null']['exists_etalon'] = $aSnapshotEtalon[$sTableName][$sField]['null'];
						$aDatabaseDifference[$sTableName][$sField]['null']['exists_patient'] = $aParameters['null'];
					}
					if(array_key_exists('key',$aParametersDifference))
					{
						$aDatabaseDifference[$sTableName][$sField]['key']['exists_etalon'] = $aSnapshotEtalon[$sTableName][$sField]['key'];
						$aDatabaseDifference[$sTableName][$sField]['key']['exists_patient'] = $aParameters['key'];
					}
					if(array_key_exists('default',$aParametersDifference))
					{
						$aDatabaseDifference[$sTableName][$sField]['default']['exists_etalon'] = $aSnapshotEtalon[$sTableName][$sField]['default'];
						$aDatabaseDifference[$sTableName][$sField]['default']['exists_patient'] = $aParameters['default'];
					}
				}
			}
		}
		return  $this->arrayToObject($aDatabaseDifference);
	}

    private function arrayDifference(array $aArrayFirst,$aArraySecond){
        if($aArrayFirst != null && $aArraySecond != null){
        return array_map(
            'unserialize', 
            array_diff_assoc(
                array_map(
                    'serialize',
                    $aArraySecond
                ),
                array_map(
                    'serialize',
                    $aArrayFirst
                )
            )
        );
        }else{
            return [];
        }
    }

    private function databaseSnapshot(string $sDatabaseName,$sConn) : array
    {
        $aOutput = [];
        $GB_TablesObj = DB::connection($sConn)->select("SHOW FULL TABLES IN `".$sDatabaseName."` WHERE TABLE_TYPE LIKE 'BASE TABLE'");
        $GB_Tables = json_decode(json_encode($GB_TablesObj),true);
        foreach($GB_Tables as $GB_Table){
            $sTableName = array_shift($GB_Table);
            $aOutput[$sTableName] = $this->tableSnapshot($sTableName,$sConn);
        }
        return $aOutput;
    }

    private function tableSnapshot(string $sTableName,$sConn) : array
    {	
    	$aOutput = [];
        $getColumns = DB::connection($sConn)->select("SHOW COLUMNS FROM `$sTableName`");
        foreach($getColumns as $Key => $Column){
            $aOutput[$Column->Field]['field'] = $Column->Field;
            $aOutput[$Column->Field]['type'] = $Column->Type;
            $aOutput[$Column->Field]['null'] = $Column->Null;
            $aOutput[$Column->Field]['key'] = $Column->Key;
            $aOutput[$Column->Field]['default'] = $Column->Default;
        }
        return $aOutput;
    }

    private function newConnection(string $sDBName)
    {
		config(["database.connections.$sDBName" => [
			"driver" => "mysql",
			'host' => Config::get('database.connections.mysql.host'),
			'port' => Config::get('database.connections.mysql.port'),
			'database' => $sDBName,
			'username' => Config::get('database.connections.mysql.username'),
			'password' => Config::get('database.connections.mysql.password'),
			'unix_socket' => Config::get('database.connections.mysql.unix_socket'),
			'charset' => 'utf8',
			'collation' => 'utf8_general_ci',
			'prefix' => '',
			'strict' => true,
			'engine' => null,
			'modes' => [
				'STRICT_TRANS_TABLES',
				//    'NO_ZERO_IN_DATE',
				//    'NO_ZERO_DATE',
				'ERROR_FOR_DIVISION_BY_ZERO',
				'NO_ENGINE_SUBSTITUTION',
			],
		]]);
	}


	private function arrayToObject($aArray) {
		$oObject = new \stdClass();
		foreach ($aArray as $sKey => $value) {
			$oObject->$sKey = (is_array($value)) ? $this->arrayToObject($value) : $value;
		}
		return $oObject;
	}
}