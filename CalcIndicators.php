<?php

require_once 'App.php';
require_once 'Bus.php';

function getArrayOrgs($db,$oktmo_template = '') {
    if ($oktmo_template == '') $oktmo_template = '%%';
    $Orgs = [];
    $query = "SELECT GI.`id`,GI.`shortName`,GI.`inn` FROM `bus_GeneralInfo` GI JOIN `bus_oktmo` OKTMO ON GI.oktmo = oktmo.`id` WHERE oktmo.`code` LIKE ? ";
    //$query = "SELECT GI.`id`,GI.`shortName`,GI.`inn` FROM `bus_GeneralInfo` GI JOIN `bus_oktmo` OKTMO ON GI.oktmo = oktmo.`id` WHERE oktmo.`code` LIKE ? AND GI.INN IN ('3804022507','3803201084','3809024530') LIMIT 50";
    //$query = "SELECT GI.`id`,GI.`shortName`,GI.`inn` FROM `bus_GeneralInfo` GI JOIN `bus_oktmo` OKTMO ON GI.oktmo = oktmo.`id` WHERE oktmo.`code` LIKE ? AND GI.INN='3803201084' LIMIT 50";
    //$query = "SELECT GI.`id`,GI.`shortName`,GI.`inn` FROM `bus_GeneralInfo` GI JOIN `bus_oktmo` OKTMO ON GI.oktmo = oktmo.`id` WHERE oktmo.`code` LIKE ? AND GI.INN='3809024530' LIMIT 50";
    //$query = "SELECT GI.`id`,GI.`shortName`,GI.`inn` FROM `bus_GeneralInfo` GI JOIN `bus_oktmo` OKTMO ON GI.oktmo = oktmo.`id` WHERE oktmo.`code` LIKE ? AND GI.INN='3829000897' LIMIT 50";
    $statement = $db->prepare($query);    
    $statement->bind_param('s', $oktmo_template);
    $statement->execute();
    $statement->bind_result($id, $shortName, $inn);
    $i = 0;
    while ($statement->fetch()) {
        $i++;
        $Orgs[$i]['id'] = $id;
        $Orgs[$i]['shortName'] = $shortName;
        $Orgs[$i]['inn'] = $inn;
    }
    $statement->close();
    return $Orgs;
}

function getArrayYears($db, $org) {
    $Years = [];
    $query = "SELECT formationPeriod FROM (SELECT b1.formationPeriod FROM `bus_BalanceF0503721` b1 WHERE b1.`GeneralInfo` = ? UNION DISTINCT SELECT b2.formationPeriod FROM `bus_BalanceF0503730` b2 WHERE b2.`GeneralInfo` = ?) AS Years ORDER BY formationPeriod ";
    //$query = "SELECT formationPeriod FROM (SELECT b1.formationPeriod FROM `bus_BalanceF0503721` b1 WHERE b1.`GeneralInfo` = ? UNION DISTINCT SELECT b2.formationPeriod FROM `bus_BalanceF0503730` b2 WHERE b2.`GeneralInfo` = ?) AS Years Where formationPeriod = 2017 ORDER BY formationPeriod ";
    $statement = $db->prepare($query);

    $oktmo_template = '25%';

    $statement->bind_param('ii', $org, $org);
    $statement->execute();
    $statement->bind_result($formationPeriod);
    $i = 0;
    while ($statement->fetch()) {
        $i++;
        $Years[$i]['Year'] = $formationPeriod;
    }
    $statement->close();
    return $Years;
}

function getAmount721($Field, $Org, $Year, $Code, $Page, $db) {
    $sql = "SELECT r.`$Field` FROM `bus_BalanceF0503721_records` R JOIN `bus_BalanceF0503721` B ON b.`id` = r.`Balance` JOIN `bus_Balance_codes` c ON c.id = r.`lineCode` WHERE b.`formationPeriod` = ? AND b.`GeneralInfo` = ? AND c.`code` = ? AND r.`page` = ? LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('iisi', $Year, $Org, $Code, $Page);
    $stmt->execute();
    $stmt->bind_result($Amount);
    $stmt->store_result();

    if ($stmt->fetch()) {
        return $Amount;
    } else {
        //APP::echo_log("За ".$Year." нет данных по ф.721 стока 110 по организации ".$Org);
        return null;
    }
    $stmt->close();
}

function getAmount730($Field, $Org, $Year, $Code, $Page, $db) {
    $sql = "SELECT r.`$Field` FROM `bus_BalanceF0503730_records` R JOIN `bus_BalanceF0503730` B ON b.`id` = r.`Balance` JOIN `bus_Balance_codes` c ON c.id = r.`lineCode` WHERE b.`formationPeriod` = ? AND b.`GeneralInfo` = ? AND c.`code` = ? AND r.`page` = ? LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('iisi', $Year, $Org, $Code, $Page);
    $stmt->execute();
    $stmt->bind_result($Amount);
    $stmt->store_result();

    if ($stmt->fetch()) {
        return $Amount;
    } else {
        //APP::echo_log("За ".$Year." нет данных по ф.730 стока 110 по организации ".$Org);
        return null;
    }
    $stmt->close();
}

function getAmount737($Field, $Org, $Year, $Code, $type, $Page, $db) {
   // $sql = "SELECT r.`$Field` FROM `bus_BalanceF0503737_records` R JOIN `bus_BalanceF0503737` B ON b.`id` = r.`Balance` JOIN `bus_Balance_codes` c ON c.id = r.`lineCode` WHERE b.`formationPeriod` = ? AND b.`GeneralInfo` = ? AND c.`code` = ? AND r.`typeFinancialSupport` = ? AND r.`page` = ? LIMIT 1";
   $sql = "SELECT 
            r.`$Field` 
          FROM
            `bus_BalanceF0503737_records` R 
            JOIN `bus_BalanceF0503737` B
              ON b.`id` = r.`Balance` AND b.`GeneralInfo` = ? AND b.`formationPeriod` = ?
            JOIN `bus_Balance_codes` c 
              ON c.id = r.`lineCode` AND c.`code` = ? 
          WHERE  
            r.`typeFinancialSupport` = ?
            AND r.`page` = ?
          LIMIT 1";

    $stmt = $db->prepare($sql);
    $stmt->bind_param('iisii', $Org, $Year, $Code, $type, $Page);
    $stmt->execute();
    $stmt->bind_result($Amount);
    $stmt->store_result();

    if ($stmt->fetch()) {
//            print $Amount.PHP_EOL.PHP_EOL;
        return $Amount;
    } else {
        //APP::echo_log("За ".$Year." нет данных по ф.721 стока 110 по организации ".$Org);
        return null;
    }
    $stmt->close();
}

function SaveValue($ind, $year, $org, $task, $val, $db) {


    $sql = "SELECT id,value FROM bus_indicators_results WHERE GeneralInfo = ? AND Year = ? AND Indicator = ? AND Task = ? ORDER BY id LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('iiii', $org, $year, $ind, $task);
    $stmt->execute();
    $stmt->bind_result($id, $value);
    $stmt->store_result();
    if ($stmt->fetch()) {
        if ($value != $val) {
            //echo $value.PHP_EOL.$val.PHP_EOL.$id;
            $stm = $db->prepare('UPDATE bus_indicators_results SET Value = ? WHERE id = ?');
            $stm->bind_param('di', $val, $id);
            $stm->execute();
        }
    } else {
        $sql = 'INSERT INTO bus_indicators_results (Indicator,Year,GeneralInfo,Task,Value) VALUES (?, ?, ?, ?, ?)';
        $stm = $db->prepare($sql);
        $stm->bind_param('iiiid', $ind, $year, $org, $task, $val);
        $stm->execute();
    }
}

$db = App::getDb();
if (!$db) {
    printf("Невозможно подключиться к базе данных. Код ошибки: %s\n", mysqli_connect_error());
    exit;
}

$oktmo_template = "";
if (count($argv) == 2) {    
    $oktmo_template = $argv[1];
    $oktmo_template=str_replace("*","%",$oktmo_template);    
}
$Orgs = getArrayOrgs($db,$oktmo_template);
$size_orgs = sizeof($Orgs);
$Indicators = [];
$Task = 1;
$Count = 0;
foreach ($Orgs as $org) {
    $Count ++;
    $percent = (int) ($Count / $size_orgs * 100);

    echo PHP_EOL . '[CALC] ' . $Count . ' из ' . $size_orgs . '. ' . $org['shortName'] . ' ИНН: ' . $org['inn'] . '   ( ' . $percent . ' % )' . PHP_EOL;
    $Years = getArrayYears($db, $org['id']);



    foreach ($Years as $y) {
        //echo $y['Year'].PHP_EOL;

        /* 1 Иос гз */
        //echo "     => Считаю. 1 Иос гз               \r";
        if ($y['Year'] == 2017) {
            $string_report = '040';
        } else {
            $string_report = '101';
        }
        $Vsub = getAmount721('stateTaskFunds', $org['id'], $y['Year'], $string_report, 1, $db);
        $Ss_gos_usl = getAmount721('stateTaskFunds', $org['id'], $y['Year'], '372', 3, $db);
        $Osr_gz = getAmount730('stateTaskFundsEndYear', $org['id'], $y['Year'], '030', 1, $db);
        $VB = getAmount730('stateTaskFundsEndYear', $org['id'], $y['Year'], '410', 2, $db);
        $Ost_OCI = getAmount730('stateTaskFundsEndYear', $org['id'], $y['Year'], '338', 2, $db);
        if (( $VB - $Ost_OCI <> 0 && $Ss_gos_usl * ($Osr_gz / ($VB - $Ost_OCI) <> 0)) && ($VB != NULL && $Ost_OCI != NULL && $Ss_gos_usl != NULL && $Osr_gz != NULL && $Vsub != NULL)) {
            $Indicators[$org['id']][$y['Year']][1] = $Vsub / $Ss_gos_usl * ($Osr_gz / ($VB - $Ost_OCI)) * 100;
        } else {
            $Indicators[$org['id']][$y['Year']][1] = false;
        }

        /* 2 Инма гз */
        //echo "     => Считаю. 2 Инма гз               \r";
        $NMA_gz = getAmount730('stateTaskFundsEndYear', $org['id'], $y['Year'], '060', 1, $db);
        if (( $VB - $Ost_OCI <> 0 && $Ss_gos_usl * ($NMA_gz / ($VB - $Ost_OCI)) <> 0 ) && ($Vsub != null && $Ss_gos_usl != null && $NMA_gz != null && $VB != null && $Ost_OCI != null )) {
            $Indicators[$org['id']][$y['Year']][2] = $Vsub / $Ss_gos_usl * ($NMA_gz / ($VB - $Ost_OCI)) * 100;
        } else {
            $Indicators[$org['id']][$y['Year']][2] = false;
        }

        /* 3 Имз гз */
        //echo "     => Считаю. 3 Имз гз               \r";
        $MZ_gz = getAmount730('stateTaskFundsEndYear', $org['id'], $y['Year'], '080', 1, $db);
        if (( $VB - $Ost_OCI <> 0 and $Ss_gos_usl * ($MZ_gz / ($VB - $Ost_OCI) <> 0 )) && ($Vsub != null && $Ss_gos_usl != null && $MZ_gz != null && $VB != null && $Ost_OCI != null)) {
            $Indicators[$org['id']][$y['Year']][3] = $Vsub / $Ss_gos_usl * ($MZ_gz / ($VB - $Ost_OCI)) * 100;
        } else {
            $Indicators[$org['id']][$y['Year']][3] = false;
        }
        /* 4 Тмз */
        //echo "     => Считаю. 4 Тмз               \r";
        $M1 = getAmount730('stateTaskFundsStartYear', $org['id'], $y['Year'], '080', 1, $db);
        $M2 = getAmount730('stateTaskFundsEndYear', $org['id'], $y['Year'], '080', 1, $db);        
        if ($M1 != null && $M2 != null) {
            $MZ_gz_ = ($M1 + $M2) / 2;
        } else {
            $MZ_gz_ = null;
        }
        if (($Vsub <> 0) && ($MZ_gz_ != null && $Vsub != null)) {
            $Indicators[$org['id']][$y['Year']][4] = $MZ_gz_ / $Vsub * 365;
        } else {
            $Indicators[$org['id']][$y['Year']][4] = false;
        }

        /* 5 КоборФА */
        //echo "     => Считаю. 5 КоборФА               \r";
        $F1 = getAmount730('stateTaskFundsStartYear', $org['id'], $y['Year'], '400', 2, $db);
        $F2 = getAmount730('stateTaskFundsEndYear', $org['id'], $y['Year'], '400', 2, $db);
        if ($F1 != null && $F2 != null) {
            $FA_gz_ = ($F1 + $F2) / 2;
        } else {
            $FA_gz_ = null;
        }
        if (($FA_gz_ - $Ost_OCI <> 0) && ($Vsub != null && $FA_gz_ != null && $Ost_OCI != null)) {
            $Indicators[$org['id']][$y['Year']][5] = $Vsub / ($FA_gz_ - $Ost_OCI);
        } else {
            $Indicators[$org['id']][$y['Year']][5] = false;
        }

        /* 6 Иос пдд */
        //echo "     => Считаю. 6 Иос пдд               \r";
        $CHORpdd = getAmount721('revenueFunds', $org['id'], $y['Year'], '300', 2, $db);
        $Ss = getAmount721('revenueFunds', $org['id'], $y['Year'], '372', 3, $db);
        $Osr_pdd = getAmount730('revenueFundsEndYear', $org['id'], $y['Year'], '030', 1, $db);
        $VB_pdd = getAmount730('revenueFundsEndYear', $org['id'], $y['Year'], '410', 2, $db);
        $Ost_OCI_pdd = getAmount730('revenueFundsEndYear', $org['id'], $y['Year'], '338', 2, $db);

        if (($VB_pdd - $Ost_OCI_pdd <> 0 AND $Ss <> 0) && ($CHORpdd != null && $Ss != null && $Osr_pdd != null && $VB_pdd != null && $Ost_OCI_pdd != null )) {
            $Indicators[$org['id']][$y['Year']][6] = $CHORpdd / $Ss * ($Osr_pdd / ($VB_pdd - $Ost_OCI_pdd)) * 100;
        } else {
            $Indicators[$org['id']][$y['Year']][6] = false;
        }

        /* 7 Инма пдд */
        //echo "     => Считаю. 7 Инма пдд               \r";
        $NMA_pdd = getAmount730('revenueFundsEndYear', $org['id'], $y['Year'], '060', 1, $db);
        if (($VB_pdd - $Ost_OCI_pdd <> 0 AND $Ss <> 0) && ($CHORpdd != null && $Ss != null && $NMA_pdd != null && $VB_pdd != null && $Ost_OCI_pdd != null )) {
            $Indicators[$org['id']][$y['Year']][7] = $CHORpdd / $Ss * ($NMA_pdd / ($VB_pdd - $Ost_OCI_pdd)) * 100;
        } else {
            $Indicators[$org['id']][$y['Year']][7] = false;
        }

        /* 8 Имз пдд */
        //echo "     => Считаю. 8 Имз пдд               \r";
        $MZ_pdd = getAmount730('revenueFundsEndYear', $org['id'], $y['Year'], '080', 1, $db);
        if (($VB_pdd - $Ost_OCI_pdd <> 0 AND $Ss <> 0) && ($CHORpdd != null && $Ss != null && $MZ_pdd != null && $VB_pdd != null && $Ost_OCI_pdd != null )) {
            $Indicators[$org['id']][$y['Year']][8] = $CHORpdd / $Ss * ($MZ_pdd / ($VB_pdd - $Ost_OCI_pdd)) * 100;
        } else {
            $Indicators[$org['id']][$y['Year']][8] = false;
        }

        /* 9 Тмз пдд */
        //echo "     => Считаю. 9 Тмз пдд               \r";
        $M1 = getAmount730('revenueFundsEndYear', $org['id'], $y['Year'], '080', 1, $db);        
        $M2 = getAmount730('revenueFundsStartYear', $org['id'], $y['Year'], '080', 1, $db);
        if ($M1 == null) $M1 = 0;
        if ($M2 == null) $M2 = 0;
        $MZ_pdd_ = ($M1 + $M2) / 2;

        if ($CHORpdd <> 0 && ($CHORpdd != null)) {
            $Indicators[$org['id']][$y['Year']][9] = $MZ_pdd_ / $CHORpdd * 365;
        } else {
            $Indicators[$org['id']][$y['Year']][9] = false;
        }

        /* 10 КоборФА */
        //echo "     => Считаю. 10 КоборФА               \r";
        $F1 = getAmount730('revenueFundsEndYear', $org['id'], $y['Year'], '400', 2, $db);
        $F2 = getAmount730('revenueFundsStartYear', $org['id'], $y['Year'], '400', 2, $db);
        if ($F1 == null) $F1 = 0;
        if ($F2 == null) $F2 = 0;
        $FA_pdd = ($F1 + $F2) / 2;

        if ($FA_pdd - $Ost_OCI_pdd <> 0 && ($CHORpdd != null && $Ost_OCI_pdd != null)) {
            $Indicators[$org['id']][$y['Year']][10] = $CHORpdd / ($FA_pdd - $Ost_OCI_pdd);
        } else {
            $Indicators[$org['id']][$y['Year']][10] = false;
        }

        /* 11 Пвнеб */
        //echo "     => Считаю. 11 Пвнеб               \r";
        $Dohody_pdd = getAmount721('revenueFunds', $org['id'], $y['Year'], '010', 1, $db);
        $V_subs = getAmount721('services', $org['id'], $y['Year'], '101', 1, $db) + getAmount721('stateTaskFunds', $org['id'], $y['Year'], '101', 1, $db);
        if ($V_subs <> 0 && ($Dohody_pdd != null && $V_subs != null)) {
            $Indicators[$org['id']][$y['Year']][11] = $Dohody_pdd / $V_subs * 100;
        } else {
            $Indicators[$org['id']][$y['Year']][11] = false;
        }

        /* 12 Ксдп гз */
        //echo "     => Считаю. 12 Ксдп гз               \r";
        $P1 = getAmount737('execPersonalAuthorities', $org['id'], $y['Year'], '010', 4, 1, $db);
        $P2 = getAmount737('execBankAccounts', $org['id'], $y['Year'], '010', 4, 1, $db);
        $P3 = getAmount737('execCashAgency', $org['id'], $y['Year'], '010', 4, 1, $db);
        $P4 = getAmount737('execPersonalAuthorities', $org['id'], $y['Year'], '500', 4, 3, $db);
        $P5 = getAmount737('execBankAccounts', $org['id'], $y['Year'], '500', 4, 3, $db);
        $P6 = getAmount737('execCashAgency', $org['id'], $y['Year'], '500', 4, 3, $db);
        if ($P1 == null) $P1 = 0;
        if ($P2 == null) $P2 = 0;
        if ($P3 == null) $P3 = 0;
        if ($P4 == null) $P4 = 0;
        if ($P5 == null) $P5 = 0;
        if ($P6 == null) $P6 = 0;
        $Postuplenie_ds_gz = $P1 + $P2 + $P3 + $P4 + $P5 + $P6;

        $V1 = getAmount737('execPersonalAuthorities', $org['id'], $y['Year'], '200', 4, 2, $db);
        $V2 = getAmount737('execBankAccounts', $org['id'], $y['Year'], '200', 4, 2, $db);
        $V3 = getAmount737('execCashAgency', $org['id'], $y['Year'], '200', 4, 2, $db);
        if ($V1 == null) $V1 = 0;
        if ($V2 == null) $V2 = 0;
        if ($V3 == null) $V3 = 0;

        $Vybytia_ds_gz = $V1 + $V3 + $V2;

        if ($Vybytia_ds_gz <> 0) {
            $Indicators[$org['id']][$y['Year']][12] = $Postuplenie_ds_gz / $Vybytia_ds_gz;
        } else {
            $Indicators[$org['id']][$y['Year']][12] = false;
        }

        /* 13 Ксдп пдд */
        //echo "     => Считаю. 13 Ксдп пдд               \r";        
        $P1 = getAmount737('execPersonalAuthorities', $org['id'], $y['Year'], '010', 2, 1, $db);        
        $P2 = getAmount737('execBankAccounts', $org['id'], $y['Year'], '010', 2, 1, $db);        
        $P3 = getAmount737('execCashAgency', $org['id'], $y['Year'], '010', 2, 1, $db);        
        $P4 = getAmount737('execPersonalAuthorities', $org['id'], $y['Year'], '500', 2, 3, $db);        
        $P5 = getAmount737('execBankAccounts', $org['id'], $y['Year'], '500', 2, 3, $db);       
        $P6 = getAmount737('execCashAgency', $org['id'], $y['Year'], '500', 2, 3, $db);
        if ($P1 == null) $P1 = 0;
        if ($P2 == null) $P2 = 0;
        if ($P3 == null) $P3 = 0;
        if ($P4 == null) $P4 = 0;
        if ($P5 == null) $P5 = 0;
        if ($P6 == null) $P6 = 0;
        $Postuplenie_ds_pdd = $P1 + $P2 + $P3 + $P4 + $P5 + $P6;
        
        $V1 = getAmount737('execPersonalAuthorities', $org['id'], $y['Year'], '200', 2, 2, $db);
        $V2 = getAmount737('execBankAccounts', $org['id'], $y['Year'], '200', 2, 2, $db);
        $V3 = getAmount737('execCashAgency', $org['id'], $y['Year'], '200', 2, 2, $db);
        if ($V1 == null) $V1 = 0;
        if ($V2 == null) $V2 = 0;
        if ($V3 == null) $V3 = 0;
        $Vybytia_ds_pdd = $V1 + $V2 + $V3;

        if ($Vybytia_ds_pdd <> 0) {
            $Indicators[$org['id']][$y['Year']][13] = $Postuplenie_ds_pdd / $Vybytia_ds_pdd;
        } else {
            $Indicators[$org['id']][$y['Year']][13] = false;
        }

        /* 14 Кддп гз */
        //echo "     => Считаю. 14 Кддп гз               \r";        
        $Nachislennye_rashody = getAmount721('stateTaskFunds', $org['id'], $y['Year'], '150', 2, $db);
        if ($Nachislennye_rashody <> 0 && ($Postuplenie_ds_gz != null && $Nachislennye_rashody != null )) {
            $Indicators[$org['id']][$y['Year']][14] = $Postuplenie_ds_gz / $Nachislennye_rashody;
        } else {
            $Indicators[$org['id']][$y['Year']][14] = false;
        }

        /* 15 Кддп пдд */
        //echo "     => Считаю. 15 Кддп пдд               \r";        
        if ($Vybytia_ds_pdd <> 0 ) {
            $Indicators[$org['id']][$y['Year']][15] = $Dohody_pdd / $Vybytia_ds_pdd;
        } else {
            $Indicators[$org['id']][$y['Year']][15] = false;
        }

        /* 16 Эдп гз */
        //echo "     => Считаю. 16 Эдп гз               \r";  
        if ($Vsub <> 0 && ( $Postuplenie_ds_gz != null && $Vsub != null)) {
            $Indicators[$org['id']][$y['Year']][16] = $Postuplenie_ds_gz / $Vsub;
        } else {
            $Indicators[$org['id']][$y['Year']][16] = false;
        }

        /* 17 Эдп пдд */
        //echo "     => Считаю. 17 Эдп пдд               \r"; 
        if ($Dohody_pdd <> 0 && ( $Postuplenie_ds_pdd != null && $Dohody_pdd != null )) {
            $Indicators[$org['id']][$y['Year']][17] = $Postuplenie_ds_pdd / $Dohody_pdd;
        } else {
            $Indicators[$org['id']][$y['Year']][17] = false;
        }

        /* 18 Ргз */
        //echo "     => Считаю. 18 Ргз               \r";
        $CHORgz = getAmount721('stateTaskFunds', $org['id'], $y['Year'], '300', 2, $db);
        if ($Vsub <> 0 && ( $CHORgz != null && $Vsub != null )) {
            $Indicators[$org['id']][$y['Year']][18] = $CHORgz / $Vsub * 100;
        } else {
            $Indicators[$org['id']][$y['Year']][18] = false;
        }

        /* 19 Рпдд */
        //echo "     => Считаю. 19 Рпдд               \r";
        if ($Dohody_pdd <> 0 && ($CHORpdd != null && $Dohody_pdd != null)) {
            $Indicators[$org['id']][$y['Year']][19] = $CHORpdd / $Dohody_pdd * 100;
        } else {
            $Indicators[$org['id']][$y['Year']][19] = false;
        }

        /* 20 Кэф им гз */
        //echo "     => Считаю. 20 Кэф им гз               \r";
        $R1 = getAmount721('stateTaskFunds', $org['id'], $y['Year'], '173', 2, $db);
        $R2 = getAmount721('stateTaskFunds', $org['id'], $y['Year'], '174', 2, $db);
        $R3 = getAmount721('stateTaskFunds', $org['id'], $y['Year'], '175', 2, $db);
        $R4 = getAmount721('stateTaskFunds', $org['id'], $y['Year'], '250', 2, $db);
        if ($R1 == null) $R1 = 0;
        if ($R2 == null) $R2 = 0;
        if ($R3 == null) $R3 = 0;
        if ($R4 == null) $R4 = 0;
        $Rash_po_soderj_imusch_gz = $R1 + $R2 + $R3 + $R4;

        $V1 = getAmount737('execPersonalAuthorities', $org['id'], $y['Year'], '010', 4, 1, $db);
        $V2 = getAmount737('execBankAccounts', $org['id'], $y['Year'], '010', 4, 1, $db);        
        $V3 = getAmount737('execCashAgency', $org['id'], $y['Year'], '010', 4, 1, $db);
        if ($V1 == null) $V1 = 0;
        if ($V2 == null) $V2 = 0;
        if ($V3 == null) $V3 = 0;
        $Vsub_gz = $V1 + $V2 + $V3;

        if ($Vsub_gz <> 0) {
            $Indicators[$org['id']][$y['Year']][20] = $Rash_po_soderj_imusch_gz / $Vsub_gz * 100;
        } else {
            $Indicators[$org['id']][$y['Year']][20] = false;
        }

        /* 21 Кэф им пдд */
        //echo "     => Считаю. 21 Кэф им пдд               \r";
        $R1 = getAmount721('revenueFunds', $org['id'], $y['Year'], '173', 2, $db);        
        $R2 = getAmount721('revenueFunds', $org['id'], $y['Year'], '174', 2, $db);
        $R3 = getAmount721('revenueFunds', $org['id'], $y['Year'], '175', 2, $db);        
        $R4 = getAmount721('revenueFunds', $org['id'], $y['Year'], '250', 2, $db);
        if ($R1 == null) $R1 = 0;
        if ($R2 == null) $R2 = 0;
        if ($R3 == null) $R3 = 0;
        if ($R4 == null) $R4 = 0;
        $Rash_po_soderj_imusch_pdd = $R1 + $R2 + $R3 + $R4;
        $D1 = getAmount737('execPersonalAuthorities', $org['id'], $y['Year'], '010', 2, 1, $db);
        $D2 = getAmount737('execBankAccounts', $org['id'], $y['Year'], '010', 2, 1, $db);        
        $D3 = getAmount737('execCashAgency', $org['id'], $y['Year'], '010', 2, 1, $db);
        if ($D1 == null) $D1 = 0;
        if ($D2 == null) $D2 = 0;
        if ($D3 == null) $D3 = 0;
        $Dohody_pdd737 = $D1 + $D2 + $D3;

        if ($Dohody_pdd737 <> 0) {
            $Indicators[$org['id']][$y['Year']][21] = $Rash_po_soderj_imusch_gz / $Dohody_pdd737 * 100;
        } else {
            $Indicators[$org['id']][$y['Year']][21] = false;
        }

        /* 22 Кэф упр дох */
        //echo "     => Считаю. 22 Кэф упр дох               \r";
        $P1 = getAmount737('execPersonalAuthorities', $org['id'], $y['Year'], '010', 5, 1, $db);
        $P2 = getAmount737('execBankAccounts', $org['id'], $y['Year'], '010', 5, 1, $db);        
        $P3 = getAmount737('execCashAgency', $org['id'], $y['Year'], '010', 5, 1, $db);        
        $P4 = getAmount737('execPersonalAuthorities', $org['id'], $y['Year'], '500', 5, 3, $db);        
        $P5 = getAmount737('execBankAccounts', $org['id'], $y['Year'], '500', 5, 3, $db);
        $P6 = getAmount737('execCashAgency', $org['id'], $y['Year'], '500', 5, 3, $db);
        if ($P1 == null) $P1 = 0;
        if ($P2 == null) $P2 = 0;
        if ($P3 == null) $P3 = 0;
        if ($P4 == null) $P4 = 0;
        if ($P5 == null) $P5 = 0;
        if ($P6 == null) $P6 = 0;
        $Postuplenie_ds_celsub = $P1 + $P2 + $P3 + $P4 + $P5 + $P6;
        
        $P1 = getAmount737('execPersonalAuthorities', $org['id'], $y['Year'], '010', 6, 1, $db);                
        $P2 = getAmount737('execBankAccounts', $org['id'], $y['Year'], '010', 6, 1, $db);        
        $P3 = getAmount737('execCashAgency', $org['id'], $y['Year'], '010', 6, 1, $db);        
        $P4 = getAmount737('execPersonalAuthorities', $org['id'], $y['Year'], '500', 6, 3, $db);        
        $P5 = getAmount737('execBankAccounts', $org['id'], $y['Year'], '500', 6, 3, $db);        
        $P6 = getAmount737('execCashAgency', $org['id'], $y['Year'], '500', 6, 3, $db);
        if ($P1 == null) $P1 = 0;
        if ($P2 == null) $P2 = 0;
        if ($P3 == null) $P3 = 0;
        if ($P4 == null) $P4 = 0;
        if ($P5 == null) $P5 = 0;
        if ($P6 == null) $P6 = 0;       
        $Postuplenie_ds_kapvlozh = $P1 + $P2 + $P3 + $P4 + $P5 + $P6;
        
        $Obsh_fact_rash = getAmount721('total', $org['id'], $y['Year'], '150', 2, $db);
        if ($Obsh_fact_rash <> 0) {
            $Indicators[$org['id']][$y['Year']][22] = ($Postuplenie_ds_gz + $Postuplenie_ds_pdd + $Postuplenie_ds_celsub + $Postuplenie_ds_kapvlozh) / $Obsh_fact_rash;
        } else {
            $Indicators[$org['id']][$y['Year']][22] = false;
        }
//        Echo "Year=".$y['Year'].PHP_EOL;
//        Echo "org= ".$org['id'].PHP_EOL;
//        
//        
//        Echo "P1=$P1".PHP_EOL;
//        Echo "P2=$P2".PHP_EOL;
//        Echo "P3=$P3".PHP_EOL;
//        Echo "P4=$P4".PHP_EOL;
//        Echo "P5=$P5".PHP_EOL;
//        Echo "P6=$P6".PHP_EOL;
//       
//        Echo "Postuplenie_ds_gz       = $Postuplenie_ds_gz".PHP_EOL;
//        Echo "Postuplenie_ds_pdd      = $Postuplenie_ds_pdd".PHP_EOL;
//        Echo "Postuplenie_ds_celsub   = $Postuplenie_ds_celsub".PHP_EOL;
//        Echo "Postuplenie_ds_kapvlozh = $Postuplenie_ds_kapvlozh".PHP_EOL;
//        Echo "Obsh_fact_rash          = $Obsh_fact_rash".PHP_EOL;        
//        Echo $Indicators[$org['id']][$y['Year']][22].PHP_EOL.PHP_EOL;

        /* 23 Рисп гз */
        //echo "     => Считаю. 23 Рисп гз               \r";
        $R_fact_gz = getAmount721('stateTaskFunds', $org['id'], $y['Year'], '150', 2, $db);
        $Vsub_pl_gz = getAmount737('approvedPlanAssignments', $org['id'], $y['Year'], '010', 4, 1, $db);
        if ($Vsub_pl_gz <> 0 && $R_fact_gz != null && $Vsub_pl_gz != null) {
            $Indicators[$org['id']][$y['Year']][23] = $R_fact_gz / $Vsub_pl_gz * 100;
        } else {
            $Indicators[$org['id']][$y['Year']][23] = false;
        }

        /* 24 Кк */
        //echo "     => Считаю. 24 Кк               \r";
        if ($Vsub <> 0) {
            $Indicators[$org['id']][$y['Year']][24] = $Ss_gos_usl / $Vsub;
        } else {
            $Indicators[$org['id']][$y['Year']][24] = false;
        }

    }
}
//print_r ($Indicators);

$Count = 0;
$Saved = 0;
$Size = sizeof($Indicators, COUNT_RECURSIVE);
foreach ($Indicators as $org => $org_inds) {
    foreach ($org_inds as $year => $inds) {
        foreach ($inds as $ind => $val) {
            $Count++;
            $percent = (int) ($Count / $Size * 100);
            echo "   [SAVE]  $Count ( $percent %)               \r";
            if ($val != false) {
                SaveValue($ind, $year, $org, $Task, $val, $db);
                $Saved ++;
            }
        }
    }
}

echo "Посчитано и сохранено $Saved показателей" . PHP_EOL;
echo "Для " . $size_orgs . " организаций" . PHP_EOL;
?>
