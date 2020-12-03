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














// 	private $

// protected $signature = 'db:check 
//     {type : Тип базы (0 - production, 1 - development, 2 - release)} 
//     {--B|base=* : База или бызы которые нужно проверить, обновить, восстановить. Писать как прописаны в prt_subdomains - name} 
//     {--E|etalon= : Эталонная база с которой сравнивать. Если не указывать, то будет раскатана база из миграций и впоследствии удалена} 
//     {--R|repair : Атрибут отвечающий за вмешательство в базу или базы которые проверяются. Если он стоит, то будет сделано все чтобы база стала похожей на эталонную. Будьте осторожны, могут быть утеряны данные}
//     {--W|views : При наличии этого атрибута будут перераскатаны все views (Работает только при наличии атрибута -R или --repair)}
//     {--J|json : Записать в JSON фаил}
//     {--S|sql : Записать alters в SQL фаил}
//     {--I|index : Разрешить ALTER на ключи (Работает только при наличии атрибута -R или --repair)}
//     {--C|create : Разрешить CREATE TABLE (Работает только при наличии атрибута -R или --repair)}
//     {--A|alter : Разрешить ALTER (Работает только при наличии атрибута -R или --repair)}
//     {--D|drop : Разрешить DROP TABLE (Работает только при наличии атрибута -R или --repair)}
//     {--H|createview : Разрешить DROP VIEW (Работает только при наличии атрибута -R или --repair)}
//     {--G|dropview : Разрешить DROP VIEW (Работает только при наличии атрибута -R или --repair)}
//     {--Q|query : Вывести сгенерированные исправления вместо списка ошибок}
//     {--N|nondrop : Не дропать эталонную базу если она раскатана из миграций (Работает только при отстутствии атрибута -E или --etalon)}
//     ';

//     /**
//      * The console command description.
//      *
//      * @var string
//      */
//     protected $description = 'Проверка, восстановление, обновление баз до версии эталонной базы.';

//     /**
//      * Create a new command instance.
//      *
//      * @return void
//      */
//     public function __construct()
//     {
//         parent::__construct();
//     }

//     /**
//      * Execute the console command.
//      *
//      * @return mixed
//      */

//     public function handle()
//     {
//         if(is_null($this->option('etalon'))){
// 	$GoodDBName = 'portal_plin2k_db_check';	
// 	DB::statement("DROP DATABASE IF EXISTS `".$GoodDBName."`");
//             DB::statement(DB::raw('CREATE DATABASE `'.$GoodDBName.'` CHARACTER SET utf8 COLLATE utf8_general_ci'));
//             newConnections($GoodDBName);
		
//             Artisan::call('migrate', ['--database' => $GoodDBName, '--path'=> "/database/migrations/skeleton"]);
//         Artisan::call('migrate', ['--database' => $GoodDBName, '--path'=> "/database/migrations/views"]);
//         Artisan::call('migrate', ['--database' => $GoodDBName, '--path'=> "/database/migrations/skeleton/risks"]);
//         Artisan::call('migrate', ['--database' => $GoodDBName, '--path'=> "/database/migrations/skeleton/rms"]);
//         Artisan::call('migrate', ['--database' => $GoodDBName, '--path'=> "/database/migrations/procedures"]);
//         Artisan::call('migrate', ['--database' => $GoodDBName, '--path'=> "/database/migrations/triggers"]);
//         Artisan::call('migrate', ['--database' => $GoodDBName, '--path'=> "/database/migrations/functions"]);
// $GoodBaseArray = self::ConstructArrayBase($GoodDBName);
//         }else{
//             $GoodDBName = $this->option('etalon');
//             newConnections($GoodDBName);
//             $GoodBaseArray = self::ConstructArrayBase($GoodDBName);
//         }

// 		if(empty($this->option('base'))){
//             $DataBases = Subdomains::on($GoodDBName)->where('active',1)->where('deleted',0)->where('type',$this->argument('type'))->select('name','type','db_name')->get();
// /*
//             $DataBases = DB::raw("SELECT prt_subdomains.contragenty_id,prt_subdomains.`name`  FROM prt_subdomains
// JOIN portal_accesses ON (portal_accesses.contragenty_id = prt_subdomains.contragenty_id AND portal_accesses.module_id = 4 AND !portal_accesses.deleted)
// WHERE prt_subdomains.active = 1 AND prt_subdomains.`type` = 0 AND !prt_subdomains.deleted AND prt_subdomains.contragenty_id != 42557 AND prt_subdomains.contragenty_id != 8761");
// */
// 		}else{
// 			$DataBases = Subdomains::on($GoodDBName)->where('active',1)->where('type',$this->argument('type'))->whereIN('name',$this->option('base'))->select('name','type','db_name')->get();
// 		}
		
		
//             if(is_null($this->option('etalon')) && !$this->option('nondrop')) DB::statement(DB::raw('DROP DATABASE '.$GoodDBName));
		
//         $this->output->progressStart(count($DataBases));
		
//         $DifferentBase = $AlterBase = [];
		
// 		foreach($DataBases as $Base){
// 			try{
// 				$CompareBase = self::ConstructArrayBase($Base->db_name);
// 				$DifferentTables = self::ArrayDiff($GoodBaseArray,$CompareBase);
// 				if(!empty($DifferentTables)){
// 					foreach($DifferentTables as $TableName => $Fields){
// 						if(isset($GoodBaseArray[$TableName]) && isset($CompareBase[$TableName])){
//                             $DifferentFields = self::ArrayDiff($GoodBaseArray[$TableName],$Fields);
// 							foreach($DifferentFields as $FieldID => $FieldParametrs){
// 								if(isset($GoodBaseArray[$TableName][$FieldID]) && isset($CompareBase[$TableName][$FieldID])){
//                                     $DifferentParametrs = self::ArrayDiff($GoodBaseArray[$TableName][$FieldID],$FieldParametrs);
//                                     $HasWrong = 0;
//                                     try{
// 									if(array_key_exists('field',$DifferentParametrs)){
// 										$DifferentBase[$Base->db_name][$TableName][$FieldID]['field'] = 'Different Field Name (Local: \''.$FieldParametrs['field'].'\') & (Migration: \''.$GoodBaseArray[$TableName][$FieldID]['field'].'\')';
//                                         $HasWrong = 1;
// 									}
// 									if(array_key_exists('type',$DifferentParametrs)){
// 										$DifferentBase[$Base->db_name][$TableName][$FieldID]['type'] = 'Different Field Datatype (Local: \''.$FieldParametrs['type'].'\') & (Migration: \''.$GoodBaseArray[$TableName][$FieldID]['type'].'\')';
//                                         $HasWrong = 1;
// 									}
// 									if(array_key_exists('null',$DifferentParametrs)){
// 										$DifferentBase[$Base->db_name][$TableName][$FieldID]['null'] = 'Different Field NULL (Local: \''.$FieldParametrs['null'].'\') & (Migration: \''.$GoodBaseArray[$TableName][$FieldID]['null'].'\')';
//                                         $HasWrong = 1;
// 									}
// 									if(array_key_exists('key',$DifferentParametrs)){
//                                         $DifferentBase[$Base->db_name][$TableName][$FieldID]['key'] = 'Different Field KEY (Local: \''.$FieldParametrs['key'].'\') & (Migration: \''.$GoodBaseArray[$TableName][$FieldID]['key'].'\')';
//                                     }
// 									if(array_key_exists('default',$DifferentParametrs)){
// 										$DifferentBase[$Base->db_name][$TableName][$FieldID]['default'] = 'Different Field Default (Local: \''.$FieldParametrs['default'].'\') & (Migration: \''.$GoodBaseArray[$TableName][$FieldID]['default'].'\')';
//                                         $HasWrong = 1;
// 									}
									
// 			}catch(\Exception $e){
// 				$ErrorMessage = json_decode(json_encode($e),true);
// 				$DifferentBase[$Base->db_name] = 'Database not found or unexpected error: \''.($ErrorMessage['errorInfo'][2]??'Nothing').'\'';
// 			}
//                                     if($HasWrong != 0){
//                                         $AllowNull = ($GoodBaseArray[$TableName][$FieldID]['null'] == 'YES')?' NULL':' NOT NULL';
//                                         $DefaultDefault = (is_null($GoodBaseArray[$TableName][$FieldID]['default']))?'':' DEFAULT '.$GoodBaseArray[$TableName][$FieldID]['default'];
//                                         if(array_key_exists('field',$DifferentParametrs)){
//                                             $AlterBase[$Base->db_name][$TableName][$FieldID] = "ALTER TABLE `$TableName` CHANGE `".$FieldParametrs['field']."` `".$GoodBaseArray[$TableName][$FieldID]['field']."` ".$FieldParametrs['type']." $AllowNull $DefaultDefault;";
//                                         }else{
//                                             $AlterBase[$Base->db_name][$TableName][$FieldID] = "ALTER TABLE `$TableName` MODIFY `".$GoodBaseArray[$TableName][$FieldID]['field']."` ".$GoodBaseArray[$TableName][$FieldID]['type']." $AllowNull $DefaultDefault;";
//                                         }
//                                         self::repairNow($Base->db_name,'alter',$AlterBase[$Base->db_name][$TableName][$FieldID]);
//                                     }
// 								}else{
// 									$Migration = (isset($GoodBaseArray[$TableName][$FieldID]))?'true':'false';
// 									$CurBase = (isset($CompareBase[$TableName][$FieldID]))?'true':'false';
// 									$DifferentBase[$Base->db_name][$TableName][$FieldID] = '(Local: \''.$CurBase.'\') & (Migration: \''.$Migration.'\')';
// 									if($Migration == 'false'){
//                                         $AlterBase[$Base->db_name][$TableName][$FieldID] = "ALTER TABLE `$TableName` DROP COLUMN `$FieldID`;";
//                                         self::repairNow($Base->db_name,'alter',$AlterBase[$Base->db_name][$TableName][$FieldID]);
// 									}
// 								}
//                             }
//                             if(!empty($DifferentFields) && $this->option('index')){
//                                 $AlterBase[$Base->db_name][$TableName]['repair_keys_plin2k'] = "";
//                                 $WhatKeyDeleteArr = self::_group_by(objToArray(DB::connection($Base->db_name)->select("SHOW INDEX FROM `$TableName`;")),'Key_name');
//                                 foreach($WhatKeyDeleteArr as $KeyName => $Key){
//                                     if($KeyName != 'PRIMARY'){
//                                         $AlterBase[$Base->db_name][$TableName]['repair_keys_plin2k'] .= " ALTER TABLE `$TableName` DROP INDEX `$KeyName`;";  
//                                     }
//                                 }
//                                 $WhatKeyArr = self::_group_by(objToArray(DB::connection($GoodDBName)->select("SHOW INDEX FROM `$TableName`;")),'Key_name');
//                                 foreach($WhatKeyArr as $KeyName => $Key){
//                                     $FieldsNeedKeys = [];
//                                     foreach($Key as $KeyArray){
//                                         array_push($FieldsNeedKeys,"`".$KeyArray['Column_name']."`");
//                                     }
                                    
//                                     if($KeyName == 'PRIMARY'){ 
//                                         if(array_search('PRIMARY',$WhatKeyDeleteArr) != false){
//                                             $AlterBase[$Base->db_name][$TableName]['repair_keys_plin2k'] .= " ALTER TABLE `$TableName` ADD PRIMARY KEY (".implode(',',$FieldsNeedKeys)."); ";
//                                         }
//                                     }elseif($Key[0]['Non_unique'] == 1){
//                                         $AlterBase[$Base->db_name][$TableName]['repair_keys_plin2k'] .= " ALTER TABLE `$TableName` ADD UNIQUE INDEX `".$KeyName."` (".implode(',',$FieldsNeedKeys)."); ";
//                                     }elseif($Key[0]['Non_unique'] == 0){
//                                         $AlterBase[$Base->db_name][$TableName]['repair_keys_plin2k'] .= " ALTER TABLE `$TableName` ADD UNIQUE INDEX `".$KeyName."` (".implode(',',$FieldsNeedKeys)."); ";
//                                     }
//                                 }
//                                 self::repairNow($Base->db_name,'alter',$AlterBase[$Base->db_name][$TableName]['repair_keys_plin2k']);
//                             }

// 							$DifferentFields = self::ArrayDiff($Fields,$GoodBaseArray[$TableName]??null);
// 							foreach($DifferentFields as $FieldID => $FieldParametrs){
// 								if(!array_key_exists($FieldID,$CompareBase[$TableName])){
// 									$DifferentBase[$Base->db_name][$TableName][$FieldID] = '(Local: \'false\') & (Migration: \'true\')';
									
// 									$AllowNull = ($GoodBaseArray[$TableName][$FieldID]['null'] == 'YES')?' NULL':' NOT NULL';
// 									$DefaultDefault = (is_null($GoodBaseArray[$TableName][$FieldID]['default']))?'':' DEFAULT '.$GoodBaseArray[$TableName][$FieldID]['default'];
// 									$AlterBase[$Base->db_name][$TableName][$FieldID] = "ALTER TABLE `$TableName` ADD `".$FieldID."` ".$DifferentFields[$FieldID]['type']." $AllowNull $DefaultDefault;";
//                                     self::repairNow($Base->db_name,'alter',$AlterBase[$Base->db_name][$TableName][$FieldID]);
// 								}
// 							}
// 						}else{
// 							$Migration = (isset($GoodBaseArray[$TableName]))?'true':'false';
// 							$CurBase = (isset($CompareBase[$TableName]))?'true':'false';
// 							$DifferentBase[$Base->db_name][$TableName] = '(Local: \''.$CurBase.'\') & (Migration: \''.$Migration.'\')';
// 							$AlterBase[$Base->db_name][$TableName] = "DROP TABLE IF EXISTS `$TableName`;";
//                             self::repairNow($Base->db_name,'drop',$AlterBase[$Base->db_name][$TableName]);
// 						}
// 					}
// 					$DifferentTables = self::ArrayDiff($CompareBase,$GoodBaseArray);
// 					foreach($DifferentTables as $TableName => $Fields){
// 						if(!array_key_exists($TableName,$CompareBase)){
// 							$DifferentBase[$Base->db_name][$TableName] = '(Local: \'false\') & (Migration: \'true\')';
//                             newConnections($GoodDBName);
//                             $FckVarCreate = "Create Table";
//                             $AlterBase[$Base->db_name][$TableName] = DB::connection($GoodDBName)->select("SHOW CREATE TABLE `$TableName`")[0]->$FckVarCreate;
//                             self::repairNow($Base->db_name,'create',$AlterBase[$Base->db_name][$TableName]);
// 						}
// 					}
// 				}else{
// 					$DifferentBase[$Base->db_name] = 'OK';
//                 }

//                 if($this->option('views') && $this->option('repair')){
//                     $ViewsArr = self::getViews($Base->db_name);
//                     $QueryDropView = '';
//                     foreach($ViewsArr as $View){
//                         $QueryDropView .= "DROP VIEW `".array_shift($View)."`; ";
//                     }
//                     self::repairNow($Base->db_name,'dropview',$QueryDropView);
//                     $NewViewsArr = self::getViews($GoodDBName);
//                     $QueryCreateView = '';
//                     foreach($NewViewsArr as $View){
//                         $FckVarCreate = "Create View";
//                         $CreateView = DB::connection($GoodDBName)->select("SHOW CREATE VIEW `".array_shift($View)."`")[0]->$FckVarCreate;
//                         $QueryCreateView .= str_replace(Config::get('values.contragenty_id'),$Base->contragenty_id,$CreateView)."; ";
//                     }
//                     $OutputRepair = self::repairNow($Base->db_name,'createview',$QueryCreateView);
//                     if($OutputRepair == true)continue;
//                     elseif($OutputRepair == false)continue;
//                     else self::repairNow($Base->db_name,'createview',$OutputRepair);
//                 }

// 			}catch(\Exception $e){
// 				$ErrorMessage = json_decode(json_encode($e),true);
// 				$DifferentBase[$Base->db_name] = 'Database not found or unexpected error: \''.($ErrorMessage['errorInfo'][2]??'Nothing').'\'';
// 			}
// 			$this->output->progressAdvance();
// 		}
				
//         $this->output->progressFinish();

//         if($this->option('json')){
//             self::writeToJSON('errors',$DifferentBase);
//             self::writeToJSON('alters',$AlterBase);
//         }
//         if($this->option('sql')){
//             self::writeToSQL('alters_sql',$AlterBase);
//         }

//         if($this->option('query'))dd($AlterBase);
//         else dd($DifferentBase);
        
//     }

//     private function writeToJSON($filename,$content){
//         $pathname = './DBCheckOutput/'.$filename.'.json';
//         $dirname = dirname($pathname);
//         if (!is_dir($dirname)) mkdir($dirname, 0755, true);
//         $editfilename = fopen($pathname,'w+');
//         fwrite($editfilename,json_encode($content));
//         fclose($editfilename);
//     }

//     private function writeToSQL($filename,$content){
//         $pathname = './DBCheckOutput/'.$filename.'.sql';
//         $dirname = dirname($pathname);
//         if (!is_dir($dirname)) mkdir($dirname, 0755, true);
//         $editfilename = fopen($pathname,'w+');
//         foreach($content as $DBName => $DBTables){
//             fwrite($editfilename,"use `".$DBName."`;");
//             fwrite($editfilename,"\n\n");
//             foreach($DBTables as $TableName => $TableRows){
//                 if(is_array($TableRows) == 0){
//                     fwrite($editfilename,$TableRows."\n\n");
//                 }else{
//                     foreach($TableRows as $RowName => $Row){
//                         fwrite($editfilename,$Row."\n");
//                     }
//                 }
//             }
//             fwrite($editfilename,"\n\n\n");
//         }
//         fclose($editfilename);
//     }

//     private function _group_by($array,$key) {
//         $return = array();
//         foreach($array as $val) {
//             $return[$val[$key]][] = $val;
//         }
//         return $return;
//     }

//     private function repairNow($Connection,$Type,$Query){
//         if($this->option('repair')) {
//             if(($Type == 'alter' && $this->option('alter')) || ($Type == 'drop' && $this->option('drop')) || ($Type == 'create' && $this->option('create')) || ($Type == 'drop' && $this->option('drop')) || ($Type == 'dropview' && $this->option('dropview')) || ($Type == 'createview' && $this->option('createview'))){
//                 newConnections($Connection);
//                 $QueryArr = ($Type == 'alter' || $Type == 'dropview' || $Type == 'createview')?explode(';',$Query):[$Query];
//                 $Okay = 0;
//                 $Error = '';
//                 foreach($QueryArr as $Query){
//                     try{
//                         DB::connection($Connection)->statement($Query);
//                         $Okay++;
//                     }catch(\Exception $e){
//                         $Error .= $Query.";";
//                     }
//                 }
//                 if(count($QueryArr) != $Okay){
//                    return $Error;
//                 }else{
//                     return true;
//                 }
//             }else{
//                 return false;
//             }
//         }else{
//             return false;
//         }
//     }

//     private function getViews($BaseName){
//         newConnections($BaseName);
//         $GB_TablesObj = DB::connection($BaseName)->select("SHOW FULL TABLES IN `".$BaseName."` WHERE TABLE_TYPE LIKE 'VIEW'");
//         return objToArray($GB_TablesObj);
//     }
// }